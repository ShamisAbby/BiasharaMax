export type PurchaseOrderStatus =
    | 'draft'
    | 'pending_approval'
    | 'approved'
    | 'rejected'
    | 'sent'
    | 'partially_received'
    | 'fully_received'
    | 'cancelled'
    | 'closed';

export interface PurchaseOrderItem {
    id: string;
    product_id: string;
    product_variant_id: string | null;
    product_name: string;
    product_sku: string | null;
    quantity_ordered: string;
    quantity_received: string;
    remaining_quantity: string;
    unit_cost: string;
    discount_amount: string;
    tax_amount: string;
    line_total: string;
    notes: string | null;
}

export interface SupplierPayment {
    id: string;
    amount: string;
    payment_method: 'cash' | 'mobile_money' | 'card' | 'bank_transfer';
    reference_number: string | null;
    paid_at: string;
    paid_by: string | null;
}

export interface PurchaseOrder {
    id: string;
    po_number: string;
    status: PurchaseOrderStatus;
    payment_status: 'unpaid' | 'partial' | 'paid';
    order_date: string;
    expected_delivery_date: string | null;
    subtotal: string;
    discount_amount: string;
    tax_amount: string;
    shipping_cost: string;
    other_charges: string;
    total_amount: string;
    paid_amount: string;
    balance_due: string;
    notes: string | null;
    terms: string | null;
    rejection_reason: string | null;
    cancellation_reason: string | null;
    sent_at: string | null;
    approved_at: string | null;
    cancelled_at: string | null;
    closed_at: string | null;
    supplier: {
        id: string;
        name: string;
        email: string | null;
        phone: string | null;
    } | null;
    branch: { id: string; name: string } | null;
    warehouse: { id: string; name: string } | null;
    approved_by: { id: string; name: string } | null;
    items: PurchaseOrderItem[];
    payments: SupplierPayment[];
    created_at: string;
}

export interface GoodsReceivedItem {
    id: string;
    purchase_order_item_id: string;
    product: { id: string; name: string } | null;
    quantity_received: string;
    quantity_damaged: string;
    quantity_rejected: string;
    batch_number: string | null;
    manufactured_date: string | null;
    expiry_date: string | null;
    notes: string | null;
}

export interface GoodsReceivedNote {
    id: string;
    grn_number: string;
    received_at: string;
    notes: string | null;
    purchase_order: {
        id: string;
        po_number: string;
        status: PurchaseOrderStatus;
    } | null;
    warehouse: { id: string; name: string } | null;
    received_by: { id: string; name: string } | null;
    items: GoodsReceivedItem[];
    created_at: string;
}

export interface PurchasingDashboardSummary {
    total_purchase_orders_count: number;
    pending_purchase_orders_count: number;
    completed_orders_count: number;
    pending_deliveries_count: number;
    today_receipts_count: number;
    total_order_value: number;
    purchase_value_this_month: number;
    active_suppliers_count: number;
}

export interface PurchasingDashboardTrend {
    orders: number[];
    completed: number[];
    value: number[];
}

export interface TopSupplier {
    supplier_id: string;
    name: string;
    total_spend: number;
}

export interface RecentPurchaseOrderRow {
    id: string;
    po_number: string;
    supplier_name: string;
    status: PurchaseOrderStatus;
    total_amount: number;
    order_date: string;
}

export interface RecentDeliveryRow {
    id: string;
    grn_number: string;
    po_number: string;
    supplier_name: string;
    received_at: string;
}

export interface SupplierLeadTime {
    supplier_id: string;
    name: string;
    average_lead_time_days: number;
    completed_orders: number;
}
