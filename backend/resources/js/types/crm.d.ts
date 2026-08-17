export interface CustomerGroup {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    is_vip: boolean;
    discount_percentage: string;
    customers_count?: number;
    created_at: string;
}

export interface CustomerTag {
    id: string;
    name: string;
    slug: string;
    color: string | null;
    customers_count?: number;
    created_at: string;
}

export interface CustomerNote {
    id: string;
    body: string;
    author: { id: string; name: string } | null;
    created_at: string;
}

export type LoyaltyTransactionType = 'earn' | 'redeem' | 'adjustment';

export interface CustomerLoyaltyTransaction {
    id: string;
    type: LoyaltyTransactionType;
    points: number;
    balance_after: number;
    notes: string | null;
    created_at: string;
}

export interface CustomerCrmProfile {
    id: string;
    name: string;
    phone: string | null;
    email: string | null;
    customer_type: 'cash' | 'credit';
    current_balance: string;
    credit_limit: string;
    loyalty_points: number;
    is_active: boolean;
    created_at: string;
    group: { id: string; name: string; is_vip: boolean } | null;
    loyalty_tier: { id: string; name: string } | null;
    tags: { id: string; name: string; color: string | null }[];
    lifetime_value: number;
    sales_count: number;
}

export interface LoyaltyTier {
    id: string;
    name: string;
    slug: string;
    minimum_spend: string;
    sort_order: number;
    benefits_description: string | null;
    customers_count?: number;
}

export interface LoyaltyReward {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    points_cost: number;
    stock_quantity: number | null;
    is_active: boolean;
    in_stock: boolean;
    redemptions_count?: number;
}

export interface LoyaltyDashboardSummary {
    total_members: number;
    active_members: number;
    vip_customers: number;
    points_issued: number;
    points_redeemed: number;
    reward_redemptions_count: number;
    points_outstanding: number;
}

export interface TopLoyalCustomer {
    customer_id: string;
    name: string;
    loyalty_points: number;
    tier: string | null;
}

export interface LoyaltyTierDistributionPoint {
    tier: string;
    customers_count: number;
}

export type FeedbackType = 'rating' | 'review' | 'complaint';
export type FeedbackStatus = 'open' | 'pending' | 'resolved' | 'closed';

export interface CustomerFeedbackReply {
    id: string;
    body: string;
    author: { id: string; name: string } | null;
    created_at: string;
}

export interface CustomerFeedback {
    id: string;
    type: FeedbackType;
    rating: number | null;
    subject: string | null;
    body: string;
    status: FeedbackStatus;
    resolved_at: string | null;
    created_at: string;
    customer: { id: string; name: string } | null;
    assigned_to: { id: string; name: string } | null;
    replies: CustomerFeedbackReply[];
}

export interface FeedbackDashboardSummary {
    total_feedback: number;
    open_count: number;
    pending_count: number;
    resolved_count: number;
    complaints_this_month: number;
    average_rating: number;
}

export type CampaignStatus = 'draft' | 'sending' | 'sent' | 'failed';

export interface CampaignSegmentFilters {
    tag_ids?: string[];
    loyalty_tier_id?: string | null;
    debt_status?: 'with_debt' | 'no_debt' | null;
    inactive_days?: number | null;
}

export interface MarketingCampaign {
    id: string;
    name: string;
    subject: string;
    body: string;
    status: CampaignStatus;
    segment_filters: CampaignSegmentFilters | null;
    audience_count: number;
    sent_count: number;
    failed_count: number;
    sent_at: string | null;
    created_at: string;
}

export interface CampaignRecipientRow {
    id: string;
    email: string;
    customer_name: string | null;
    status: 'pending' | 'sent' | 'failed';
    error_message: string | null;
}

export interface CrmDashboardSummary {
    total_customers: number;
    active_customers: number;
    new_customers_this_month: number;
    vip_customers: number;
    outstanding_debts: number;
    total_loyalty_points: number;
}

export interface TopCustomer {
    customer_id: string;
    name: string;
    lifetime_value: number;
}

export interface NewCustomersTrendPoint {
    label: string;
    count: number;
}
