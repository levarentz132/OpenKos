import { Link, router } from '@inertiajs/react';
import { Award, CheckCircle2, Home, PieChart, Sparkles, Wrench, Zap } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { N8nSyncDialog } from '@/components/features/n8n-sync-dialog';
import { Button } from '@/components/ui/button';
import type { OccupancyReviewData } from '@/types';

export function OccupancyReviewCard({ data }: { data: OccupancyReviewData }) {
    if (!data) {
        return null;
    }

    const {
        total_units,
        occupied_units,
        available_units,
        maintenance_units,
        unavailable_units,
        occupancy_rate,
        vacancy_rate,
        maintenance_rate,
        unavailable_rate,
        property_reviews,
        insights,
    } = data;

    return (
        <section className="mb-10 flex flex-col gap-3">
            <div className="rounded-xl border border-border bg-card p-6 shadow-xs">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2">
                        <span className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <PieChart className="size-4" />
                        </span>
                        <div>
                            <h2 className="text-base font-bold text-foreground">
                                Occupancy Rate Review
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Portfolio-wide capacity utilization and property occupancy status.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <N8nSyncDialog buttonSize="xs" />
                        <Badge
                            variant={occupancy_rate >= 75 ? 'default' : 'outline'}
                            className="w-fit text-xs font-semibold"
                        >
                            {occupancy_rate}% Total Portfolio Occupied
                        </Badge>
                    </div>
                </div>

                {/* Overall Portfolio Capacity Gauge Bar */}
                <div className="mb-6 rounded-lg border border-border/60 bg-muted/20 p-4">
                    <div className="mb-2 flex items-center justify-between text-xs font-semibold">
                        <span className="text-foreground">Portfolio Capacity Distribution</span>
                        <span className="text-muted-foreground tabular-nums">
                            {occupied_units} of {total_units} units occupied ({occupancy_rate}%)
                        </span>
                    </div>

                    {/* Stacked Progress Bar */}
                    <div className="flex h-3.5 w-full overflow-hidden rounded-full bg-muted">
                        {occupancy_rate > 0 && (
                            <div
                                className="h-full bg-surface-blue-foreground transition-all duration-300"
                                style={{ width: `${occupancy_rate}%` }}
                                title={`Occupied: ${occupied_units} units (${occupancy_rate}%)`}
                            />
                        )}
                        {vacancy_rate > 0 && (
                            <div
                                className="h-full bg-surface-green-foreground transition-all duration-300"
                                style={{ width: `${vacancy_rate}%` }}
                                title={`Available: ${available_units} units (${vacancy_rate}%)`}
                            />
                        )}
                        {maintenance_rate > 0 && (
                            <div
                                className="h-full bg-surface-amber-foreground transition-all duration-300"
                                style={{ width: `${maintenance_rate}%` }}
                                title={`Maintenance: ${maintenance_units} units (${maintenance_rate}%)`}
                            />
                        )}
                        {unavailable_rate > 0 && (
                            <div
                                className="h-full bg-muted-foreground/40 transition-all duration-300"
                                style={{ width: `${unavailable_rate}%` }}
                                title={`Unavailable: ${unavailable_units} units (${unavailable_rate}%)`}
                            />
                        )}
                    </div>

                    {/* Legend */}
                    <div className="mt-3 flex flex-wrap items-center gap-4 text-xs font-medium text-muted-foreground">
                        <span className="flex items-center gap-1.5">
                            <span className="size-2.5 rounded-full bg-surface-blue-foreground" />
                            <span>Occupied:</span>
                            <strong className="font-bold text-foreground tabular-nums">{occupied_units}</strong>
                        </span>
                        <span className="flex items-center gap-1.5">
                            <span className="size-2.5 rounded-full bg-surface-green-foreground" />
                            <span>Vacant/Available:</span>
                            <strong className="font-bold text-foreground tabular-nums">{available_units}</strong>
                        </span>
                        <span className="flex items-center gap-1.5">
                            <span className="size-2.5 rounded-full bg-surface-amber-foreground" />
                            <span>Maintenance:</span>
                            <strong className="font-bold text-foreground tabular-nums">{maintenance_units}</strong>
                        </span>
                        {unavailable_units > 0 && (
                            <span className="flex items-center gap-1.5">
                                <span className="size-2.5 rounded-full bg-muted-foreground/40" />
                                <span>Unavailable:</span>
                                <strong className="font-bold text-foreground tabular-nums">{unavailable_units}</strong>
                            </span>
                        )}
                    </div>
                </div>

                {/* Highlights & Insights Callout Chips */}
                <div className="mb-6 grid gap-3 sm:grid-cols-3">
                    {insights.highest_occupancy && (
                        <div className="flex items-center gap-3 rounded-lg border border-border/40 bg-card p-3 shadow-2xs">
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-surface-green/20 text-surface-green-foreground">
                                <Award className="size-4" />
                            </span>
                            <div className="truncate text-xs">
                                <span className="text-muted-foreground">Top Occupancy Property</span>
                                <p className="truncate font-semibold text-foreground">
                                    {insights.highest_occupancy.name} ({insights.highest_occupancy.rate}%)
                                </p>
                            </div>
                        </div>
                    )}

                    <div className="flex items-center gap-3 rounded-lg border border-border/40 bg-card p-3 shadow-2xs">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-surface-blue/20 text-surface-blue-foreground">
                            <Sparkles className="size-4" />
                        </span>
                        <div className="text-xs">
                            <span className="text-muted-foreground">Leasing Opportunities</span>
                            <p className="font-semibold text-foreground">
                                {available_units} ready unit{available_units === 1 ? '' : 's'} available
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3 rounded-lg border border-border/40 bg-card p-3 shadow-2xs">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-surface-amber/20 text-surface-amber-foreground">
                            <Wrench className="size-4" />
                        </span>
                        <div className="text-xs">
                            <span className="text-muted-foreground">Maintenance Status</span>
                            <p className="font-semibold text-foreground">
                                {maintenance_units} unit{maintenance_units === 1 ? '' : 's'} under repair
                            </p>
                        </div>
                    </div>
                </div>

                {/* Per-Property Review Table */}
                {property_reviews.length > 0 && (
                    <div className="overflow-x-auto rounded-lg border border-border/80">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-border bg-muted/50 font-semibold text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3">Property</th>
                                    <th className="px-4 py-3">Units Breakdown</th>
                                    <th className="px-4 py-3">Occupancy Bar</th>
                                    <th className="px-4 py-3 text-center">Status</th>
                                    <th className="px-4 py-3 text-right">Occupancy Rate</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/60">
                                {property_reviews.map((prop) => (
                                    <tr key={prop.id} className="hover:bg-muted/20">
                                        <td className="px-4 py-3">
                                            <Link
                                                href={`/properties/${prop.slug}`}
                                                className="flex items-center gap-2 font-semibold text-foreground transition-colors hover:text-primary"
                                            >
                                                <Home className="size-3.5 text-muted-foreground" />
                                                <span>{prop.name}</span>
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            <span className="tabular-nums">
                                                <strong className="text-foreground">{prop.occupied_units}</strong> occupied,{' '}
                                                <strong>{prop.available_units}</strong> vacant
                                                {prop.maintenance_units > 0 && (
                                                    <span className="text-surface-amber-foreground">
                                                        , {prop.maintenance_units} repair
                                                    </span>
                                                )}
                                            </span>
                                        </td>
                                        <td className="w-44 px-4 py-3">
                                            <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full bg-primary transition-all duration-300"
                                                    style={{ width: `${prop.occupancy_rate}%` }}
                                                />
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <Badge
                                                variant={
                                                    prop.occupancy_rate >= 90
                                                        ? 'default'
                                                        : prop.occupancy_rate >= 70
                                                          ? 'secondary'
                                                          : 'outline'
                                                }
                                                className="text-xs"
                                            >
                                                {prop.status_label}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-right font-bold text-foreground tabular-nums">
                                            {prop.occupancy_rate}%
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </section>
    );
}
