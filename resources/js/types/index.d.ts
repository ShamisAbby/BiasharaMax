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
    price_monthly: string;
    price_quarterly: string;
    price_yearly: string;
    trial_days: number;
    features: string[];
}

export interface Subscription {
    id: string;
    status: 'trialing' | 'active' | 'past_due' | 'canceled' | 'expired';
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
    email: string;
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
    role: { id: string; name: string } | null;
    branch: { id: string; name: string } | null;
    is_owner: boolean;
    last_login_at: string | null;
    created_at: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        business: Business | null;
        role: Role | null;
        permissions: string[];
    };
};
