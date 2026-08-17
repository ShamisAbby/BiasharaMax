<?php

namespace Tests\Feature\Payroll;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Services\ChartOfAccountsService;
use App\Domain\Payroll\Models\EmployeeProfile;
use App\Domain\Payroll\Models\PayrollPeriod;
use App\Domain\Payroll\Models\Payslip;
use App\Domain\Payroll\Services\PayrollService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function makeEmployee(string $businessId, string $userId, float $baseSalary = 50000.00): EmployeeProfile
    {
        return app(PayrollService::class)->createEmployeeProfile($businessId, $userId, [
            'employee_number' => 'EMP-' . substr($userId, 0, 4),
            'employment_date' => '2025-01-01',
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'base_salary' => (string) $baseSalary,
            'salary_cycle' => 'monthly',
            'status' => EmployeeProfile::STATUS_ACTIVE,
            'created_by' => $userId,
        ]);
    }

    public function test_can_create_employee_profile_with_allowances(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $profile = app(PayrollService::class)->createEmployeeProfile($business->id, $owner->id, [
            'employee_number' => 'EMP-001',
            'employment_date' => '2025-01-01',
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'base_salary' => '80000.00',
            'salary_cycle' => 'monthly',
            'status' => EmployeeProfile::STATUS_ACTIVE,
            'department' => 'Engineering',
            'position' => 'Senior Engineer',
            'created_by' => $owner->id,
        ], [
            ['allowance_type' => 'housing', 'amount' => '10000.00', 'is_taxable' => true, 'is_active' => true],
            ['allowance_type' => 'transport', 'amount' => '5000.00', 'is_taxable' => false, 'is_active' => true],
        ]);

        $this->assertEquals('EMP-001', $profile->employee_number);
        $this->assertEquals('80000.00', $profile->base_salary);
        $this->assertCount(2, $profile->allowances);
        $this->assertEquals('95000.00', $profile->grossSalary());
    }

    public function test_generating_payslips_computes_deductions_correctly(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->makeEmployee($business->id, $owner->id, 100000.00);

        $service = app(PayrollService::class);
        $period = $service->createPeriod($business->id, [
            'period_name' => 'June 2026',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'salary_cycle' => 'monthly',
            'created_by' => $owner->id,
        ]);

        $count = $service->generatePayslips($period);

        $this->assertEquals(1, $count);

        $payslip = Payslip::query()->where('payroll_period_id', $period->id)->firstOrFail();

        $this->assertEquals('100000.00', $payslip->gross_salary);

        // Income tax = 100000 × 15% = 15000
        $this->assertEquals('15000.00', $payslip->income_tax);

        // NSSF = 100000 × 5% = 5000; NHIF = 100000 × 1.5% = 1500 → social_security = 6500
        $this->assertEquals('6500.00', $payslip->social_security);

        // Total deductions = 15000 + 6500 = 21500
        $this->assertEquals('21500.00', $payslip->total_deductions);

        // Net = 100000 - 21500 = 78500
        $this->assertEquals('78500.00', $payslip->net_salary);

        $period->refresh();
        $this->assertEquals('processing', $period->status);
        $this->assertEquals('100000.00', $period->total_gross);
        $this->assertEquals('78500.00', $period->total_net);
    }

    public function test_approving_period_sets_status_and_payslips_to_approved(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $this->makeEmployee($business->id, $owner->id);

        $service = app(PayrollService::class);
        $period = $service->createPeriod($business->id, [
            'period_name' => 'June 2026',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'salary_cycle' => 'monthly',
            'created_by' => $owner->id,
        ]);

        $service->generatePayslips($period);
        $service->approvePeriod($period->fresh(), $owner->id);

        $period->refresh();
        $this->assertEquals(PayrollPeriod::STATUS_APPROVED, $period->status);
        $this->assertNotNull($period->approved_at);

        $this->assertSame(
            0,
            Payslip::query()->where('payroll_period_id', $period->id)->where('status', '!=', Payslip::STATUS_APPROVED)->count(),
        );
    }

    public function test_approving_non_processing_period_throws(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $service = app(PayrollService::class);
        $period = $service->createPeriod($business->id, [
            'period_name' => 'June 2026',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'salary_cycle' => 'monthly',
            'created_by' => $owner->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $service->approvePeriod($period, $owner->id);
    }

    public function test_processing_payment_posts_payroll_journal_entry(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $this->makeEmployee($business->id, $owner->id, 60000.00);

        // createOwnerWithBusiness seeds all system accounts including KEY_SALARY_EXPENSE
        $cashAccount = Account::query()
            ->where('business_id', $business->id)
            ->where('code', '1000') // Cash
            ->firstOrFail();

        $service = app(PayrollService::class);
        $period = $service->createPeriod($business->id, [
            'period_name' => 'June 2026',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'salary_cycle' => 'monthly',
            'created_by' => $owner->id,
        ]);

        $service->generatePayslips($period);
        $service->approvePeriod($period->fresh(), $owner->id);
        $service->processPayment($period->fresh(), $owner->id, $cashAccount->id);

        $period->refresh();
        $this->assertEquals(PayrollPeriod::STATUS_PAID, $period->status);
        $this->assertNotNull($period->paid_at);
        $this->assertNotNull($period->journal_entry_id);

        // Verify payslips are paid
        $this->assertSame(
            0,
            Payslip::query()->where('payroll_period_id', $period->id)->where('status', '!=', Payslip::STATUS_PAID)->count(),
        );

        // Verify journal entry exists and balances
        $je = $period->load('journalEntry.lines')->journalEntry;
        $this->assertNotNull($je);

        $totalDebits = $je->lines->sum('debit');
        $totalCredits = $je->lines->sum('credit');
        $this->assertEquals(number_format($totalDebits, 2), number_format($totalCredits, 2));
    }

    public function test_processing_payment_on_non_approved_period_throws(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $cashAccount = Account::query()
            ->where('business_id', $business->id)
            ->where('code', '1000')
            ->firstOrFail();

        $service = app(PayrollService::class);
        $period = $service->createPeriod($business->id, [
            'period_name' => 'June 2026',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'salary_cycle' => 'monthly',
            'created_by' => $owner->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $service->processPayment($period, $owner->id, $cashAccount->id);
    }

    public function test_payroll_with_allowances_includes_allowances_in_gross(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        app(PayrollService::class)->createEmployeeProfile($business->id, $owner->id, [
            'employee_number' => 'EMP-001',
            'employment_date' => '2025-01-01',
            'employment_type' => EmployeeProfile::TYPE_FULL_TIME,
            'base_salary' => '50000.00',
            'salary_cycle' => 'monthly',
            'status' => EmployeeProfile::STATUS_ACTIVE,
            'created_by' => $owner->id,
        ], [
            ['allowance_type' => 'housing', 'amount' => '10000.00', 'is_taxable' => true, 'is_active' => true],
        ]);

        $service = app(PayrollService::class);
        $period = $service->createPeriod($business->id, [
            'period_name' => 'June 2026',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'salary_cycle' => 'monthly',
            'created_by' => $owner->id,
        ]);

        $service->generatePayslips($period);

        $payslip = Payslip::query()->where('payroll_period_id', $period->id)->firstOrFail();

        $this->assertEquals('50000.00', $payslip->basic_salary);
        $this->assertEquals('10000.00', $payslip->total_allowances);
        $this->assertEquals('60000.00', $payslip->gross_salary);

        // Income tax = 60000 × 15% = 9000
        $this->assertEquals('9000.00', $payslip->income_tax);
    }

    /**
     * Proves the docs/ADR/0002-money-format-migration.md dual-write: every
     * `_minor` integer column must always agree with its legacy decimal
     * sibling column (decimal x100 = minor), for both Payslip and
     * PayrollPeriod. If PayrollService ever writes one without the other,
     * this is the test that catches it.
     */
    public function test_minor_unit_columns_agree_with_legacy_decimal_columns(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $this->makeEmployee($business->id, $owner->id, 100000.00);

        $service = app(PayrollService::class);
        $period = $service->createPeriod($business->id, [
            'period_name' => 'June 2026',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'salary_cycle' => 'monthly',
            'created_by' => $owner->id,
        ]);

        $service->generatePayslips($period);

        $payslip = Payslip::query()->where('payroll_period_id', $period->id)->firstOrFail();

        foreach ([
            'basic_salary', 'total_allowances', 'gross_salary', 'income_tax',
            'social_security', 'other_deductions', 'total_deductions', 'net_salary',
        ] as $field) {
            $decimal = (string) $payslip->{$field};
            $minor = (int) $payslip->{"{$field}_minor"};
            $this->assertSame(
                $decimal,
                bcdiv((string) $minor, '100', 2),
                "{$field} decimal/_minor mismatch: {$decimal} vs {$minor}"
            );
        }

        $period->refresh();
        foreach (['total_gross', 'total_deductions', 'total_net'] as $field) {
            $decimal = (string) $period->{$field};
            $minor = (int) $period->{"{$field}_minor"};
            $this->assertSame($decimal, bcdiv((string) $minor, '100', 2));
        }

        foreach ($payslip->deductions as $deduction) {
            $decimalAmount = (string) $deduction->amount;
            $minorAmount = (int) $deduction->amount_minor;
            $this->assertSame($decimalAmount, bcdiv((string) $minorAmount, '100', 2));
        }
    }
}
