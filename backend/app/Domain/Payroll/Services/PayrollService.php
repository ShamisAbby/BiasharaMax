<?php

namespace App\Domain\Payroll\Services;

use App\Domain\Business\Models\Business;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Finance\Services\ChartOfAccountsService;
use App\Domain\Finance\Services\JournalPostingService;
use App\Domain\Payroll\Models\AttendanceRecord;
use App\Domain\Payroll\Models\EmployeeProfile;
use App\Domain\Payroll\Models\LeaveRequest;
use App\Domain\Payroll\Models\LeaveType;
use App\Domain\Payroll\Models\PayrollPeriod;
use App\Domain\Payroll\Models\Payslip;
use App\Domain\Payroll\Models\PayslipDeduction;
use App\Domain\Payroll\Models\SalaryAllowance;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cut over to integer minor units per docs/ADR/0002-money-format-migration.md
 * (Payroll is first in the rollout order). Every create/update below writes
 * BOTH the legacy decimal column and its `_minor` counterpart from the same
 * Money value, so they can never drift apart — the decimal columns stay
 * fully correct for anything not yet cut over (Http/Controllers, Requests,
 * the React/Livewire views, and Finance's JournalPostingService, which this
 * service still calls with decimal strings unchanged).
 *
 * Statutory-rate calculations (income tax, NSSF, NHIF) deliberately use
 * Money::multiplyTruncate(), not multiply() — bcmath's bcmul($a, $b, 2)
 * truncates, it does not round, so preserving truncation here means this
 * cutover does not silently change any already-computed withholding amount.
 * The overtime/absence rate math further down works the same way, by hand,
 * since it involves an intermediate per-day/per-hour *rate* (not itself a
 * Money amount) that needs the same truncate-not-round treatment.
 */
class PayrollService
{
    // Standard NSSF rate (Tanzania: 10% employer + 5% employee contribution, we capture employee side)
    private const NSSF_RATE = '0.05';

    // NHIF standard employee contribution (flat/tiered, simplified to 1.5%)
    private const NHIF_RATE = '0.015';

    // Flat income tax rate — in production, use jurisdiction-specific brackets
    private const INCOME_TAX_RATE = '0.15';

