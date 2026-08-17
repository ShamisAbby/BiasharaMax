export type AccountType =
    | 'asset'
    | 'liability'
    | 'equity'
    | 'income'
    | 'expense';

export interface BankAccount {
    id: string;
    bank_name: string;
    account_number: string;
    account_holder_name: string;
    is_active: boolean;
    current_balance: number;
    opening_balance: number;
    currency: { code: string; symbol: string } | null;
    account: { id: string; code: string; name: string } | null;
    last_reconciliation: { period_end: string; status: string } | null;
}

export interface BankTransaction {
    id: string;
    transaction_date: string;
    type: 'debit' | 'credit' | 'transfer';
    amount: number;
    reference: string | null;
    description: string | null;
    reconciliation_status: 'unreconciled' | 'reconciled' | 'void';
    reconciled_at: string | null;
    journal_entry_id: string | null;
}

export interface BankReconciliation {
    id: string;
    period_start: string;
    period_end: string;
    statement_balance: number;
    book_balance: number;
    difference: number;
    status: 'draft' | 'completed';
    completed_at: string | null;
}

export interface FinancialPeriod {
    id: string;
    fiscal_year: number;
    period_name: string;
    period_start: string;
    period_end: string;
    status: 'open' | 'locked' | 'closed';
    is_year_end: boolean;
    locked_at: string | null;
    closed_at: string | null;
}

export interface BudgetLine {
    id: string;
    account: { id: string; code: string; name: string };
    period_start: string;
    period_end: string;
    budgeted_amount: number;
    notes: string | null;
}

export interface Budget {
    id: string;
    name: string;
    fiscal_year: number;
    description: string | null;
    status: 'draft' | 'approved' | 'active' | 'archived';
    approved_at: string | null;
    lines_count?: number;
    lines?: BudgetLine[];
}

export interface BudgetVsActual {
    account_code: string;
    account_name: string;
    period_start: string;
    period_end: string;
    budgeted: string;
    actual: string;
    variance: string;
    variance_pct: string | null;
}

export type NormalBalance = 'debit' | 'credit';

export interface Account {
    id: string;
    code: string;
    name: string;
    description: string | null;
    type: AccountType;
    subtype: string | null;
    normal_balance: NormalBalance;
    is_system_default: boolean;
    is_active: boolean;
    parent_account_id: string | null;
    parent?: { id: string; code: string; name: string } | null;
}

export type JournalEntryStatus = 'draft' | 'posted' | 'reversed' | 'voided';

export type JournalEntryType = 'auto' | 'manual';

export interface JournalLine {
    id: string;
    account: { id: string; code: string; name: string };
    debit: string;
    credit: string;
    description: string | null;
}

export interface JournalEntry {
    id: string;
    entry_number: string;
    entry_date: string;
    status: JournalEntryStatus;
    type: JournalEntryType;
    description: string | null;
    memo: string | null;
    source_type: string | null;
    source_id: string | null;
    void_reason: string | null;
    voided_at: string | null;
    posted_at: string | null;
    posted_by: { id: string; name: string } | null;
    reversal_of_id: string | null;
    reversed_journal_entry_id: string | null;
    total_debit: string;
    total_credit: string;
    lines?: JournalLine[];
    created_at: string;
}
