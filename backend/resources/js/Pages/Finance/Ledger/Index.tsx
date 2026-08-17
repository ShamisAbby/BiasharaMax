import Card from '@/Components/Card';
import FinanceLayout from '@/Layouts/FinanceLayout';
import { formatCurrency } from '@/lib/currency';
import { Account } from '@/types/finance';
import { Link } from '@inertiajs/react';

interface AccountRow {
    account: Account;
    balance: string;
}

const TYPE_BADGE: Record<string, string> = {
    asset: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    liability: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    equity: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
    income: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    expense:
        'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
};

export default function LedgerIndex({ accounts }: { accounts: AccountRow[] }) {
    return (
        <FinanceLayout title="General Ledger">
            <Card
                title="General Ledger"
                description="Click any account to view its detailed transaction history and running balance."
            >
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th className="px-4 py-3">Code</th>
                                <th className="px-4 py-3">Account Name</th>
                                <th className="px-4 py-3">Type</th>
                                <th className="px-4 py-3 text-right">
                                    Balance
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                            {accounts.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        No accounts found. Seed your Chart of
                                        Accounts first.
                                    </td>
                                </tr>
                            ) : (
                                accounts.map(({ account, balance }) => (
                                    <tr
                                        key={account.id}
                                        className="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                    >
                                        <td className="px-4 py-3 font-mono text-sm text-gray-600 dark:text-gray-400">
                                            {account.code}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Link
                                                href={route(
                                                    'finance.ledger.show',
                                                    account.id,
                                                )}
                                                className="font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                            >
                                                {account.name}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize ${TYPE_BADGE[account.type] ?? ''}`}
                                            >
                                                {account.type}
                                            </span>
                                        </td>
                                        <td
                                            className={`px-4 py-3 text-right font-medium tabular-nums ${parseFloat(balance) < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100'}`}
                                        >
                                            {formatCurrency(
                                                parseFloat(balance),
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </Card>
        </FinanceLayout>
    );
}
