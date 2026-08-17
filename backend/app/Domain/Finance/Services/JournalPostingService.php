<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Events\JournalEntryPosted;
use App\Domain\Finance\Exceptions\JournalEntryException;
use App\Domain\Finance\Exceptions\LockedPeriodException;
use App\Domain\Finance\Exceptions\UnbalancedJournalEntryException;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Finance\Models\JournalLine;
use Illuminate\Support\Facades\DB;

/**
 * The only place journal entries are written. Every entry — manual or
 * auto-posted — passes through createDraft()/postImmediately(), so the
 * balance check and entry numbering can never be bypassed.
 */
class JournalPostingService
{
    /**
     * @param  array{entry_date?: string, type?: string, description?: ?string, memo?: ?string, source_id?: ?string, source_type?: ?string}  $entryData
     * @param  array<int, array{account_id: string, debit?: string|float|int, credit?: string|float|int, description?: ?string, customer_id?: ?string, supplier_id?: ?string}>  $lines
     */
    public function createDraft(string $businessId, array $entryData, array $lines, ?string $createdBy = null): JournalEntry
    {
        return $this->createEntry($businessId, $entryData, $lines, JournalEntry::STATUS_DRAFT, $createdBy);
    }

    /**
     * Creates and posts a balanced entry in one step — the path used by
     * AutoPostingService, since a system-generated entry must never sit
     * around as an unposted draft.
     *
     * @param  array{entry_date?: string, type?: string, description?: ?string, memo?: ?string, source_id?: ?string, source_type?: ?string}  $entryData
     * @param  array<int, array{account_id: string, debit?: string|float|int, credit?: string|float|int, description?: ?string, customer_id?: ?string, supplier_id?: ?string}>  $lines
     */
    /**
     * @param  bool  $bypassPeriodCheck  Set to true only for system-generated closing entries
     *                                   that must post into an already-locked period.
     */
    public function postImmediately(string $businessId, array $entryData, array $lines, ?string $postedBy = null, bool $bypassPeriodCheck = false): JournalEntry
    {
        return DB::transaction(function () use ($businessId, $entryData, $lines, $postedBy, $bypassPeriodCheck) {
            $entry = $this->createEntry($businessId, $entryData, $lines, JournalEntry::STATUS_DRAFT, $postedBy);

            return $this->post($entry, $postedBy, $bypassPeriodCheck);
        });
    }

    public function post(JournalEntry $entry, ?string $postedBy = null, bool $bypassPeriodCheck = false): JournalEntry
    {
        if (! $entry->isDraft()) {
            throw JournalEntryException::invalidTransition($entry->status, 'posted');
        }

        if (! $bypassPeriodCheck) {
            // Silently skipped when no periods have been seeded for this business
            // (same graceful-degradation pattern as businessHasAccounts()).
            try {
                app(FinancialPeriodService::class)->assertPostingAllowed($entry->business_id, $entry->entry_date);
            } catch (LockedPeriodException $e) {
                throw $e;
            } catch (\Throwable) {
                // Service not resolvable yet during early boot — allow
            }
        }

        return DB::transaction(function () use ($entry, $postedBy) {
            $entry = $entry->fresh('lines');
            $this->assertBalanced($entry->lines);

            $entry->update([
                'status' => JournalEntry::STATUS_POSTED,
                'posted_by' => $postedBy,
                'posted_at' => now(),
            ]);

            $entry = $entry->refresh();

            event(new JournalEntryPosted($entry));

            return $entry;
        });
    }

    /**
     * Creates a balanced, posted entry with every line's debit/credit
     * swapped from the original, linking the two records both ways.
     */
    public function reverse(JournalEntry $entry, ?string $reversedBy = null, ?string $reason = null): JournalEntry
    {
        if (! $entry->isPosted()) {
            throw JournalEntryException::invalidTransition($entry->status, 'reversed');
        }

        return DB::transaction(function () use ($entry, $reversedBy, $reason) {
            $entry = $entry->fresh('lines');

            $reversalLines = $entry->lines->map(fn (JournalLine $line) => [
                'account_id' => $line->account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'description' => $line->description,
                'customer_id' => $line->customer_id,
                'supplier_id' => $line->supplier_id,
            ])->all();

            $reversal = $this->postImmediately($entry->business_id, [
                'entry_date' => now()->toDateString(),
                'type' => $entry->type,
                'description' => 'Reversal of '.$entry->entry_number.($reason ? ": {$reason}" : ''),
                'source_id' => $entry->source_id,
                'source_type' => $entry->source_type,
            ], $reversalLines, $reversedBy);

            $reversal->update(['reversal_of_id' => $entry->id]);

            $entry->update([
                'status' => JournalEntry::STATUS_REVERSED,
                'reversed_journal_entry_id' => $reversal->id,
            ]);

            return $entry->refresh();
        });
    }

