export interface Category {
    id: string;
    parent_id: string | null;
    name: string;
    slug: string;
    description: string | null;
    image_path: string | null;
    status: 'active' | 'inactive';
    sort_order: number;
    products_count?: number;
    children?: Category[];
}

export interface Brand {
    id: string;
    name: string;
    slug: string;
    logo_path: string | null;
    description: string | null;
    status: 'active' | 'inactive';
    products_count?: number;
}

export interface Unit {
    id: string;
    base_unit_id: string | null;
    base_unit_name?: string | null;
    name: string;
    symbol: string;
    conversion_factor: string;
    status: 'active' | 'inactive';
}

export interface Tag {
    id: string;
    name: string;
    slug: string;
}

export interface ProductCollection {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    image_path: string | null;
    status: 'active' | 'inactive';
    sort_order: number;
    products_count?: number;
}

export interface AttributeValue {
    id: string;
    value: string;
    sort_order: number;
}

export interface Attribute {
    id: string;
    name: string;
    slug: string;
    input_type: 'select' | 'text' | 'number';
    is_variant_attribute: boolean;
    status: 'active' | 'inactive';
    values?: AttributeValue[];
}

export interface Supplier {
    id: string;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    status: 'active' | 'inactive';
    products_count?: number;
}

export interface ProductVariant {
    id: string;
    sku: string;
    barcode: string | null;
    attributes: Record<string, string> | null;
    cost_price: string | null;
    selling_price: string | null;
    wholesale_price: string | null;
    status: 'active' | 'inactive';
}

export interface InventoryStockRow {
    id: string;
    warehouse_id: string;
    warehouse_name?: string;
    product_variant_id: string | null;
    quantity: string;
    reserved_quantity: string;
    available_quantity: string;
    minimum_stock: string | null;
    maximum_stock: string | null;
    reorder_level: string | null;
    average_cost: string;
    is_low_stock: boolean;
    is_out_of_stock: boolean;
    last_counted_at: string | null;
}

export interface Product {
    id: string;
    name: string;
    slug: string;
    sku: string;
    barcode: string | null;
    custom_code: string | null;
    description: string | null;
    product_type: 'simple' | 'variable' | 'service';
    track_stock: boolean;
    has_expiry: boolean;
    has_batch: boolean;
    has_serial: boolean;
    cost_price: string;
    purchase_price: string;
    selling_price: string;
    wholesale_price: string | null;
    minimum_price: string | null;
    tax_rate: string;
    last_purchase_price: string | null;
    last_purchase_at: string | null;
    minimum_stock: string;
    maximum_stock: string | null;
    reorder_level: string;
    weight: string | null;
    weight_unit: string | null;
    dimensions: Record<string, number> | null;
    status: 'active' | 'inactive' | 'archived';
    visibility: 'visible' | 'hidden';
    category?: Category | null;
    brand?: Brand | null;
    unit?: Unit | null;
    default_supplier?: Supplier | null;
    variants?: ProductVariant[];
    tags?: Tag[];
    collections?: ProductCollection[];
    suppliers?: Supplier[];
    inventories?: InventoryStockRow[];
    total_quantity?: string;
    created_at: string;
    updated_at: string;
}

export interface StockMovement {
    id: string;
    product_id: string;
    product_name?: string;
    warehouse_name?: string;
    type: string;
    direction: 'in' | 'out';
    quantity: string;
    quantity_before: string;
    quantity_after: string;
    unit_cost: string | null;
    total_cost: string | null;
    notes: string | null;
    created_at: string;
}

export interface StockAdjustmentItem {
    id: string;
    product_id: string;
    product_name?: string;
    direction: 'in' | 'out';
    quantity: string;
    unit_cost: string | null;
}

export interface StockAdjustment {
    id: string;
    adjustment_number: string;
    warehouse_name?: string;
    reason: 'damage' | 'theft' | 'expiry' | 'correction' | 'count' | 'other';
    notes: string | null;
    status: 'draft' | 'completed';
    completed_at: string | null;
    items?: StockAdjustmentItem[];
    created_at: string;
}

export interface StockTransferItem {
    id: string;
    product_id: string;
    product_name?: string;
    quantity: string;
    unit_cost: string | null;
}

export interface StockTransfer {
    id: string;
    transfer_number: string;
    from_warehouse_name?: string;
    to_warehouse_name?: string;
    status: 'pending' | 'in_transit' | 'completed' | 'cancelled';
    notes: string | null;
    dispatched_at: string | null;
    received_at: string | null;
    items?: StockTransferItem[];
    created_at: string;
}

export interface InventoryCountItem {
    id: string;
    product_id: string;
    product_name?: string;
    expected_quantity: string;
    counted_quantity: string | null;
    variance: string | null;
    notes: string | null;
}

export interface InventoryCount {
    id: string;
    count_number: string;
    warehouse_name?: string;
    status: 'draft' | 'in_progress' | 'completed';
    started_at: string | null;
    completed_at: string | null;
    items?: InventoryCountItem[];
}

export interface InventoryDashboardSummary {
    total_products: number;
    low_stock_count: number;
    out_of_stock_count: number;
    expired_count: number;
    expiring_soon_count: number;
    inventory_value: number;
    today_stock_movements: number;
    recent_products: Array<{
        id: string;
        name: string;
        sku: string;
        selling_price: string;
        created_at: string;
    }>;
    health_score: number;
    stock_movement_trend: Array<{
        date: string;
        inbound: number;
        outbound: number;
    }>;
    stock_health_breakdown: {
        healthy: number;
        low_stock: number;
        out_of_stock: number;
        expiring_soon: number;
        expired: number;
    };
}
