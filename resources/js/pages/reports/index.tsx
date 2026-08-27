import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    Award,
    Banknote,
    BarChart3,
    Building2,
    Calendar,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    CreditCard,
    Download,
    Filter,
    Home,
    PieChart,
    Receipt,
    RefreshCw,
    Table as TableIcon,
    TrendingUp,
    User,
    Zap,
} from 'lucide-react';
import React, { Fragment, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { N8nSyncDialog } from '@/components/features/n8n-sync-dialog';
import { Input } from '@/components/ui/input';
import { formatRupiah } from '@/lib/formatters';
import type {
    MonthlyIncomeData,
    OccupancyReviewData,
    PaidBillingDetail,
    PropertyIncomeInfo,
} from '@/types';

export default function ReportsIndex({
    income_report,
    occupancy_review,
    properties,
    filters,
}: {
    income_report: MonthlyIncomeData;
    occupancy_review: OccupancyReviewData;
    properties: PropertyIncomeInfo[];
    filters: {
        start_date: string;
        end_date: string;
        property_id: string | number;
    };
}) {
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');
    const [selectedPropertyId, setSelectedPropertyId] = useState<number | 'all'>(
        filters.property_id ? (filters.property_id === 'all' ? 'all' : Number(filters.property_id)) : 'all',
    );
    const [activeTab, setActiveTab] = useState<'matrix' | 'charts'>('matrix');
    const [expandedRow, setExpandedRow] = useState<string | null>(null);

    const handleFilterSubmit = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        router.get(
            '/reports',
            {
                start_date: startDate,
                end_date: endDate,
                property_id: selectedPropertyId,
            },
            { preserveState: true },
        );
    };

    const handlePresetRange = (monthsCount: number) => {
        const end = new Date();
        const start = new Date();
        start.setMonth(start.getMonth() - (monthsCount - 1));
        start.setDate(1);

        const startStr = start.toISOString().split('T')[0];
        const endStr = end.toISOString().split('T')[0];

        setStartDate(startStr);
        setEndDate(endStr);

        router.get(
            '/reports',
            {
                start_date: startStr,
                end_date: endStr,
                property_id: selectedPropertyId,
            },
            { preserveState: true },
        );
    };

    const handleExportCSV = () => {
        if (!income_report?.months) return;

        const rows: string[][] = [
            ['Month', 'Property', 'Revenue Collected (Rp)', 'Occupancy Rate (%)', 'Occupied Units', 'Total Units'],
        ];

        income_report.months.forEach((m) => {
            income_report.properties.forEach((prop) => {
                if (selectedPropertyId !== 'all' && prop.id !== selectedPropertyId) return;

                const income = m.by_property[prop.id] || 0;
                const occStat = m.occupancy_by_property?.[prop.id];

                rows.push([
                    m.month_name,
                    `"${prop.name}"`,
                    income.toString(),
                    occStat ? `${occStat.occupancy_rate}%` : '—',
                    occStat ? occStat.occupied_units.toString() : '—',
                    occStat ? occStat.total_units.toString() : '—',
                ]);
            });
        });

        const csvContent = 'data:text/csv;charset=utf-8,' + rows.map((e) => e.join(',')).join('\n');
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', `Property_Performance_Report_${startDate}_to_${endDate}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    const totalIncomeAll = income_report?.months?.reduce((acc, m) => acc + m.total_income, 0) || 0;

    return (
        <>
            <Head title="Property Performance & Revenue Reports" />
            <div className="flex h-full flex-1 flex-col overflow-x-auto p-4 md:p-6 lg:p-8">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <BarChart3 className="size-5" />
                            </span>
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                                    Property Performance & Revenue Report
                                </h1>
                                <p className="mt-0.5 text-xs text-muted-foreground sm:text-sm">
                                    Track monthly income collections and occupancy rates per property over custom date ranges.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <N8nSyncDialog buttonSize="sm" />
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleExportCSV}
                            className="cursor-pointer gap-2 bg-card shadow-xs"
                        >
                            <Download className="size-4 text-muted-foreground" />
                            Export CSV
                        </Button>
                        <Button
                            variant="default"
                            size="sm"
                            asChild
                            className="gap-2 shadow-xs"
                        >
                            <Link href="/dashboard">
                                Overview Dashboard
                                <ArrowUpRight className="size-4" />
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Filter Control Bar */}
                <section className="mb-8 rounded-xl border border-border bg-card p-5 shadow-xs">
                    <form onSubmit={handleFilterSubmit} className="flex flex-col gap-4">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border/50 pb-4">
                            <div className="flex items-center gap-2 font-semibold text-foreground text-xs uppercase tracking-wider">
                                <Filter className="size-3.5 text-primary" />
                                <span>Report Date & Property Filters</span>
                            </div>

                            {/* Date Presets */}
                            <div className="flex flex-wrap items-center gap-1 text-xs">
                                <span className="text-muted-foreground mr-1">Quick Presets:</span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="xs"
                                    onClick={() => handlePresetRange(3)}
                                    className="text-xs"
                                >
                                    Last 3 Months
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="xs"
                                    onClick={() => handlePresetRange(6)}
                                    className="text-xs"
                                >
                                    Last 6 Months
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="xs"
                                    onClick={() => handlePresetRange(12)}
                                    className="text-xs"
                                >
                                    Last 12 Months
                                </Button>
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-12 sm:items-end">
                            {/* Start Date */}
                            <div className="space-y-1 sm:col-span-3">
                                <label className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                    <Calendar className="size-3.5" />
                                    Start Date
                                </label>
                                <Input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="h-9 text-xs"
                                />
                            </div>

                            {/* End Date */}
                            <div className="space-y-1 sm:col-span-3">
                                <label className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                    <Calendar className="size-3.5" />
                                    End Date
                                </label>
                                <Input
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="h-9 text-xs"
                                />
                            </div>

                            {/* Property Selector */}
                            <div className="space-y-1 sm:col-span-4">
                                <label className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                    <Building2 className="size-3.5" />
                                    Property
                                </label>
                                <select
                                    value={selectedPropertyId}
                                    onChange={(e) => setSelectedPropertyId(e.target.value === 'all' ? 'all' : Number(e.target.value))}
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-2xs transition-colors focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    <option value="all">All Properties ({properties.length})</option>
                                    {properties.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Submit Filter Button */}
                            <div className="sm:col-span-2">
                                <Button type="submit" size="sm" className="w-full gap-1.5 cursor-pointer">
                                    <RefreshCw className="size-3.5" />
                                    Apply Filter
                                </Button>
                            </div>
                        </div>
                    </form>
                </section>

                {/* Summary Stat Cards */}
                <section className="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-xl border border-border bg-card p-5 shadow-2xs">
                        <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground font-medium">
                            <span>Total Period Revenue</span>
                            <Banknote className="size-4 text-surface-green-foreground" />
                        </div>
                        <p className="text-2xl font-bold text-foreground tabular-nums">
                            {formatRupiah(totalIncomeAll)}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Collected over selected range
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-5 shadow-2xs">
                        <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground font-medium">
                            <span>Portfolio Occupancy</span>
                            <PieChart className="size-4 text-primary" />
                        </div>
                        <p className="text-2xl font-bold text-primary tabular-nums">
                            {occupancy_review?.occupancy_rate || 0}%
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {occupancy_review?.occupied_units || 0} of {occupancy_review?.total_units || 0} total units occupied
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-5 shadow-2xs">
                        <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground font-medium">
                            <span>Available Units</span>
                            <Home className="size-4 text-surface-green-foreground" />
                        </div>
                        <p className="text-2xl font-bold text-surface-green-foreground tabular-nums">
                            {occupancy_review?.available_units || 0}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Vacant & ready for lease
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-5 shadow-2xs">
                        <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground font-medium">
                            <span>Units Under Repair</span>
                            <Award className="size-4 text-surface-amber-foreground" />
                        </div>
                        <p className="text-2xl font-bold text-surface-amber-foreground tabular-nums">
                            {occupancy_review?.maintenance_units || 0}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Maintenance tickets in progress
                        </p>
                    </div>
                </section>

                {/* View Switcher Tabs */}
                <div className="mb-4 flex items-center justify-between">
                    <div className="flex items-center rounded-lg border border-border bg-muted/40 p-1 text-xs">
                        <button
                            type="button"
                            onClick={() => setActiveTab('matrix')}
                            className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 font-medium transition-all ${
                                activeTab === 'matrix'
                                    ? 'bg-card text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            <TableIcon className="size-3.5" />
                            Monthly Matrix Table
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('charts')}
                            className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 font-medium transition-all ${
                                activeTab === 'charts'
                                    ? 'bg-card text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            <TrendingUp className="size-3.5" />
                            Property Revenue & Occupancy Trends
                        </button>
                    </div>

                    <span className="text-xs text-muted-foreground font-medium">
                        Period: {startDate} to {endDate}
                    </span>
                </div>

                {/* Tab 1: Detailed Matrix Table */}
                {activeTab === 'matrix' && (
                    <div className="rounded-xl border border-border bg-card shadow-2xs overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="border-b border-border bg-muted/50 font-semibold text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3.5">Month</th>
                                        <th className="px-4 py-3.5">Property</th>
                                        <th className="px-4 py-3.5 text-right">Monthly Revenue</th>
                                        <th className="px-4 py-3.5 text-center">Occupancy Rate</th>
                                        <th className="px-4 py-3.5 text-center">Units Breakdown</th>
                                        <th className="px-4 py-3.5 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {income_report?.months?.map((m) => {
                                        return income_report.properties.map((prop) => {
                                            if (selectedPropertyId !== 'all' && prop.id !== selectedPropertyId) {
                                                return null;
                                            }

                                            const income = m.by_property[prop.id] || 0;
                                            const occStat = m.occupancy_by_property?.[prop.id];
                                            const occRate = occStat?.occupancy_rate ?? 0;
                                            const payments = m.payments_by_property?.[prop.id] || [];
                                            const rowKey = `${m.month_key}-${prop.id}`;
                                            const isExpanded = expandedRow === rowKey;

                                            return (
                                                <Fragment key={rowKey}>
                                                    <tr className={`transition-colors ${isExpanded ? 'bg-primary/5' : 'hover:bg-muted/20'}`}>
                                                        <td className="px-4 py-3 font-semibold text-foreground">
                                                            {m.month_name}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <Link
                                                                href={`/properties/${prop.slug || ''}`}
                                                                className="flex items-center gap-1.5 font-semibold text-foreground hover:text-primary transition-colors"
                                                            >
                                                                <Building2 className="size-3.5 text-muted-foreground" />
                                                                <span>{prop.name}</span>
                                                            </Link>
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            <div className="flex flex-col items-end">
                                                                <span className="font-bold text-foreground tabular-nums">
                                                                    {income > 0 ? formatRupiah(income) : '—'}
                                                                </span>
                                                                {payments.length > 0 && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => setExpandedRow(isExpanded ? null : rowKey)}
                                                                        className="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:underline cursor-pointer bg-primary/10 hover:bg-primary/20 px-2 py-0.5 rounded-full transition-all"
                                                                    >
                                                                        <Receipt className="size-3" />
                                                                        <span>{payments.length} paid {payments.length === 1 ? 'billing' : 'billings'}</span>
                                                                        {isExpanded ? <ChevronUp className="size-3" /> : <ChevronDown className="size-3" />}
                                                                    </button>
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3 text-center">
                                                            <div className="flex items-center justify-center gap-2">
                                                                <div className="h-1.5 w-16 overflow-hidden rounded-full bg-muted">
                                                                    <div
                                                                        className="h-full bg-primary rounded-full transition-all"
                                                                        style={{ width: `${occRate}%` }}
                                                                    />
                                                                </div>
                                                                <span className="font-bold text-foreground tabular-nums">
                                                                    {occRate}%
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3 text-center text-muted-foreground tabular-nums">
                                                            {occStat ? (
                                                                <span>
                                                                    <strong className="text-foreground">{occStat.occupied_units}</strong> / {occStat.total_units} units occupied
                                                                </span>
                                                            ) : (
                                                                '—'
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-center">
                                                            <Badge
                                                                variant={
                                                                    occRate >= 90
                                                                        ? 'default'
                                                                        : occRate >= 70
                                                                          ? 'secondary'
                                                                          : 'outline'
                                                                }
                                                                className="text-xs"
                                                            >
                                                                {occRate >= 90
                                                                    ? 'Full'
                                                                    : occRate >= 70
                                                                      ? 'High'
                                                                      : occRate >= 40
                                                                        ? 'Moderate'
                                                                        : 'Low'}
                                                            </Badge>
                                                        </td>
                                                    </tr>

                                                    {/* Expanded Billings Breakdown Row */}
                                                    {isExpanded && (
                                                        <tr className="bg-muted/30">
                                                            <td colSpan={6} className="p-4">
                                                                <div className="rounded-xl border border-primary/20 bg-card p-4 shadow-sm">
                                                                    <div className="mb-3 flex items-center justify-between">
                                                                        <div className="flex items-center gap-2">
                                                                            <div className="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                                                <Receipt className="size-4" />
                                                                            </div>
                                                                            <div>
                                                                                <h4 className="text-xs font-bold text-foreground">
                                                                                    Paid Billings Breakdown — {prop.name} ({m.month_name})
                                                                                </h4>
                                                                                <p className="text-[11px] text-muted-foreground">
                                                                                    Individual payments received for this location during {m.month_name}
                                                                                </p>
                                                                            </div>
                                                                        </div>
                                                                        <div className="flex items-center gap-3">
                                                                            <Badge variant="secondary" className="text-xs font-semibold">
                                                                                {payments.length} {payments.length === 1 ? 'Payment Record' : 'Payment Records'}
                                                                            </Badge>
                                                                            <span className="text-xs font-bold text-surface-green-foreground tabular-nums">
                                                                                Accumulated Total: {formatRupiah(income)}
                                                                            </span>
                                                                        </div>
                                                                    </div>

                                                                    <div className="overflow-x-auto rounded-lg border border-border/80">
                                                                        <table className="w-full text-left text-xs">
                                                                            <thead className="border-b border-border bg-muted/70 text-[11px] font-semibold text-muted-foreground uppercase">
                                                                                <tr>
                                                                                    <th className="px-3 py-2">Invoice #</th>
                                                                                    <th className="px-3 py-2">Tenant / Payer</th>
                                                                                    <th className="px-3 py-2">Room / Unit</th>
                                                                                    <th className="px-3 py-2">Payment Date</th>
                                                                                    <th className="px-3 py-2">Payment Method</th>
                                                                                    <th className="px-3 py-2 text-right">Amount Paid</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody className="divide-y divide-border/40 bg-background">
                                                                                {payments.map((p) => (
                                                                                    <tr key={p.id} className="hover:bg-muted/20">
                                                                                        <td className="px-3 py-2 font-mono font-medium text-foreground">
                                                                                            <Link
                                                                                                href={`/leases/${p.lease_id}`}
                                                                                                className="inline-flex items-center gap-1 text-primary hover:underline font-semibold"
                                                                                            >
                                                                                                <Receipt className="size-3" />
                                                                                                <span>{p.invoice_reference}</span>
                                                                                            </Link>
                                                                                        </td>
                                                                                        <td className="px-3 py-2 font-medium text-foreground">
                                                                                            <div className="flex items-center gap-1.5">
                                                                                                <User className="size-3.5 text-muted-foreground" />
                                                                                                <span>{p.tenant_name}</span>
                                                                                            </div>
                                                                                        </td>
                                                                                        <td className="px-3 py-2 font-medium text-foreground">
                                                                                            {p.unit_name}
                                                                                        </td>
                                                                                        <td className="px-3 py-2 text-muted-foreground">
                                                                                            {p.payment_date}
                                                                                        </td>
                                                                                        <td className="px-3 py-2">
                                                                                            <Badge variant="outline" className="text-[10px] capitalize">
                                                                                                {p.payment_method}
                                                                                            </Badge>
                                                                                        </td>
                                                                                        <td className="px-3 py-2 text-right font-bold text-surface-green-foreground tabular-nums">
                                                                                            {formatRupiah(p.amount)}
                                                                                        </td>
                                                                                    </tr>
                                                                                ))}
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    )}
                                                </Fragment>
                                            );
                                        });
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Tab 2: Visual Charts Trend Overview */}
                {activeTab === 'charts' && (
                    <div className="space-y-6">
                        <div className="rounded-xl border border-border bg-card p-6 shadow-xs">
                            <h3 className="mb-4 text-sm font-bold text-foreground">
                                Monthly Revenue Comparison per Property
                            </h3>
                            <div className="grid gap-4 sm:grid-cols-6">
                                {income_report?.months?.map((m) => (
                                    <div key={m.month_key} className="flex flex-col items-center justify-end rounded-lg border border-border/40 bg-muted/10 p-3">
                                        <span className="mb-2 text-xs font-bold text-foreground tabular-nums">
                                            {formatRupiah(m.total_income)}
                                        </span>
                                        <div className="flex h-32 w-full items-end justify-center rounded-md bg-muted/30 p-1">
                                            <div
                                                className="w-full rounded-xs bg-primary transition-all duration-300"
                                                style={{
                                                    height: `${Math.max(4, Math.min(100, Math.round((m.total_income / (totalIncomeAll || 1)) * 100)))}%`,
                                                }}
                                            />
                                        </div>
                                        <span className="mt-2 text-xs font-semibold text-muted-foreground">
                                            {m.month_name}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Reports',
            href: '/reports',
        },
    ],
};