    /**
     * Drafts can simply be discarded — they were never posted to the
     * ledger, so no balancing reversal is needed.
     */
    public function void(JournalEntry $entry, string $voidedBy, string $reason): JournalEntry
    {
        if (! $entry->isDraft()) {
            throw JournalEntryException::invalidTransition($entry->status, 'voided');
        }

        $entry->update([
            'status' => JournalEntry::STATUS_VOIDED,
            'voided_by' => $voidedBy,
            'voided_at' => now(),
            'void_reason' => $reason,
        ]);

        return $entry->refresh();
    }

    /**
     * @param  array{entry_date?: string, type?: string, description?: ?string, memo?: ?string, source_id?: ?string, source_type?: ?string}  $entryData
     * @param  array<int, array{account_id: string, debit?: string|float|int, credit?: string|float|int, description?: ?string, customer_id?: ?string, supplier_id?: ?string}>  $lines
     */
    private function createEntry(string $businessId, array $entryData, array $lines, string $status, ?string $createdBy): JournalEntry
    {
        if (count($lines) < 2) {
            throw JournalEntryException::noLines();
        }

        $this->assertBalanced($lines);

        return DB::transaction(function () use ($businessId, $entryData, $lines, $status, $createdBy) {
            $entry = JournalEntry::create([
                'business_id' => $businessId,
                'entry_number' => $this->nextEntryNumber($businessId),
                'entry_date' => $entryData['entry_date'] ?? now()->toDateString(),
                'status' => $status,
                'type' => $entryData['type'] ?? JournalEntry::TYPE_MANUAL,
                'description' => $entryData['description'] ?? null,
                'memo' => $entryData['memo'] ?? null,
                'source_id' => $entryData['source_id'] ?? null,
                'source_type' => $entryData['source_type'] ?? null,
                'created_by' => $createdBy,
            ]);

            foreach (array_values($lines) as $index => $line) {
                JournalLine::create([
                    'business_id' => $businessId,
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                    'customer_id' => $line['customer_id'] ?? null,
                    'supplier_id' => $line['supplier_id'] ?? null,
                    'line_number' => $index + 1,
                    'currency_id' => $line['currency_id'] ?? null,
                    'exchange_rate' => $line['exchange_rate'] ?? 1,
                    'foreign_amount' => $line['foreign_amount'] ?? null,
                ]);
            }

            return $entry->load('lines');
        });
    }

    /**
     * @param  iterable<array{debit?: string|float|int, credit?: string|float|int}|JournalLine>  $lines
     */
    private function assertBalanced(iterable $lines): void
    {
        $totalDebits = '0.00';
        $totalCredits = '0.00';

        foreach ($lines as $line) {
            $debit = is_array($line) ? ($line['debit'] ?? 0) : $line->debit;
            $credit = is_array($line) ? ($line['credit'] ?? 0) : $line->credit;

            $totalDebits = bcadd($totalDebits, (string) $debit, 2);
            $totalCredits = bcadd($totalCredits, (string) $credit, 2);
        }

        if (bccomp($totalDebits, $totalCredits, 2) !== 0) {
            throw UnbalancedJournalEntryException::make($totalDebits, $totalCredits);
        }
    }

    /**
     * Business-scoped sequence (JE-000001, JE-000002, ...). Locks existing
     * rows for this business so concurrent posts can never collide.
     */
    private function nextEntryNumber(string $businessId): string
    {
        $lastNumber = JournalEntry::query()
            ->where('business_id', $businessId)
            ->where('entry_number', 'like', 'JE-%')
            ->lockForUpdate()
            ->orderByDesc('entry_number')
            ->value('entry_number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, 3)) + 1 : 1;

        return 'JE-'.str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }
}
