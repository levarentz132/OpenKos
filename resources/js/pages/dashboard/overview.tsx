import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpRight,
    Banknote,
    Building2,
    CalendarClock,
    CheckCircle2,
    ChevronDown,
    Clock,
    FileText,
    ShoppingCart,
    UserCheck,
    UserPlus,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import {
    ActivityFeedItem,
    BusinessHealthPanel,
    getActivitySummaryChips,
    MonthlyIncomeCard,
    OccupancyReviewCard,
    OperationalBriefingCard,
    PropertyFormSheet,
    PropertyOverviewCard,
    TenantFormSheet,
    TicketFormSheet,
} from '@/components/features';
import { MetricCard } from '@/components/shared/metric-card';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatRupiah } from '@/lib/formatters';
import { dashboard } from '@/routes';
import type {
    AttentionData,
    DashboardBookingOrder,
    Finance,
    MaintenanceProperty,
    MaintenanceUnit,
    MonthlyIncomeData,
    OccupancyReviewData,
    PropertyStats,
    RecentActivityEntry,
    Stats,
} from '@/types';

export default function Overview({
    booking_orders = [],
    attention,
    finance,
    monthly_income,
    occupancy_review,
    stats,
    recent_activity,
    properties,
    units,
}: {
    booking_orders?: DashboardBookingOrder[];
    attention: AttentionData;
    finance: Finance;
    monthly_income: MonthlyIncomeData;
    occupancy_review: OccupancyReviewData;
    stats: Stats;
    recent_activity: RecentActivityEntry[];
    properties: MaintenanceProperty[];
    units: MaintenanceUnit[];
}) {
    const [tenantSheetOpen, setTenantSheetOpen] = useState(false);
    const [propertySheetOpen, setPropertySheetOpen] = useState(false);
    const [ticketSheetOpen, setTicketSheetOpen] = useState(false);

    const activitySummaryChips = getActivitySummaryChips(recent_activity);

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col overflow-x-auto p-4 md:p-6 lg:p-8">
                {/* 1. Page Header with Aligned Quick Actions */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                            Dashboard
                        </h1>
                        <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
                            Monitor billing, occupancy, and property operations.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="default"
                            size="sm"
                            onClick={() => setTenantSheetOpen(true)}
                            className="cursor-pointer gap-2 shadow-xs"
                        >
                            <UserPlus className="size-4" />
                            Add Tenant
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            className="gap-2 bg-card shadow-xs"
                        >
                            <Link href="/dashboard/rent">
                                <Banknote className="size-4 text-muted-foreground" />
                                Collect Rent
                            </Link>
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="cursor-pointer gap-1.5 bg-card shadow-xs"
                                >
                                    More
                                    <ChevronDown className="size-3.5 text-muted-foreground" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    onClick={() => setTicketSheetOpen(true)}
                                >
                                    <Wrench className="mr-2 size-4 text-muted-foreground" />
                                    Report Maintenance
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() => setPropertySheetOpen(true)}
                                >
                                    <Building2 className="mr-2 size-4 text-muted-foreground" />
                                    Add Property
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href="/tenants">
                                        <UserCheck className="mr-2 size-4 text-muted-foreground" />
                                        Assign Tenant
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                {/* 2. Visual Hero — Operational Briefing Callout Card */}
                <OperationalBriefingCard attention={attention} />

                {/* 3. Today's Attention Metrics */}
                <section className="mb-10 flex flex-col gap-3">
                    <h2 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        Today&apos;s Attention
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <MetricCard
                            label="Overdue Invoices"
                            value={attention.overdue_invoices.count}
                            subtext={
                                attention.overdue_invoices.amount > 0
                                    ? formatRupiah(
                                          attention.overdue_invoices.amount,
                                      )
                                    : undefined
                            }
                            variant="red"
                            emphasis="attention"
                            icon={AlertTriangle}
                        />
                        <MetricCard
                            label="Due Today"
                            value={attention.due_today}
                            variant="amber"
                            emphasis="subtle"
                            icon={CalendarClock}
                        />
                        <MetricCard
                            label="Open Maintenance"
                            value={attention.open_maintenance}
                            variant="amber"
                            emphasis="subtle"
                            icon={Wrench}
                        />
                        <MetricCard
                            label="Leases Ending Soon"
                            value={attention.leases_ending_soon}
                            variant="blue"
                            emphasis="subtle"
                            icon={FileText}
                        />
                        <MetricCard
                            label="Pending Review"
                            value={attention.pending_payment_verification}
                            variant="purple"
                            emphasis="subtle"
                            icon={Clock}
                        />
                    </div>
                </section>

                {/* 4. Business Health Neutral Panel */}
                <BusinessHealthPanel finance={finance} />

                {/* 5. Monthly Income Breakdown per Property */}
                <MonthlyIncomeCard data={monthly_income} />

                {/* 6. Occupancy Rate Review & Portfolio Breakdown */}
                <OccupancyReviewCard data={occupancy_review} />

                {/* 6.5 Online Booking Cart Orders */}
                <section className="flex flex-col gap-3">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <ShoppingCart className="h-4 w-4 text-primary" />
                            <h2 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Pesanan Keranjang Online (Cart Orders)
                            </h2>
                        </div>
                        <span className="text-xs font-medium text-muted-foreground tabular-nums">
                            {booking_orders?.length || 0} pesanan
                        </span>
                    </div>

                    <div className="overflow-hidden rounded-xl border border-border bg-card shadow-2xs">
                        {booking_orders && booking_orders.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead>
                                        <tr className="border-b border-border/70 bg-muted/30 text-muted-foreground">
                                            <th className="px-4 py-3 font-medium">Referensi</th>
                                            <th className="px-4 py-3 font-medium">Pemesan (Tamu)</th>
                                            <th className="px-4 py-3 font-medium">Kamar & Properti</th>
                                            <th className="px-4 py-3 font-medium">Nominal</th>
                                            <th className="px-4 py-3 font-medium">Waktu</th>
                                            <th className="px-4 py-3 font-medium">Status Cart</th>
                                            <th className="px-4 py-3 text-right font-medium">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/40">
                                        {booking_orders.map((order) => {
                                            const isPaid = order.status === 'paid';
                                            const isPending = order.status === 'pending';
                                            const isConflict = order.status === 'payment_conflict';

                                            return (
                                                <tr key={order.id} className="transition-colors hover:bg-muted/40">
                                                    <td className="px-4 py-3 font-mono font-semibold text-foreground">
                                                        {order.reference}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="font-medium text-foreground">{order.guest_name}</div>
                                                        <div className="text-[11px] text-muted-foreground">{order.guest_phone}</div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="font-medium text-foreground">{order.unit_name}</div>
                                                        <div className="text-[11px] text-muted-foreground">{order.property_name}</div>
                                                    </td>
                                                    <td className="px-4 py-3 font-semibold text-foreground tabular-nums">
                                                        {formatRupiah(order.amount)}
                                                    </td>
                                                    <td className="px-4 py-3 text-muted-foreground tabular-nums">
                                                        {order.paid_at || order.created_at}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {isPaid && (
                                                            <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                                                <CheckCircle2 className="h-3 w-3" />
                                                                Lunas (Paid)
                                                            </span>
                                                        )}
                                                        {isPending && (
                                                            <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                                                                <Clock className="h-3 w-3" />
                                                                Menunggu Bayar
                                                            </span>
                                                        )}
                                                        {isConflict && (
                                                            <span className="inline-flex items-center gap-1.5 rounded-full border border-rose-500/30 bg-rose-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-rose-600 dark:text-rose-400">
                                                                <AlertTriangle className="h-3 w-3" />
                                                                Konflik Pembayaran
                                                            </span>
                                                        )}
                                                        {!isPaid && !isPending && !isConflict && (
                                                            <span className="inline-flex items-center rounded-full border border-border bg-muted px-2.5 py-0.5 text-[11px] font-medium text-muted-foreground uppercase">
                                                                {order.status}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        {order.lease_id ? (
                                                            <Link
                                                                href={`/leases/${order.lease_id}`}
                                                                className="inline-flex items-center gap-1 text-xs font-semibold text-primary transition-colors hover:underline"
                                                            >
                                                                Lihat Sewa #{order.lease_id}
                                                                <ArrowUpRight className="h-3.5 w-3.5" />
                                                            </Link>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground">-</span>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="py-8 text-center text-xs text-muted-foreground">
                                Belum ada pesanan booking online melalui keranjang.
                            </div>
                        )}
                    </div>
                </section>

                {/* 7. Lower Dashboard: Operational Workspace (Two-Column Layout) */}
                <div className="grid gap-8 lg:grid-cols-12">
                    {/* Left Column: Property Overview (~65% / lg:col-span-7) */}
                    <section className="flex flex-col gap-3 lg:col-span-7">
                        <div className="flex items-center justify-between">
                            <h2 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Property Overview
                            </h2>
                            {stats.properties.length > 0 && (
                                <Link
                                    href="/properties"
                                    className="inline-flex items-center gap-1 text-xs font-medium text-primary transition-colors hover:underline"
                                >
                                    View All Properties (
                                    {stats.properties.length}) →
                                </Link>
                            )}
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {stats.properties
                                .slice(0, 6)
                                .map((property: PropertyStats) => (
                                    <PropertyOverviewCard
                                        key={property.id}
                                        property={property}
                                    />
                                ))}
                        </div>
                    </section>

                    {/* Right Column: Recent Activity Timeline (~35% / lg:col-span-5) */}
                    <section className="flex flex-col gap-3 lg:col-span-5">
                        <div className="flex items-center justify-between">
                            <h2 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Recent Activity
                            </h2>
                            <span className="text-xs font-medium text-muted-foreground tabular-nums">
                                {recent_activity.length} events
                            </span>
                        </div>

                        <div className="rounded-xl border border-border bg-card p-4 shadow-2xs">
                            {/* Category Chips Summary Bar */}
                            {activitySummaryChips.length > 0 && (
                                <div className="mb-4 flex flex-wrap items-center gap-1.5 border-b border-border/50 pb-3">
                                    {activitySummaryChips.map((chip) => (
                                        <span
                                            key={chip.label}
                                            className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                        >
                                            <span>{chip.label}</span>
                                            <span className="font-bold text-foreground tabular-nums">
                                                {chip.count}
                                            </span>
                                        </span>
                                    ))}
                                </div>
                            )}

                            {/* Operational Feed Items */}
                            {recent_activity.length > 0 ? (
                                <div className="space-y-3.5">
                                    {recent_activity.map((entry) => (
                                        <ActivityFeedItem
                                            key={entry.id}
                                            entry={entry}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <p className="py-4 text-center text-xs text-muted-foreground">
                                    No recent activity recorded.
                                </p>
                            )}
                        </div>
                    </section>
                </div>
            </div>

            <TenantFormSheet
                open={tenantSheetOpen}
                onOpenChange={setTenantSheetOpen}
            />
            <PropertyFormSheet
                open={propertySheetOpen}
                onOpenChange={setPropertySheetOpen}
            />
            <TicketFormSheet
                open={ticketSheetOpen}
                onOpenChange={setTicketSheetOpen}
                properties={properties}
                units={units}
            />
        </>
    );
}

Overview.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
