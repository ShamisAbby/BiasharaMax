<?php

namespace Tests\Feature\Finance;

use App\Domain\Authentication\Models\User;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Services\ChartOfAccountsService;
use App\Domain\Finance\Services\JournalPostingService;
use App\Domain\RBAC\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class AccountUiTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_create_an_account(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->post('/finance/accounts', [
            'code' => '1500',
            'name' => 'Prepaid Expenses',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('accounts', [
            'business_id' => $business->id,
            'code' => '1500',
            'name' => 'Prepaid Expenses',
            'is_system_default' => false,
        ]);
    }

    public function test_duplicate_account_code_fails_validation(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);

        $this->actingAs($owner)->post('/finance/accounts', [
            'code' => $cash->code,
            'name' => 'Duplicate Cash',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ])->assertSessionHasErrors('code');
    }

    public function test_owner_can_update_an_account_name_without_changing_its_type(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);

        $this->actingAs($owner)->patch("/finance/accounts/{$cash->id}", [
            'name' => 'Petty Cash',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $cash->refresh();
        $this->assertSame('Petty Cash', $cash->name);
        $this->assertSame(Account::TYPE_ASSET, $cash->type);
    }

    public function test_system_default_account_cannot_be_deleted(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);

        $this->actingAs($owner)->delete("/finance/accounts/{$cash->id}")
            ->assertSessionHasErrors('account');

        $this->assertDatabaseHas('accounts', ['id' => $cash->id]);
    }

    public function test_account_with_posted_journal_activity_cannot_be_deleted(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);
        $cash = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $coa->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);

        $account = $coa->create($business->id, [
            'code' => '1600',
            'name' => 'Custom Asset',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ]);

        app(JournalPostingService::class)->postImmediately($business->id, [], [
            ['account_id' => $account->id, 'debit' => 50, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 50],
        ]);

        $this->actingAs($owner)->delete("/finance/accounts/{$account->id}")
            ->assertSessionHasErrors('account');

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    }

    public function test_owner_can_delete_an_unused_custom_account(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $coa = app(ChartOfAccountsService::class);

        $account = $coa->create($business->id, [
            'code' => '1800',
            'name' => 'Unused Asset',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ]);

        $this->actingAs($owner)->delete("/finance/accounts/{$account->id}")
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
    }

    public function test_employee_without_chart_of_accounts_permission_cannot_create_an_account(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $plainEmployeeRole = Role::query()->where('business_id', $business->id)->where('slug', Role::EMPLOYEE)->first();
        $employee = User::factory()->create([
            'business_id' => $business->id,
            'role_id' => $plainEmployeeRole->id,
        ]);

        $this->actingAs($employee)->post('/finance/accounts', [
            'code' => '1800',
            'name' => 'Unauthorized Asset',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ])->assertForbidden();

        $this->assertDatabaseMissing('accounts', ['business_id' => $business->id, 'code' => '1800']);
    }
}
