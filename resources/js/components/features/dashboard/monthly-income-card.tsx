import { Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    Banknote,
    BarChart2,
    Calendar,
    Filter,
    Table as TableIcon,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatRupiah } from '@/lib/formatters';
import type { MonthlyIncomeData } from '@/types';

const PROPERTY_COLORS = [
    { bg: 'bg-primary', text: 'text-primary', fill: '#3b82f6' },
    { bg: 'bg-emerald-500', text: 'text-emerald-500', fill: '#10b981' },
    { bg: 'bg-amber-500', text: 'text-amber-500', fill: '#f59e0b' },
    { bg: 'bg-cyan-500', text: 'text-cyan-500', fill: '#06b6d4' },
    { bg: 'bg-purple-500', text: 'text-purple-500', fill: '#a855f7' },
    { bg: 'bg-rose-500', text: 'text-rose-500', fill: '#f43f5e' },
];

export function MonthlyIncomeCard({ data }: { data: MonthlyIncomeData }) {
    const [viewMode, setViewMode] = useState<'chart' | 'table'>('chart');
    const [selectedPropertyId, setSelectedPropertyId] = useState<number | 'all'>('all');
    const [startDate, setStartDate] = useState(data?.start_date || '');
    const [endDate, setEndDate] = useState(data?.end_date || '');
    const [showDatePicker, setShowDatePicker] = useState(false);

    if (!data || !data.months || data.months.length === 0) {
        return null;
    }

    const { months, properties } = data;

    const handleDateFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/dashboard',
            {
                start_date: startDate,
                end_date: endDate,
            },
            { preserveState: true },
        );
    };

    // Aggregate overall total income across all months
    const totalAccumulatedIncome = months.reduce((acc, m) => acc + m.total_income, 0);

    // Maximum income value for SVG scaling
    const maxIncome = Math.max(
        1,
        ...months.map((m) => {
            if (selectedPropertyId === 'all') {
                return m.total_income;
            }
            return m.by_property[selectedPropertyId] || 0;
        }),
    );

    return (
        <section className="mb-10 flex flex-col gap-3">
            <div className="rounded-xl border border-border bg-card p-6 shadow-xs">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="flex size-8 items-center justify-center rounded-lg bg-surface-green/20 text-surface-green-foreground">
                                <Banknote className="size-4" />
                            </span>
                            <div>
                                <h2 className="text-base font-bold text-foreground">
                                    Monthly Income & Occupancy per Property
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Revenue collected and occupancy rate breakdown per property.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {/* Link to Full Reports Page */}
                        <Button
                            variant="outline"
                            size="xs"
                            asChild
                            className="gap-1 bg-card text-xs font-semibold shadow-2xs"
                        >
                            <Link href="/reports">
                                Full Reports Page
                                <ArrowUpRight className="size-3.5" />
                            </Link>
                        </Button>

                        {/* Date Filter Toggle */}
                        <Button
                            variant={showDatePicker ? 'secondary' : 'outline'}
                            size="xs"
                            onClick={() => setShowDatePicker(!showDatePicker)}
                            className="gap-1 text-xs cursor-pointer"
                        >
                            <Calendar className="size-3.5" />
                            Select Date
                        </Button>

                        {/* View Switcher Buttons */}
                        <div className="flex items-center rounded-lg border border-border bg-muted/40 p-1 text-xs">
                            <button
                                type="button"
                                onClick={() => setViewMode('chart')}
                                className={`flex items-center gap-1.5 rounded-md px-2.5 py-1 font-medium transition-all ${
                                    viewMode === 'chart'
                                        ? 'bg-card text-foreground shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                <BarChart2 className="size-3.5" />
                                Chart
                            </button>
                            <button
                                type="button"
                                onClick={() => setViewMode('table')}
                                className={`flex items-center gap-1.5 rounded-md px-2.5 py-1 font-medium transition-all ${
                                    viewMode === 'table'
                                        ? 'bg-card text-foreground shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                <TableIcon className="size-3.5" />
                                Table
                            </button>
                        </div>

                        {/* Property Filter Pills */}
                        {properties.length > 1 && (
                            <div className="flex flex-wrap items-center gap-1">
                                <Button
                                    variant={selectedPropertyId === 'all' ? 'secondary' : 'ghost'}
                                    size="xs"
                                    onClick={() => setSelectedPropertyId('all')}
                                    className="text-xs"
                                >
                                    All ({properties.length})
                                </Button>
                                {properties.slice(0, 3).map((prop) => (
                                    <Button
                                        key={prop.id}
                                        variant={selectedPropertyId === prop.id ? 'secondary' : 'ghost'}
                                        size="xs"
                                        onClick={() => setSelectedPropertyId(prop.id)}
                                        className="text-xs"
                                    >
                                        {prop.name}
                                    </Button>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Optional Expandable Date Picker Bar */}
                {showDatePicker && (
                    <form
                        onSubmit={handleDateFilterSubmit}
                        className="mb-6 flex flex-wrap items-end gap-3 rounded-lg border border-border/80 bg-muted/30 p-3 text-xs"
                    >
                        <div className="space-y-1">
                            <label className="text-muted-foreground font-medium flex items-center gap-1">
                                <Calendar className="size-3" />
                                Start Date
                            </label>
                            <Input
                                type="date"
                                value={startDate}
                                onChange={(e) => setStartDate(e.target.value)}
                                className="h-8 text-xs bg-background"
                            />
                        </div>
                        <div className="space-y-1">
                            <label className="text-muted-foreground font-medium flex items-center gap-1">
                                <Calendar className="size-3" />
                                End Date
                            </label>
                            <Input
                                type="date"
                                value={endDate}
                                onChange={(e) => setEndDate(e.target.value)}
                                className="h-8 text-xs bg-background"
                            />
                        </div>
                        <Button type="submit" size="xs" className="h-8 gap-1 cursor-pointer">
                            <Filter className="size-3" />
                            Apply Date Range
                        </Button>
                    </form>
                )}

                {/* Total Summary Badge Banner */}
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border/60 bg-muted/20 px-4 py-3">
                    <div className="flex items-center gap-2">
                        <TrendingUp className="size-4 text-surface-green-foreground" />
                        <span className="text-xs font-medium text-muted-foreground">
                            Total Income ({data.start_date} to {data.end_date}):
                        </span>
                        <span className="text-sm font-bold text-foreground tabular-nums">
                            {formatRupiah(totalAccumulatedIncome)}
                        </span>
                    </div>
                    {selectedPropertyId !== 'all' && (
                        <Badge variant="outline" className="text-xs">
                            Showing: {properties.find((p) => p.id === selectedPropertyId)?.name}
                        </Badge>
                    )}
                </div>

                {/* Chart View */}
                {viewMode === 'chart' && (
                    <div className="space-y-6">
                        {/* Custom Bar Visualization */}
                        <div className="grid gap-4 sm:grid-cols-6">
                            {months.map((m) => {
                                const displayedAmount =
                                    selectedPropertyId === 'all'
                                        ? m.total_income
                                        : m.by_property[selectedPropertyId] || 0;
                                const heightPercentage =
                                    maxIncome > 0 ? Math.round((displayedAmount / maxIncome) * 100) : 0;

                                return (
                                    <div
                                        key={m.month_key}
                                        className="group relative flex flex-col items-center justify-end rounded-lg border border-border/40 bg-muted/10 p-3 transition-colors hover:border-primary/40 hover:bg-muted/30"
                                    >
                                        {/* Value Label */}
                                        <span className="mb-2 text-xs font-bold text-foreground tabular-nums">
                                            {formatRupiah(displayedAmount)}
                                        </span>

                                        {/* Bar Track & Fill */}
                                        <div className="relative flex h-36 w-full items-end justify-center rounded-md bg-muted/30 p-1">
                                            {selectedPropertyId === 'all' ? (
                                                /* Stacked/Segmented Bar */
                                                <div
                                                    className="flex w-full flex-col-reverse justify-start overflow-hidden rounded-xs transition-all duration-300"
                                                    style={{ height: `${Math.max(4, heightPercentage)}%` }}
                                                >
                                                    {properties.map((prop, idx) => {
                                                        const pAmount = m.by_property[prop.id] || 0;
                                                        if (pAmount <= 0) return null;
                                                        const pPct =
                                                            m.total_income > 0
                                                                ? (pAmount / m.total_income) * 100
                                                                : 0;
                                                        const color =
                                                            PROPERTY_COLORS[idx % PROPERTY_COLORS.length];

                                                        return (
                                                            <div
                                                                key={prop.id}
                                                                className={`${color.bg} w-full transition-all`}
                                                                style={{ height: `${pPct}%` }}
                                                                title={`${prop.name}: ${formatRupiah(pAmount)}`}
                                                            />
                                                        );
                                                    })}
                                                </div>
                                            ) : (
                                                /* Single Property Bar */
                                                <div
                                                    className="w-full rounded-xs bg-primary transition-all duration-300"
                                                    style={{ height: `${Math.max(4, heightPercentage)}%` }}
                                                />
                                            )}
                                        </div>

                                        {/* Month Label */}
                                        <span className="mt-2 text-xs font-semibold text-muted-foreground">
                                            {m.month_name}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Legend */}
                        {properties.length > 0 && selectedPropertyId === 'all' && (
                            <div className="flex flex-wrap items-center justify-center gap-4 border-t border-border/50 pt-4 text-xs">
                                {properties.map((prop, idx) => {
                                    const color = PROPERTY_COLORS[idx % PROPERTY_COLORS.length];
                                    return (
                                        <div key={prop.id} className="flex items-center gap-1.5">
                                            <span className={`size-2.5 rounded-xs ${color.bg}`} />
                                            <span className="font-medium text-muted-foreground">
                                                {prop.name}
                                                {prop.occupancy_rate !== undefined && (
                                                    <span className="font-bold text-foreground ml-1">
                                                        ({prop.occupancy_rate}% occupied)
                                                    </span>
                                                )}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                )}

                {/* Table View */}
                {viewMode === 'table' && (
                    <div className="overflow-x-auto rounded-lg border border-border/80">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-border bg-muted/50 font-semibold text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3">Month</th>
                                    {properties.map((prop) => (
                                        <th key={prop.id} className="px-4 py-3 text-right">
                                            {prop.name}
                                        </th>
                                    ))}
                                    <th className="px-4 py-3 text-right">Total Income</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/60">
                                {months.map((m) => (
                                    <tr key={m.month_key} className="hover:bg-muted/20">
                                        <td className="px-4 py-3 font-semibold text-foreground">
                                            {m.month_name}
                                        </td>
                                        {properties.map((prop) => {
                                            const pAmount = m.by_property[prop.id] || 0;
                                            const occStat = m.occupancy_by_property?.[prop.id];

                                            return (
                                                <td
                                                    key={prop.id}
                                                    className="px-4 py-3 text-right tabular-nums text-muted-foreground"
                                                >
                                                    <div>{pAmount > 0 ? formatRupiah(pAmount) : '—'}</div>
                                                    {occStat && (
                                                        <div className="text-[10px] text-muted-foreground/80 font-medium">
                                                            {occStat.occupancy_rate}% occupied
                                                        </div>
                                                    )}
                                                </td>
                                            );
                                        })}
                                        <td className="px-4 py-3 text-right font-bold text-foreground tabular-nums">
                                            {formatRupiah(m.total_income)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="border-t-2 border-border bg-muted/40 font-bold text-foreground">
                                <tr>
                                    <td className="px-4 py-3">Total</td>
                                    {properties.map((prop) => {
                                        const pTotal = months.reduce(
                                            (acc, m) => acc + (m.by_property[prop.id] || 0),
                                            0,
                                        );
                                        return (
                                            <td key={prop.id} className="px-4 py-3 text-right tabular-nums">
                                                {formatRupiah(pTotal)}
                                            </td>
                                        );
                                    })}
                                    <td className="px-4 py-3 text-right text-primary tabular-nums">
                                        {formatRupiah(totalAccumulatedIncome)}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}
            </div>
        </section>
    );
}
