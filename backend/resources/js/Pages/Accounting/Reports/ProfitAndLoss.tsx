import Card from '@/Components/Card';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AccountingLayout from '@/Layouts/AccountingLayout';
import { formatCurrency } from '@/lib/currency';
import { ProfitAndLossReport } from '@/types/accounting';
import { router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

export default function ProfitAndLoss({
    report,
}: {
    report: ProfitAndLossReport;
}) {
    const [from, setFrom] = useState(report.period.from);
    const [to, setTo] = useState(report.period.to);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('accounting.reports.profit-and-loss'),
            { from, to },
            { preserveState: true },
        );
    };

    const Row = ({
        label,
        value,
        bold = false,
        indent = false,
    }: {
        label: string;
        value: number;
        bold?: boolean;
        indent?: boolean;
    }) => (
        <div
            className={`flex items-center justify-between py-2 ${bold ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-300'}`}
        >
            <span className={indent ? 'pl-4' : ''}>{label}</span>
            <span>{formatCurrency(value)}</span>
        </div>
    );

    return (
        <AccountingLayout title="Profit & Loss">
            <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                <div>
                    <label className="block text-xs font-medium text-gray-500 dark:text-gray-400">
                        From
                    </label>
                    <TextInput
                        type="date"
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-500 dark:text-gray-400">
                        To
                    </label>
                    <TextInput
                        type="date"
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                    />
                </div>
                <SecondaryButton type="submit">Apply</SecondaryButton>
            </form>

            <Card
                title="Profit & Loss Statement"
                description={`${report.period.from} to ${report.period.to}`}
            >
                <div className="divide-y divide-gray-100 dark:divide-gray-700">
                    <div className="pb-2">
                        <p className="text-sm font-semibold uppercase tracking-wide text-gray-400">
                            Revenue
                        </p>
                        <Row
                            label="Sales Revenue"
                            value={report.sales_revenue}
                            indent
                        />
                        <Row
                            label="Other Income"
                            value={report.other_income}
                            indent
                        />
                        <Row
                            label="Total Revenue"
                            value={report.total_revenue}
                            bold
                        />
                    </div>

                    <div className="py-2">
                        <Row
                            label="Cost of Goods Sold"
                            value={report.cost_of_goods_sold}
                        />
                        <Row
                            label="Gross Profit"
                            value={report.gross_profit}
                            bold
                        />
                    </div>

                    <div className="py-2">
                        <p className="text-sm font-semibold uppercase tracking-wide text-gray-400">
                            Operating Expenses
                        </p>
                        {report.expenses_by_category.length > 0 ? (
                            report.expenses_by_category.map((row) => (
                                <Row
                                    key={row.category}
                                    label={row.category}
                                    value={row.total}
                                    indent
                                />
                            ))
                        ) : (
                            <p className="py-2 pl-4 text-sm text-gray-500 dark:text-gray-400">
                                No approved expenses in this period.
                            </p>
                        )}
                        <Row
                            label="Total Expenses"
                            value={report.total_expenses}
                            bold
                        />
                    </div>

                    <div className="pt-2">
                        <Row
                            label="Net Profit"
                            value={report.net_profit}
                            bold
                        />
                    </div>
                </div>
            </Card>
        </AccountingLayout>
    );
}
