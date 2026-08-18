export interface Permission {
    id: string;
    module: string;
    name: string;
    slug: string;
    description: string | null;
}

export interface Role {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    is_system: boolean;
    users_count?: number;
    permissions?: Permission[];
    created_at?: string;
}

export interface SubscriptionPlan {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    /** 3, 6 or 12. Null on the retired tier plans. */
    duration_months: number | null;
    /** Total for the whole term, not a monthly rate. */
    price: string | null;
    price_monthly: string;
    price_quarterly: string;
    price_yearly: string;
    trial_days: number;
    features: string[] | null;
}

export interface Subscription {
    id: string;
    status:
        | 'pending_payment'
        | 'trialing'
        | 'active'
        | 'past_due'
        | 'canceled'
        | 'expired'
        | 'suspended';
    billing_cycle: 'monthly' | 'quarterly' | 'yearly' | null;
    trial_ends_at: string | null;
    current_period_start: string | null;
    current_period_end: string | null;
    plan?: SubscriptionPlan;
}

export interface Business {
    id: string;
    name: string;
    slug: string;
    business_type: string;
    email: string;
    phone: string | null;
    country: string;
    currency: string;
    timezone: string;
    address: string | null;
    city: string | null;
    logo_path: string | null;
    status: 'trial' | 'active' | 'suspended' | 'expired';
    trial_ends_at: string | null;
}

export interface User {
    id: string;
    name: string;
    /** Optional: predates the column, so existing accounts hold null until edited. */
    username?: string | null;
    email: string;
    avatar_url: string | null;
    phone?: string | null;
    email_verified_at?: string;
    business_id?: string | null;
    role_id?: string | null;
}

export interface Branch {
    id: string;
    name: string;
    code: string;
    is_main: boolean;
    phone: string | null;
    address: string | null;
    city: string | null;
    status: 'active' | 'inactive';
    warehouses_count?: number;
    employees_count?: number;
}

export interface Warehouse {
    id: string;
    branch_id: string;
    branch_name?: string;
    name: string;
    code: string;
    is_default: boolean;
    address: string | null;
    status: 'active' | 'inactive';
}

export interface Employee {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    status: 'invited' | 'active' | 'suspended';
    /** First assigned role, for screens that display only one. */
    role: { id: string; name: string } | null;
    /** Every assigned role — permissions are the union of these. */
    roles: { id: string; name: string }[];
    branch: { id: string; name: string } | null;
    is_owner: boolean;
    last_login_at: string | null;
    created_at: string;
}

export interface BusinessHealth {
    score: number;
    status: 'Excellent' | 'Good' | 'Needs Attention' | 'Critical';
    signals: Array<{ label: string; deduction: number }>;
    recommendations: string[];
}

export interface BusinessPulse {
    revenue_growth: {
        percent: number | null;
        this_week: number;
        previous_week: number;
    };
    profit_trend: {
        percent: number | null;
        this_week: number;
        previous_week: number;
    };
    inventory_health: {
        score: number;
        low_stock_count: number;
        expiring_soon_count: number;
    };
    cash_flow: {
        net_cash: number;
        accounts_payable: number;
        status: 'healthy' | 'tight';
    } | null;
    debt_status: {
        outstanding_debts: number;
        accounts_payable: number;
        net_position: number;
    } | null;
    customer_growth: {
        new_customers_this_month: number;
        total_customers: number;
        vip_customers: number;
    } | null;
}

export interface RecentActivityItem {
    id: string;
    actor_name: string;
    module: string | null;
    action: string;
    auditable_type: string | null;
    created_at: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        business: Business | null;
        subscription: Subscription | null;
        role: Role | null;
        roles: Array<Pick<Role, 'id' | 'name' | 'slug'>>;
        permissions: string[];
        /**
         * Dashboard sections the Super Admin has switched OFF for this
         * business. A negative list on purpose — the UI hides only what
         * it is told to, rather than everything it doesn't recognise.
         */
        hiddenModules: string[];
    };
    /**
     * The server's flash bag, scalars only.
     *
     * Open-ended because controllers flash arbitrary keys — `status` and
     * `success` drive the toasts, and individual pages read their own
     * one-off keys (`broadcast_count`, `plain_text_token`, …).
     */
    flash: Record<string, string | number | boolean | null>;
    impersonating: boolean;
    platformAuth: {
        user: {
            id: string;
            name: string;
            email: string;
            avatar_url: string | null;
        } | null;
        /**
         * Which of the two admin surfaces this account lands on, and the
         * screens the Filament panel does not have. Sourced from
         * AdminSurface::ONLY_ON so the switcher's warning cannot drift
         * from what is actually missing.
         */
        surface: {
            current: 'admin' | 'platform';
            missingFromFilament: string[];
        } | null;
        /**
         * Live platform health for the top bar, from
         * PlatformStatusBadgeService — the same service and cache entry
         * the Filament panel reads, so the two surfaces cannot report
         * different states at the same moment.
         */
        status: {
            color: 'success' | 'warning' | 'danger';
            label: 'Operational' | 'Degraded' | 'Down';
            title: string;
            database: boolean;
            redis: boolean;
            healthLabel: string;
        } | null;
    };
    availableCurrencies: Array<{
        code: string;
        name: string;
        symbol: string;
        exchange_rate_to_base: string;
        is_base: boolean;
    }>;
};
