import { useState } from 'react';
import { Banknote, BarChart2, Building, Table as TableIcon, TrendingUp } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

    if (!data || !data.months || data.months.length === 0) {
        return null;
    }

    const { months, properties } = data;

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
                                    Monthly Income per Property
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Revenue collected over the past 6 months across properties.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
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

                {/* Total Summary Badge Banner */}
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border/60 bg-muted/20 px-4 py-3">
                    <div className="flex items-center gap-2">
                        <TrendingUp className="size-4 text-surface-green-foreground" />
                        <span className="text-xs font-medium text-muted-foreground">
                            Total Income (Past 6 Months):
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
                                                <div className="flex w-full flex-col-reverse justify-start overflow-hidden rounded-sm transition-all duration-300" style={{ height: `${Math.max(4, heightPercentage)}%` }}>
                                                    {properties.map((prop, idx) => {
                                                        const pAmount = m.by_property[prop.id] || 0;
                                                        if (pAmount <= 0) return null;
                                                        const pPct = m.total_income > 0 ? (pAmount / m.total_income) * 100 : 0;
                                                        const color = PROPERTY_COLORS[idx % PROPERTY_COLORS.length];

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
                                                    className="w-full rounded-sm bg-primary transition-all duration-300"
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
                                            return (
                                                <td
                                                    key={prop.id}
                                                    className="px-4 py-3 text-right tabular-nums text-muted-foreground"
                                                >
                                                    {pAmount > 0 ? formatRupiah(pAmount) : '—'}
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
