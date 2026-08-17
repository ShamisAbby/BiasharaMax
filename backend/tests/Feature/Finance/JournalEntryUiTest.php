<?php

namespace Tests\Feature\Finance;

use App\Domain\Authentication\Models\User;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Finance\Services\ChartOfAccountsService;
use App\Domain\Finance\Services\JournalPostingService;
use App\Domain\RBAC\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class JournalEntryUiTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_create_a_draft_journal_entry(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $this->actingAs($owner)->post('/finance/journal', [
            'entry_date' => Carbon::today()->toDateString(),
            'description' => 'Manual cash sale',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 100],
            ],
        ])->assertSessionHasNoErrors();

        $entry = JournalEntry::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertTrue($entry->isDraft());
        $this->assertCount(2, $entry->lines);
    }

    public function test_creating_an_unbalanced_entry_fails_validation(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $this->actingAs($owner)->post('/finance/journal', [
            'entry_date' => Carbon::today()->toDateString(),
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 90],
            ],
        ])->assertSessionHasErrors('lines');

        $this->assertSame(0, JournalEntry::query()->where('business_id', $business->id)->count());
    }

    public function test_owner_can_post_a_draft_entry(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $entry = app(JournalPostingService::class)->createDraft($business->id, [], [
            ['account_id' => $cash->id, 'debit' => 50, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 50],
        ]);

        $this->actingAs($owner)->post("/finance/journal/{$entry->id}/post")
            ->assertSessionHasNoErrors();

        $this->assertTrue($entry->refresh()->isPosted());
    }

    public function test_owner_can_void_a_draft_entry(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $entry = app(JournalPostingService::class)->createDraft($business->id, [], [
            ['account_id' => $cash->id, 'debit' => 20, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 20],
        ]);

        $this->actingAs($owner)->post("/finance/journal/{$entry->id}/void", [
            'reason' => 'Created by mistake',
        ])->assertSessionHasNoErrors();

        $this->assertSame(JournalEntry::STATUS_VOIDED, $entry->refresh()->status);
    }

    public function test_owner_can_reverse_a_posted_entry(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $entry = app(JournalPostingService::class)->postImmediately($business->id, [], [
            ['account_id' => $cash->id, 'debit' => 75, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 75],
        ]);

        $this->actingAs($owner)->post("/finance/journal/{$entry->id}/reverse", [
            'reason' => 'Customer dispute',
        ])->assertSessionHasNoErrors();

        $this->assertSame(JournalEntry::STATUS_REVERSED, $entry->refresh()->status);
    }

    public function test_employee_without_finance_journal_manage_permission_cannot_create_an_entry(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $plainEmployeeRole = Role::query()->where('business_id', $business->id)->where('slug', Role::EMPLOYEE)->first();
        $employee = User::factory()->create([
            'business_id' => $business->id,
            'role_id' => $plainEmployeeRole->id,
        ]);

        $this->actingAs($employee)->post('/finance/journal', [
            'entry_date' => Carbon::today()->toDateString(),
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 10, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 10],
            ],
        ])->assertForbidden();

        $this->assertSame(0, JournalEntry::query()->where('business_id', $business->id)->count());
    }

    public function test_employee_without_finance_journal_post_permission_cannot_post_a_draft(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $entry = app(JournalPostingService::class)->createDraft($business->id, [], [
            ['account_id' => $cash->id, 'debit' => 10, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 10],
        ]);

        $plainEmployeeRole = Role::query()->where('business_id', $business->id)->where('slug', Role::EMPLOYEE)->first();
        $employee = User::factory()->create([
            'business_id' => $business->id,
            'role_id' => $plainEmployeeRole->id,
        ]);

        $this->actingAs($employee)->post("/finance/journal/{$entry->id}/post")
            ->assertForbidden();

        $this->assertTrue($entry->refresh()->isDraft());
    }
}
