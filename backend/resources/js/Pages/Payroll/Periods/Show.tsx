import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import PayrollLayout from '@/Layouts/PayrollLayout';
import { formatCurrency } from '@/lib/currency';
import { ArrowLeftIcon, DocumentTextIcon } from '@heroicons/react/24/outline';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface PayslipRow {
    id: string;
    employee_name: string;
    employee_number: string;
    basic_salary: number;
    total_allowances: number;
    gross_salary: number;
    total_deductions: number;
    net_salary: number;
    status: string;
}

interface CashAccount {
    id: string;
    code: string;
    name: string;
}

interface Period {
    id: string;
    period_name: string;
    period_start: string;
    period_end: string;
    status: string;
    total_gross: number;
    total_deductions: number;
    total_net: number;
    approved_at: string | null;
    paid_at: string | null;
    journal_entry: { id: string; entry_number: string } | null;
    payslips: PayslipRow[];
}

interface Props {
    period: Period;
    cashAccounts: CashAccount[];
}

const STATUS_VARIANT: Record<
    string,
    'neutral' | 'warning' | 'info' | 'success'
> = {
    draft: 'neutral',
    processing: 'warning',
    approved: 'info',
    paid: 'success',
};

const PAYSLIP_VARIANT: Record<
    string,
    'neutral' | 'warning' | 'info' | 'success'
> = {
    draft: 'neutral',
    approved: 'info',
    paid: 'success',
};

