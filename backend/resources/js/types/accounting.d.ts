export interface ExpenseCategory {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    expenses_count?: number;
    created_at: string;
}

export type ExpenseStatus = 'pending' | 'approved' | 'rejected' | 'paid';

export interface Expense {
    id: string;
    title: string;
    description: string | null;
    amount: string;
    expense_date: string;
    payment_method: string;
    status: ExpenseStatus;
    receipt_path: string | null;
    is_recurring: boolean;
    recurrence_frequency: string | null;
    next_recurrence_date: string | null;
    rejection_reason: string | null;
    notes: string | null;
    category: { id: string; name: string } | null;
    supplier: { id: string; name: string } | null;
    employee: { id: string; name: string } | null;
    branch: { id: string; name: string } | null;
    approved_by: { id: string; name: string } | null;
    approved_at: string | null;
    created_at: string;
}

export type IncomeCategory = 'service' | 'other' | 'manual';

export interface Income {
    id: string;
    title: string;
    description: string | null;
    category: IncomeCategory;
    amount: string;
    income_date: string;
    payment_method: string;
    notes: string | null;
    customer: { id: string; name: string } | null;
    branch: { id: string; name: string } | null;
    created_at: string;
}

export interface FinancialSummary {
    cash_balance: number;
    bank_balance: number;
    total_revenue: number;
    total_expenses: number;
    today_expenses: number;
    gross_profit: number;
    net_profit: number;
    outstanding_debts: number;
    accounts_payable: number;
    tax_collected: number;
    pending_expenses_count: number;
}

export interface ProfitTrendPoint {
    label: string;
    revenue: number;
    expenses: number;
    profit: number;
}

export interface ExpenseCategoryTotal {
    category: string;
    total: number;
}

export interface ProfitAndLossReport {
    period: { from: string; to: string };
    sales_revenue: number;
    cost_of_goods_sold: number;
    other_income: number;
    total_revenue: number;
    gross_profit: number;
    expenses_by_category: ExpenseCategoryTotal[];
    total_expenses: number;
    net_profit: number;
}
