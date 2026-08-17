export interface Customer {
    id: string;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    city: string | null;
    customer_type: 'cash' | 'credit';
    credit_limit: string;
    current_balance: string;
    available_credit: string;
    is_active: boolean;
    notes: string | null;
    created_at: string;
}

export interface SaleItem {
    id: string;
    product_id: string;
    product_name: string;
    product_sku: string | null;
    quantity: string;
    unit_price: string;
    discount_amount: string;
    tax_amount: string;
    line_total: string;
}

export interface SalePayment {
    id: string;
    amount: string;
    payment_method: 'cash' | 'mobile_money' | 'card' | 'bank_transfer';
    reference_number: string | null;
    paid_at: string;
    received_by: string | null;
}

export interface Sale {
    id: string;
    sale_number: string;
    status: 'completed' | 'voided' | 'refunded';
    payment_status: 'paid' | 'partial' | 'unpaid';
    source: 'pos' | 'online';
    delivery_address: string | null;
    subtotal: string;
    discount_amount: string;
    tax_amount: string;
    total_amount: string;
    paid_amount: string;
    balance_due: string;
    notes: string | null;
    voided_at: string | null;
    void_reason: string | null;
    customer: { id: string; name: string } | null;
    sold_by: string | null;
    items: SaleItem[];
    payments: SalePayment[];
    items_count?: number;
    created_at: string;
}

export interface POSProduct {
    id: string;
    name: string;
    sku: string | null;
    barcode: string | null;
    selling_price: string;
    tax_rate: string;
    stock_on_hand: number;
}

export interface SalesDashboardSummary {
    today_sales_count: number;
    today_revenue: number;
    today_profit: number;
    month_sales_count: number;
    month_revenue: number;
    month_profit: number;
    outstanding_credit: number;
    unpaid_sales_count: number;
    customers_count: number;
}

export type SaleReturnStatus = 'pending' | 'approved' | 'rejected';
export type SaleReturnReason =
    | 'damaged'
    | 'wrong_item'
    | 'expired'
    | 'defective'
    | 'changed_mind'
    | 'other';
export type RefundMethod =
    | 'cash'
    | 'bank_transfer'
    | 'mobile_money'
    | 'card'
    | 'store_credit';
export type ReturnItemCondition = 'good' | 'damaged' | 'expired';

export interface SaleReturnItem {
    id: string;
    sale_item_id: string;
    product: { id: string; name: string } | null;
    quantity_returned: string;
    condition: ReturnItemCondition;
    restock: boolean;
    unit_price: string;
    line_refund_amount: string;
    notes: string | null;
}

export interface SaleReturn {
    id: string;
    return_number: string;
    status: SaleReturnStatus;
    reason: SaleReturnReason;
    refund_method: RefundMethod | null;
    refund_amount: string;
    notes: string | null;
    rejection_reason: string | null;
    approved_at: string | null;
    sale: { id: string; sale_number: string; total_amount: string } | null;
    customer: { id: string; name: string } | null;
    approved_by: { id: string; name: string } | null;
    items: SaleReturnItem[];
    created_at: string;
}

export interface SaleReturnDashboardSummary {
    today_returns_count: number;
    today_return_value: number;
    refund_amount_this_month: number;
    pending_returns_count: number;
    approved_returns_count: number;
    rejected_returns_count: number;
}
