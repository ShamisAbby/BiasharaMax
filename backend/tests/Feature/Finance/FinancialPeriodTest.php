<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\Exceptions\LockedPeriodException;
use App\Domain\Finance\Models\FinancialPeriod;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Finance\Models\PeriodClosingEntry;
use App\Domain\Finance\Services\ChartOfAccountsService;
use App\Domain\Finance\Services\FinancialPeriodService;
use App\Domain\Finance\Services\JournalPostingService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class FinancialPeriodTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_seed_default_periods_creates_twelve_monthly_periods(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $service = app(FinancialPeriodService::class);
        $service->seedDefaultPeriods($business->id, 2026);

        $periods = FinancialPeriod::query()
            ->where('business_id', $business->id)
            ->where('fiscal_year', 2026)
            ->get();

        $this->assertCount(12, $periods);

        $january = $periods->firstWhere('period_name', 'January 2026');
        $this->assertNotNull($january);
        $this->assertEquals('open', $january->status);
        $this->assertFalse($january->is_year_end);

        $december = $periods->firstWhere('period_name', 'December 2026');
        $this->assertNotNull($december);
        $this->assertTrue($december->is_year_end);
    }

    public function test_seeding_is_idempotent(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $service = app(FinancialPeriodService::class);
        $service->seedDefaultPeriods($business->id, 2026);
        $service->seedDefaultPeriods($business->id, 2026);

        $count = FinancialPeriod::query()
            ->where('business_id', $business->id)
            ->where('fiscal_year', 2026)
            ->count();

        $this->assertEquals(12, $count);
    }

    public function test_locking_a_period_prevents_journal_posting(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        app(ChartOfAccountsService::class)->seedDefaults($business->id);

        $service = app(FinancialPeriodService::class);
        $service->seedDefaultPeriods($business->id, 2026);

        $january = FinancialPeriod::query()
            ->where('business_id', $business->id)
            ->where('period_name', 'January 2026')
            ->firstOrFail();

        $service->lock($january, $owner->id);

        $this->assertEquals('locked', $january->fresh()->status);

        $this->expectException(LockedPeriodException::class);

        $accounts = \App\Domain\Finance\Models\Account::query()
            ->where('business_id', $business->id)
            ->get();
        $cash = $accounts->firstWhere('code', '1000');
        $income = $accounts->firstWhere('code', '4000');

        app(JournalPostingService::class)->postImmediately($business->id, [
            'entry_date' => '2026-01-15',
            'type' => JournalEntry::TYPE_MANUAL,
            'description' => 'Should be blocked',
        ], [
            ['account_id' => $cash->id, 'debit' => '100.00', 'credit' => '0', 'description' => ''],
            ['account_id' => $income->id, 'debit' => '0', 'credit' => '100.00', 'description' => ''],
        ]);
    }

    public function test_bypass_period_check_allows_posting_to_locked_period(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        app(ChartOfAccountsService::class)->seedDefaults($business->id);

        $service = app(FinancialPeriodService::class);
        $service->seedDefaultPeriods($business->id, 2026);

        $january = FinancialPeriod::query()
            ->where('business_id', $business->id)
            ->where('period_name', 'January 2026')
            ->firstOrFail();

        $service->lock($january, $owner->id);

        $accounts = \App\Domain\Finance\Models\Account::query()
            ->where('business_id', $business->id)
            ->get();
        $cash = $accounts->firstWhere('code', '1000');
        $income = $accounts->firstWhere('code', '4000');

        $je = app(JournalPostingService::class)->postImmediately($business->id, [
            'entry_date' => '2026-01-15',
            'type' => JournalEntry::TYPE_MANUAL,
            'description' => 'Closing entry bypass',
        ], [
            ['account_id' => $cash->id, 'debit' => '100.00', 'credit' => '0', 'description' => ''],
            ['account_id' => $income->id, 'debit' => '0', 'credit' => '100.00', 'description' => ''],
        ], $owner->id, true);

        $this->assertEquals('posted', $je->status);
    }

    public function test_locking_an_already_locked_period_throws(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $service = app(FinancialPeriodService::class);
        $service->seedDefaultPeriods($business->id, 2026);

        $january = FinancialPeriod::query()
            ->where('business_id', $business->id)
            ->where('period_name', 'January 2026')
            ->firstOrFail();

        $service->lock($january, $owner->id);

        $this->expectException(\RuntimeException::class);
        $service->lock($january->fresh(), $owner->id);
    }

    public function test_closing_period_posts_closing_entries_and_marks_closed(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        app(ChartOfAccountsService::class)->seedDefaults($business->id);

        $service = app(FinancialPeriodService::class);
        $service->seedDefaultPeriods($business->id, 2026);

        // Post a revenue entry in January so there is something to close
        $accounts = \App\Domain\Finance\Models\Account::query()
            ->where('business_id', $business->id)
            ->get();
        $cash = $accounts->firstWhere('code', '1000');
        $income = $accounts->firstWhere('code', '4000');

        app(JournalPostingService::class)->postImmediately($business->id, [
            'entry_date' => '2026-01-15',
            'type' => JournalEntry::TYPE_MANUAL,
            'description' => 'Sale revenue',
        ], [
            ['account_id' => $cash->id, 'debit' => '500.00', 'credit' => '0', 'description' => ''],
            ['account_id' => $income->id, 'debit' => '0', 'credit' => '500.00', 'description' => ''],
        ], $owner->id);

        $january = FinancialPeriod::query()
            ->where('business_id', $business->id)
            ->where('period_name', 'January 2026')
            ->firstOrFail();

        $closed = $service->close($january, $owner->id);

        $this->assertEquals('closed', $closed->status);
        $this->assertNotNull($closed->closed_at);

        $closingEntryCount = PeriodClosingEntry::query()
            ->where('financial_period_id', $january->id)
            ->count();

        $this->assertGreaterThan(0, $closingEntryCount);
    }

    public function test_posting_to_open_period_without_periods_seeded_is_allowed(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        app(ChartOfAccountsService::class)->seedDefaults($business->id);

        // No periods seeded — posting should silently succeed (graceful degradation)
        $accounts = \App\Domain\Finance\Models\Account::query()
            ->where('business_id', $business->id)
            ->get();
        $cash = $accounts->firstWhere('code', '1000');
        $income = $accounts->firstWhere('code', '4000');

        $je = app(JournalPostingService::class)->postImmediately($business->id, [
            'entry_date' => '2026-01-15',
            'type' => JournalEntry::TYPE_MANUAL,
            'description' => 'No periods — should be fine',
        ], [
            ['account_id' => $cash->id, 'debit' => '100.00', 'credit' => '0', 'description' => ''],
            ['account_id' => $income->id, 'debit' => '0', 'credit' => '100.00', 'description' => ''],
        ], $owner->id);

        $this->assertEquals('posted', $je->status);
    }
}
