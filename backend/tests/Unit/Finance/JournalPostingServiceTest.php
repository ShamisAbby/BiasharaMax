<?php

namespace Tests\Unit\Finance;

use App\Domain\Finance\Exceptions\JournalEntryException;
use App\Domain\Finance\Exceptions\UnbalancedJournalEntryException;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Finance\Services\ChartOfAccountsService;
use App\Domain\Finance\Services\JournalPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class JournalPostingServiceTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    private JournalPostingService $postingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->postingService = app(JournalPostingService::class);
    }

    public function test_a_balanced_draft_can_be_created_and_posted(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $entry = $this->postingService->createDraft($business->id, [
            'description' => 'Cash sale',
        ], [
            ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 100],
        ]);

        $this->assertTrue($entry->isDraft());
        $this->assertSame('JE-000001', $entry->entry_number);
        $this->assertCount(2, $entry->lines);

        $posted = $this->postingService->post($entry);

        $this->assertTrue($posted->isPosted());
        $this->assertNotNull($posted->posted_at);
    }

    public function test_an_unbalanced_entry_is_rejected(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $this->expectException(UnbalancedJournalEntryException::class);

        $this->postingService->createDraft($business->id, [], [
            ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 90],
        ]);
    }

    public function test_postImmediately_creates_an_already_posted_entry(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $entry = $this->postingService->postImmediately($business->id, [
            'type' => JournalEntry::TYPE_AUTO,
        ], [
            ['account_id' => $cash->id, 'debit' => 50, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 50],
        ]);

        $this->assertTrue($entry->isPosted());
    }

    public function test_entry_numbers_increment_per_business(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $lines = [
            ['account_id' => $cash->id, 'debit' => 10, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 10],
        ];

        $first = $this->postingService->createDraft($business->id, [], $lines);
        $second = $this->postingService->createDraft($business->id, [], $lines);

        $this->assertSame('JE-000001', $first->entry_number);
        $this->assertSame('JE-000002', $second->entry_number);
    }

    public function test_reversing_a_posted_entry_creates_a_mirrored_posted_entry(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $entry = $this->postingService->postImmediately($business->id, [], [
            ['account_id' => $cash->id, 'debit' => 75, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 75],
        ]);

        $reversed = $this->postingService->reverse($entry, null, 'Customer dispute');

        $this->assertSame(JournalEntry::STATUS_REVERSED, $reversed->status);
        $this->assertNotNull($reversed->reversed_journal_entry_id);

        $reversal = JournalEntry::findOrFail($reversed->reversed_journal_entry_id);
        $this->assertTrue($reversal->isPosted());
        $this->assertSame($entry->id, $reversal->reversal_of_id);

        $cashLine = $reversal->lines->firstWhere('account_id', $cash->id);
        $revenueLine = $reversal->lines->firstWhere('account_id', $revenue->id);
        $this->assertSame('75.00', $cashLine->credit);
        $this->assertSame('75.00', $revenueLine->debit);
    }

    public function test_only_draft_entries_can_be_voided(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $entry = $this->postingService->postImmediately($business->id, [], [
            ['account_id' => $cash->id, 'debit' => 20, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 20],
        ]);

        $this->expectException(JournalEntryException::class);

        $this->postingService->void($entry, 'system', 'test');
    }

    public function test_a_draft_can_be_voided(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $entry = $this->postingService->createDraft($business->id, [], [
            ['account_id' => $cash->id, 'debit' => 20, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 20],
        ]);

        $voided = $this->postingService->void($entry, $owner->id, 'Created by mistake');

        $this->assertSame(JournalEntry::STATUS_VOIDED, $voided->status);
        $this->assertSame('Created by mistake', $voided->void_reason);
    }

    public function test_an_entry_with_fewer_than_two_lines_is_rejected(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);

        $this->expectException(JournalEntryException::class);

        $this->postingService->createDraft($business->id, [], [
            ['account_id' => $cash->id, 'debit' => 20, 'credit' => 0],
        ]);
    }
}