    public function __construct(
        private readonly ChartOfAccountsService $accounts,
        private readonly JournalPostingService $posting,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{allowance_type: string, amount: string, is_taxable?: bool}>  $allowances
     */
    public function createEmployeeProfile(string $businessId, string $userId, array $data, array $allowances = []): EmployeeProfile
    {
        return DB::transaction(function () use ($businessId, $userId, $data, $allowances) {
            $currency = $this->currencyForBusiness($businessId);

            if (array_key_exists('base_salary', $data)) {
                $data['base_salary_minor'] = Money::fromDecimal($data['base_salary'], $currency)->minorUnits();
            }

            $profile = EmployeeProfile::create(array_merge($data, [
                'business_id' => $businessId,
                'user_id' => $userId,
            ]));

            foreach ($allowances as $allowance) {
                if (array_key_exists('amount', $allowance)) {
                    $allowance['amount_minor'] = Money::fromDecimal($allowance['amount'], $currency)->minorUnits();
                }

                $profile->allowances()->create($allowance);
            }

            return $profile;
        });
    }

    public function createPeriod(string $businessId, array $data): PayrollPeriod
    {
        return PayrollPeriod::create(array_merge($data, [
            'business_id' => $businessId,
            'status' => PayrollPeriod::STATUS_DRAFT,
        ]));
    }

    public function generatePayslips(PayrollPeriod $period): int
    {
        $currency = $this->currencyForBusiness($period->business_id);

        $employees = EmployeeProfile::query()
            ->where('business_id', $period->business_id)
            ->where('status', EmployeeProfile::STATUS_ACTIVE)
            ->with(['allowances' => fn ($q) => $q->where('is_active', true)])
            ->get();

        $period->update(['status' => PayrollPeriod::STATUS_PROCESSING]);

        $totalGross = Money::zero($currency);
        $totalDeductions = Money::zero($currency);
        $totalNet = Money::zero($currency);
        $generated = 0;

        foreach ($employees as $employee) {
            $basicSalary = $employee->baseSalaryMoney($currency);

            $totalAllowancesMinor = (int) $employee->allowances->sum('amount_minor');
            $totalAllowances = Money::fromMinorUnits($totalAllowancesMinor, $currency);

            $grossSalary = $basicSalary->add($totalAllowances);

            // Deductions — truncated, not rounded, matching the legacy
            // bcmul($a, $b, 2) behavior this replaces.
            $incomeTax = $grossSalary->multiplyTruncate(self::INCOME_TAX_RATE);
            $nssf = $basicSalary->multiplyTruncate(self::NSSF_RATE);
            $nhif = $basicSalary->multiplyTruncate(self::NHIF_RATE);
            $socialSecurity = $nssf->add($nhif);
            $totalDed = $incomeTax->add($socialSecurity);
            $netSalary = $grossSalary->subtract($totalDed);
            $zero = Money::zero($currency);

            $payslip = Payslip::create([
                'business_id' => $period->business_id,
                'payroll_period_id' => $period->id,
                'employee_profile_id' => $employee->id,
                'basic_salary' => $basicSalary->toDecimalString(),
                'basic_salary_minor' => $basicSalary->minorUnits(),
                'total_allowances' => $totalAllowances->toDecimalString(),
                'total_allowances_minor' => $totalAllowances->minorUnits(),
                'gross_salary' => $grossSalary->toDecimalString(),
                'gross_salary_minor' => $grossSalary->minorUnits(),
                'income_tax' => $incomeTax->toDecimalString(),
                'income_tax_minor' => $incomeTax->minorUnits(),
                'social_security' => $socialSecurity->toDecimalString(),
                'social_security_minor' => $socialSecurity->minorUnits(),
                'other_deductions' => $zero->toDecimalString(),
                'other_deductions_minor' => $zero->minorUnits(),
                'total_deductions' => $totalDed->toDecimalString(),
                'total_deductions_minor' => $totalDed->minorUnits(),
                'net_salary' => $netSalary->toDecimalString(),
                'net_salary_minor' => $netSalary->minorUnits(),
                'status' => Payslip::STATUS_DRAFT,
            ]);

            $this->createDeduction($payslip, PayslipDeduction::TYPE_INCOME_TAX, 'Income Tax (PAYE)', $incomeTax);
            $this->createDeduction($payslip, PayslipDeduction::TYPE_NSSF, 'NSSF Contribution', $nssf);
            $this->createDeduction($payslip, PayslipDeduction::TYPE_NHIF, 'NHIF Contribution', $nhif);

            // Apply attendance-based adjustments (overtime pay, absent deductions)
            $this->applyAttendanceAdjustments($payslip, $employee, $period, $currency);
            $payslip->refresh();

            $totalGross = $totalGross->add(Money::fromMinorUnits((int) $payslip->gross_salary_minor, $currency));
            $totalDeductions = $totalDeductions->add(Money::fromMinorUnits((int) $payslip->total_deductions_minor, $currency));
            $totalNet = $totalNet->add(Money::fromMinorUnits((int) $payslip->net_salary_minor, $currency));
            $generated++;
        }

        $period->update([
            'total_gross' => $totalGross->toDecimalString(),
            'total_gross_minor' => $totalGross->minorUnits(),
            'total_deductions' => $totalDeductions->toDecimalString(),
            'total_deductions_minor' => $totalDeductions->minorUnits(),
            'total_net' => $totalNet->toDecimalString(),
            'total_net_minor' => $totalNet->minorUnits(),
        ]);

        return $generated;
    }

    public function approvePeriod(PayrollPeriod $period, string $userId): PayrollPeriod
    {
        if ($period->status !== PayrollPeriod::STATUS_PROCESSING) {
            throw new \RuntimeException("Payroll period must be in 'processing' status to approve.");
        }

        $period->update([
            'status' => PayrollPeriod::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        $period->payslips()->update(['status' => Payslip::STATUS_APPROVED]);

        return $period->refresh();
    }

    public function processPayment(PayrollPeriod $period, string $userId, string $cashAccountId): PayrollPeriod
    {
        if ($period->status !== PayrollPeriod::STATUS_APPROVED) {
            throw new \RuntimeException("Payroll period must be approved before processing payment.");
        }

        return DB::transaction(function () use ($period, $userId, $cashAccountId) {
            $currency = $this->currencyForBusiness($period->business_id);

            $salaryExpenseAccount = $this->accounts->resolveSystemAccount($period->business_id, ChartOfAccountsService::KEY_SALARY_EXPENSE);
            $incomeTaxPayableAccount = $this->accounts->resolveSystemAccount($period->business_id, ChartOfAccountsService::KEY_INCOME_TAX_PAYABLE);
            $socialSecurityPayableAccount = $this->accounts->resolveSystemAccount($period->business_id, ChartOfAccountsService::KEY_SOCIAL_SECURITY_PAYABLE);

            $totalSocialSecurity = Money::fromMinorUnits((int) $period->payslips()->sum('social_security_minor'), $currency);
            $totalIncomeTax = Money::fromMinorUnits((int) $period->payslips()->sum('income_tax_minor'), $currency);
            $totalNet = Money::fromMinorUnits((int) $period->total_net_minor, $currency);
            $totalGross = Money::fromMinorUnits((int) $period->total_gross_minor, $currency);
            $zero = Money::zero($currency);

            // JournalPostingService hasn't been cut over yet (Finance is last
            // in the rollout order) — it still takes decimal strings, so we
            // derive them from the same Money values used everywhere else
            // here rather than recomputing from the decimal columns.
            $je = $this->posting->postImmediately($period->business_id, [
                'entry_date' => now()->toDateString(),
                'type' => JournalEntry::TYPE_AUTO,
                'description' => "Payroll payment — {$period->period_name}",
            ], [
                [
                    'account_id' => $salaryExpenseAccount->id,
                    'debit' => $totalGross->toDecimalString(),
                    'credit' => $zero->toDecimalString(),
                    'description' => 'Gross payroll expense',
                ],
                [
                    'account_id' => $incomeTaxPayableAccount->id,
                    'debit' => $zero->toDecimalString(),
                    'credit' => $totalIncomeTax->toDecimalString(),
                    'description' => 'Income tax withheld',
                ],
                [
                    'account_id' => $socialSecurityPayableAccount->id,
                    'debit' => $zero->toDecimalString(),
                    'credit' => $totalSocialSecurity->toDecimalString(),
                    'description' => 'Social security withheld',
                ],
                [
                    'account_id' => $cashAccountId,
                    'debit' => $zero->toDecimalString(),
                    'credit' => $totalNet->toDecimalString(),
                    'description' => 'Net pay disbursed',
                ],
            ], $userId);

            $period->update([
                'status' => PayrollPeriod::STATUS_PAID,
                'paid_at' => now(),
                'journal_entry_id' => $je->id,
            ]);

            $period->payslips()->update(['status' => Payslip::STATUS_PAID, 'paid_at' => now()]);

            return $period->refresh();
        });
    }

    /**
     * Attendance-aware adjustments applied after a base payslip is created.
     * Adds overtime pay to total_allowances/gross and absent-day deduction to other_deductions.
     * If no attendance data exists for the period, this is a no-op (backward compatible).
     *
     * The day/hour rate below is an intermediate *rate*, not itself a Money
     * amount — computed in minor-unit scale via bcmath directly (mirroring
     * exactly what the legacy decimal-scale bcdiv/bcmul calls did, just
     * 100x larger and therefore at least as precise) rather than through
     * Money, since Money's integer minorUnits() can't represent a
     * fractional-minor-unit daily rate without premature rounding.
     */
    private function applyAttendanceAdjustments(Payslip $payslip, EmployeeProfile $employee, PayrollPeriod $period, string $currency): void
    {
        $records = AttendanceRecord::query()
            ->where('employee_profile_id', $employee->id)
            ->whereBetween('attendance_date', [$period->period_start->toDateString(), $period->period_end->toDateString()])
            ->get();

        if ($records->isEmpty()) {
            return;
        }

        $workingDaysInPeriod = $this->workingDaysInPeriod($period->period_start, $period->period_end);
        if ($workingDaysInPeriod <= 0) {
            return;
        }

        $grossSalaryMinor = (string) $payslip->gross_salary_minor;
        $dailyRateMinor = bcdiv($grossSalaryMinor, (string) $workingDaysInPeriod, 6);
        $hourlyRateMinor = bcdiv($dailyRateMinor, '8', 6);

        // Overtime pay: sum overtime_hours × hourly_rate × 1.5, truncated to
        // whole minor units (matches legacy bcmul(..., 2) truncation, just
        // at scale 0 since we're already working in minor units).
        $totalOvertimeHours = (string) $records->sum('overtime_hours');
        $overtimePayMinor = (int) bcmul(bcmul($totalOvertimeHours, $hourlyRateMinor, 6), '1.5', 0);
        $overtimePay = Money::fromMinorUnits($overtimePayMinor, $currency);

        // Absent deduction: days absent (not on approved leave)
        $absentDays = $records->where('status', AttendanceRecord::STATUS_ABSENT)->count();

        // Unpaid leave deduction
        $unpaidLeaveDays = 0;
        $onLeaveRecords = $records->where('status', AttendanceRecord::STATUS_ON_LEAVE);
        foreach ($onLeaveRecords as $record) {
            if ($record->leave_request_id) {
                $leaveRequest = LeaveRequest::find($record->leave_request_id);
                if ($leaveRequest) {
                    $leaveType = LeaveType::find($leaveRequest->leave_type_id);
                    if ($leaveType && ! $leaveType->is_paid) {
                        $unpaidLeaveDays++;
                    }
                }
            }
        }

        $totalDeductionDays = $absentDays + $unpaidLeaveDays;
        $absentDeductionMinor = $totalDeductionDays > 0
            ? (int) bcmul($dailyRateMinor, (string) $totalDeductionDays, 0)
            : 0;
        $absentDeduction = Money::fromMinorUnits($absentDeductionMinor, $currency);

        if ($overtimePay->isZero() && $absentDeduction->isZero()) {
            return;
        }

        // Recalculate payslip with adjustments
        $newAllowances = Money::fromMinorUnits((int) $payslip->total_allowances_minor, $currency)->add($overtimePay);
        $newGross = $newAllowances->add(Money::fromMinorUnits((int) $payslip->basic_salary_minor, $currency));
        $newOtherDeductions = Money::fromMinorUnits((int) $payslip->other_deductions_minor, $currency)->add($absentDeduction);

        // Recalculate statutory deductions on new gross — same
        // truncate-not-round treatment as generatePayslips().
        $basicSalary = Money::fromMinorUnits((int) $payslip->basic_salary_minor, $currency);
        $incomeTax = $newGross->multiplyTruncate(self::INCOME_TAX_RATE);
        $nssf = $basicSalary->multiplyTruncate(self::NSSF_RATE);
        $nhif = $basicSalary->multiplyTruncate(self::NHIF_RATE);
        $newTotalDed = $incomeTax->add($nssf)->add($nhif)->add($newOtherDeductions);
        $newNet = $newGross->subtract($newTotalDed);

        $payslip->update([
            'total_allowances' => $newAllowances->toDecimalString(),
            'total_allowances_minor' => $newAllowances->minorUnits(),
            'gross_salary' => $newGross->toDecimalString(),
            'gross_salary_minor' => $newGross->minorUnits(),
            'income_tax' => $incomeTax->toDecimalString(),
            'income_tax_minor' => $incomeTax->minorUnits(),
            'other_deductions' => $newOtherDeductions->toDecimalString(),
            'other_deductions_minor' => $newOtherDeductions->minorUnits(),
            'total_deductions' => $newTotalDed->toDecimalString(),
            'total_deductions_minor' => $newTotalDed->minorUnits(),
            'net_salary' => $newNet->toDecimalString(),
            'net_salary_minor' => $newNet->minorUnits(),
        ]);

        // Delete and recreate deduction lines so they're accurate
        $payslip->deductions()->delete();
        $this->createDeduction($payslip, PayslipDeduction::TYPE_INCOME_TAX, 'Income Tax (PAYE)', $incomeTax);
        $this->createDeduction($payslip, PayslipDeduction::TYPE_NSSF, 'NSSF Contribution', $nssf);
        $this->createDeduction($payslip, PayslipDeduction::TYPE_NHIF, 'NHIF Contribution', $nhif);

        if ($overtimePay->isPositive()) {
            $negatedOvertimePay = Money::fromMinorUnits(-$overtimePay->minorUnits(), $currency);
            $this->createDeduction($payslip, PayslipDeduction::TYPE_OTHER, "Overtime Pay ({$totalOvertimeHours} hrs)", $negatedOvertimePay);
        }
        if ($absentDeduction->isPositive()) {
            $this->createDeduction($payslip, PayslipDeduction::TYPE_OTHER, "Absent/Unpaid Days ({$totalDeductionDays})", $absentDeduction);
        }
    }

    private function createDeduction(Payslip $payslip, string $type, string $description, Money $amount): void
    {
        PayslipDeduction::create([
            'payslip_id' => $payslip->id,
            'deduction_type' => $type,
            'description' => $description,
            'amount' => $amount->toDecimalString(),
            'amount_minor' => $amount->minorUnits(),
        ]);
    }

    private function workingDaysInPeriod(Carbon $start, Carbon $end): int
    {
        $days = 0;
        $current = $start->copy();
        while ($current->lte($end)) {
            if (! $current->isWeekend()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    private function currencyForBusiness(string $businessId): string
    {
        return Business::query()->find($businessId)?->currency ?? 'TZS';
    }

    public function periodsForBusiness(string $businessId): Collection
    {
        return PayrollPeriod::query()
            ->where('business_id', $businessId)
            ->orderByDesc('period_start')
            ->get();
    }

    public function employeesForBusiness(string $businessId): Collection
    {
        return EmployeeProfile::query()
            ->where('business_id', $businessId)
            ->with(['user:id,name,email', 'allowances'])
            ->orderBy('employee_number')
            ->get();
    }
}