export default function PayrollPeriodShow({ period, cashAccounts }: Props) {
    const [showPayModal, setShowPayModal] = useState(false);

    const generateForm = useForm({});
    const approveForm = useForm({});
    const payForm = useForm({ cash_account_id: cashAccounts[0]?.id ?? '' });

    const handleGenerate = () => {
        generateForm.post(route('payroll.periods.generate', period.id));
    };

    const handleApprove = () => {
        approveForm.post(route('payroll.periods.approve', period.id));
    };

    const handlePay = (e: FormEvent) => {
        e.preventDefault();
        payForm.post(route('payroll.periods.pay', period.id), {
            onSuccess: () => setShowPayModal(false),
        });
    };

    const isDraft = period.status === 'draft';
    const isApproved = period.status === 'approved';
    const isPaid = period.status === 'paid';
    const hasPayslips = period.payslips.length > 0;

    return (
        <PayrollLayout title={period.period_name}>
            <div className="flex items-center gap-3">
                <Link
                    href={route('payroll.periods.index')}
                    className="text-gray-400 hover:text-gray-600"
                >
                    <ArrowLeftIcon className="h-5 w-5" />
                </Link>
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                            {period.period_name}
                        </h3>
                        <Badge
                            variant={STATUS_VARIANT[period.status] ?? 'neutral'}
                        >
                            {period.status.charAt(0).toUpperCase() +
                                period.status.slice(1)}
                        </Badge>
                    </div>
                    <p className="mt-0.5 text-xs text-gray-400">
                        {period.period_start} → {period.period_end}
                        {period.paid_at && (
                            <span className="ml-2">
                                · Paid {period.paid_at}
                            </span>
                        )}
                    </p>
                </div>

                <div className="flex gap-2">
                    {isDraft && (
                        <SecondaryButton
                            onClick={handleGenerate}
                            disabled={generateForm.processing}
                        >
                            {generateForm.processing
                                ? 'Generating…'
                                : 'Generate Payslips'}
                        </SecondaryButton>
                    )}
                    {isDraft && hasPayslips && (
                        <PrimaryButton
                            onClick={handleApprove}
                            disabled={approveForm.processing}
                        >
                            {approveForm.processing ? 'Approving…' : 'Approve'}
                        </PrimaryButton>
                    )}
                    {isApproved && (
                        <PrimaryButton onClick={() => setShowPayModal(true)}>
                            Process Payment
                        </PrimaryButton>
                    )}
                    {isPaid && period.journal_entry && (
                        <Link
                            href={route(
                                'finance.journals.show',
                                period.journal_entry.id,
                            )}
                            className="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            <DocumentTextIcon className="h-4 w-4" />
                            {period.journal_entry.entry_number}
                        </Link>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-3 gap-4">
                <Card>
                    <p className="text-xs text-gray-500">Total Gross</p>
                    <p className="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                        {formatCurrency(period.total_gross)}
                    </p>
                </Card>
                <Card>
                    <p className="text-xs text-gray-500">Total Deductions</p>
                    <p className="mt-1 text-xl font-bold text-red-600">
                        ({formatCurrency(period.total_deductions)})
                    </p>
                </Card>
                <Card>
                    <p className="text-xs text-gray-500">Net Pay</p>
                    <p className="mt-1 text-xl font-bold text-green-600">
                        {formatCurrency(period.total_net)}
                    </p>
                </Card>
            </div>

            <Card>
                <h4 className="mb-4 text-sm font-semibold text-gray-900 dark:text-white">
                    Payslips{' '}
                    {hasPayslips && (
                        <span className="ml-1 text-xs font-normal text-gray-400">
                            ({period.payslips.length})
                        </span>
                    )}
                </h4>
                {!hasPayslips ? (
                    <div className="py-10 text-center text-sm text-gray-400">
                        No payslips yet.
                        {isDraft &&
                            ' Click "Generate Payslips" to compute salaries for all active employees.'}
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 dark:border-gray-700">
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        #
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Employee
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Basic
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Allowances
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Gross
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Deductions
                                    </th>
                                    <th className="pb-2 text-right font-medium text-gray-500">
                                        Net
                                    </th>
                                    <th className="pb-2 text-left font-medium text-gray-500">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50 dark:divide-gray-700">
                                {period.payslips.map((p) => (
                                    <tr key={p.id}>
                                        <td className="py-2 pr-3 text-xs text-gray-400">
                                            {p.employee_number}
                                        </td>
                                        <td className="py-2 pr-4 font-medium text-gray-800 dark:text-gray-200">
                                            {p.employee_name}
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono text-gray-600">
                                            {formatCurrency(p.basic_salary)}
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono text-gray-600">
                                            {formatCurrency(p.total_allowances)}
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono font-medium">
                                            {formatCurrency(p.gross_salary)}
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono text-red-500">
                                            (
                                            {formatCurrency(p.total_deductions)}
                                            )
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono font-semibold text-green-600">
                                            {formatCurrency(p.net_salary)}
                                        </td>
                                        <td className="py-2">
                                            <Badge
                                                variant={
                                                    PAYSLIP_VARIANT[p.status] ??
                                                    'neutral'
                                                }
                                            >
                                                {p.status}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-t-2 border-gray-200 dark:border-gray-600">
                                    <td
                                        colSpan={4}
                                        className="pt-2 text-right text-xs font-medium text-gray-500"
                                    >
                                        Totals
                                    </td>
                                    <td className="pr-4 pt-2 text-right font-mono font-bold">
                                        {formatCurrency(period.total_gross)}
                                    </td>
                                    <td className="pr-4 pt-2 text-right font-mono font-bold text-red-500">
                                        (
                                        {formatCurrency(
                                            period.total_deductions,
                                        )}
                                        )
                                    </td>
                                    <td className="pr-4 pt-2 text-right font-mono font-bold text-green-600">
                                        {formatCurrency(period.total_net)}
                                    </td>
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}
            </Card>

            <Modal
                show={showPayModal}
                onClose={() => setShowPayModal(false)}
                maxWidth="sm"
            >
                <form onSubmit={handlePay} className="p-6">
                    <h3 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        Process Payroll Payment
                    </h3>
                    <p className="mb-4 text-sm text-gray-500">
                        This will post a journal entry debiting Salary Expense (
                        {formatCurrency(period.total_gross)}) and crediting the
                        selected cash/bank account for the net pay (
                        {formatCurrency(period.total_net)}).
                    </p>
                    <div>
                        <InputLabel
                            htmlFor="cash_account"
                            value="Payment Account (Cash / Bank)"
                        />
                        <SelectInput
                            id="cash_account"
                            className="mt-1 block w-full"
                            value={payForm.data.cash_account_id}
                            onChange={(e) =>
                                payForm.setData(
                                    'cash_account_id',
                                    e.target.value,
                                )
                            }
                            required
                        >
                            <option value="">Select account</option>
                            {cashAccounts.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.code} — {a.name}
                                </option>
                            ))}
                        </SelectInput>
                        <InputError
                            message={
                                (payForm.errors as Record<string, string>)
                                    .cash_account_id
                            }
                            className="mt-1"
                        />
                        <InputError
                            message={
                                (payForm.errors as Record<string, string>)
                                    .period
                            }
                            className="mt-1"
                        />
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            onClick={() => setShowPayModal(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={payForm.processing}>
                            {payForm.processing
                                ? 'Processing…'
                                : 'Confirm Payment'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </PayrollLayout>
    );
}
