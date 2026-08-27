import type { InvoiceLineItem, Payment } from './models';

export type PropertyStats = {
    id: number;
    name: string;
    slug: string;
    total_units: number;
    occupied_units: number;
    available_units: number;
    maintenance_units: number;
    unavailable_units: number;
    occupancy_percentage: number;
};

export type Finance = {
    revenue_this_month: number;
    monthly_potential: number;
    outstanding: number;
    collection_rate: number;
};

export type PropertyOccupancyMonthStat = {
    total_units: number;
    occupied_units: number;
    available_units: number;
    occupancy_rate: number;
};

export type PaidBillingDetail = {
    id: number;
    amount: number;
    payment_date: string;
    payment_method: string;
    reference_number: string | null;
    invoice_id: number;
    invoice_reference: string;
    lease_id: number;
    tenant_name: string;
    unit_name: string;
};

export type AdditionalIncomeItem = {
    id: number;
    title: string;
    category: string;
    amount: number;
    income_date: string;
    notes?: string | null;
};

export type MonthlyPropertyIncomeEntry = {
    month_key: string;
    month_name: string;
    total_income: number;
    property_income?: number;
    additional_income_total?: number;
    additional_incomes?: AdditionalIncomeItem[];
    by_property: Record<number, number>;
    occupancy_by_property?: Record<number, PropertyOccupancyMonthStat>;
    payments_by_property?: Record<number, PaidBillingDetail[]>;
};

export type PropertyIncomeInfo = {
    id: number;
    name: string;
    slug?: string;
    total_units?: number;
    occupied_units?: number;
    occupancy_rate?: number;
};

export type MonthlyIncomeData = {
    start_date?: string;
    end_date?: string;
    months: MonthlyPropertyIncomeEntry[];
    properties: PropertyIncomeInfo[];
};

export type PropertyOccupancyReview = {
    id: number;
    name: string;
    slug: string;
    total_units: number;
    occupied_units: number;
    available_units: number;
    maintenance_units: number;
    unavailable_units: number;
    occupancy_rate: number;
    status_label: string;
};

export type OccupancyReviewData = {
    total_units: number;
    occupied_units: number;
    available_units: number;
    maintenance_units: number;
    unavailable_units: number;
    occupancy_rate: number;
    vacancy_rate: number;
    maintenance_rate: number;
    unavailable_rate: number;
    property_reviews: PropertyOccupancyReview[];
    insights: {
        highest_occupancy: { name: string; rate: number } | null;
        lowest_occupancy: { name: string; rate: number } | null;
        vacant_units_count: number;
    };
};

export type Stats = {
    total_units: number;
    occupied_units: number;
    available_units: number;
    maintenance_units: number;
    unavailable_units: number;
    occupancy_percentage: number;
    properties: PropertyStats[];
};

export type RentDashboardEntry = {
    id: number;
    tenant_name: string;
    unit_name: string;
    property_name: string;
    rent_due_day: number;
    days_overdue: number | null;
    rent_amount: string;
    rent_status: 'paid' | 'overdue' | 'due_today' | 'due_soon';
};

export type BillingStats = {
    overdue: { count: number; amount: number };
    due_today: number;
    due_soon: number;
    outstanding_balance: number;
    collection_rate: number;
};

export type NeedsAttentionInvoice = {
    id: number;
    lease_id: number;
    lease_reference: string | null;
    primary_tenant_id: number | null;
    tenant_name: string;
    unit_name: string;
    property_name: string;
    reference: string;
    period_start: string;
    period_end: string;
    due_date: string;
    deposit_amount: string;
    room_price: string;
    rent_amount: string;
    total: string;
    amount_paid: string;
    outstanding: string;
    days_overdue: number | null;
    urgency: 'overdue' | 'due_today' | 'due_tomorrow' | 'due_soon' | 'upcoming';
    status: string;
    pending_payment_review_count?: number;
    line_items?: InvoiceLineItem[];
    payments?: Payment[];
};

export type RecentPaymentEntry = {
    id: number;
    amount: string;
    payment_date: string;
    payment_method: string;
    status: string;
    tenant_name: string;
    invoice_id: number;
    invoice_reference: string;
    lease_id: number | null;
};

export type RecentReminderEntry = {
    id: number;
    lease_id: number;
    tenant_name: string;
    reminder_type: string;
    channel: string;
    scheduled_for: string;
    sent_at: string | null;
    overdue_days: number | null;
};

export type CollectionProgress = {
    paid_this_month: number;
    outstanding_this_month: number;
    monthly_potential: number;
    collection_rate: number;
};

export type AttentionItem = {
    label: string;
    count: number;
    amount?: number;
    href: string;
};

export type RecentActivityEntry = {
    id: number;
    description: string;
    created_at: string;
    subject_type: string | null;
    subject_id?: number | null;
    actor_name?: string | null;
    action_url?: string | null;
};

export type AttentionData = {
    overdue_invoices: { count: number; amount: number };
    due_today: number;
    open_maintenance: number;
    leases_ending_soon: number;
    pending_payment_verification: number;
};
