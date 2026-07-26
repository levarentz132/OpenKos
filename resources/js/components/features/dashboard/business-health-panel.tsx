import { AlertCircle, Building2, TrendingUp } from 'lucide-react';
import { formatRupiah } from '@/lib/formatters';
import type { Finance } from '@/types';

export function BusinessHealthPanel({ finance }: { finance: Finance }) {
    return (
        <section className="mb-10 flex flex-col gap-3">
            <div className="rounded-xl border border-border bg-card p-6 shadow-xs">
                <h2 className="mb-4 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                    Business Health
                </h2>
                <div className="grid gap-6 divide-y divide-border sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                    <div className="pt-2 first:pl-0 sm:px-4 sm:pt-0">
                        <div className="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <TrendingUp className="size-3.5 text-surface-green-foreground" />
                            <span>Revenue This Month</span>
                        </div>
                        <p className="text-2xl font-bold text-surface-green-foreground tabular-nums sm:text-3xl">
                            {formatRupiah(finance.revenue_this_month)}
                        </p>
                    </div>
                    <div className="pt-4 sm:px-4 sm:pt-0">
                        <div className="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <Building2 className="size-3.5 text-muted-foreground" />
                            <span>Monthly Potential</span>
                        </div>
                        <p className="text-2xl font-bold text-foreground tabular-nums sm:text-3xl">
                            {formatRupiah(finance.monthly_potential)}
                        </p>
                    </div>
                    <div className="pt-4 sm:px-4 sm:pt-0">
                        <div className="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <AlertCircle className="size-3.5 text-surface-red-foreground" />
                            <span>Outstanding</span>
                        </div>
                        <p className="text-2xl font-bold text-surface-red-foreground tabular-nums sm:text-3xl">
                            {formatRupiah(finance.outstanding)}
                        </p>
                    </div>
                    <div className="pt-4 sm:px-4 sm:pt-0">
                        <div className="mb-1.5 flex items-center justify-between text-xs font-medium text-muted-foreground">
                            <span>Collection Rate</span>
                        </div>
                        <p className="text-2xl font-bold text-primary tabular-nums sm:text-3xl">
                            {finance.collection_rate}%
                        </p>
                        <div className="mt-2.5 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-primary transition-all duration-300"
                                style={{
                                    width: `${finance.collection_rate}%`,
                                }}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
