import { Link } from '@inertiajs/react';
import {
    Activity,
    BadgeCheck,
    FileClock,
    FileText,
    MessageSquare,
    Receipt,
    UserPlus,
    Wrench,
} from 'lucide-react';
import { formatRelativeTime } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { RecentActivityEntry } from '@/types';

export function parseActivity(entry: RecentActivityEntry) {
    const desc = entry.description.toLowerCase();
    const type = (entry.subject_type || '').toLowerCase();

    if (desc.includes('payment') || type.includes('payment')) {
        if (
            desc.includes('verify') ||
            desc.includes('confirm') ||
            desc.includes('approve')
        ) {
            return {
                title: 'Payment Verified',
                icon: BadgeCheck,
                iconClass:
                    'bg-surface-green-icon text-surface-green-foreground',
                category: 'Payments',
            };
        }

        return {
            title: 'Payment Recorded',
            icon: Receipt,
            iconClass: 'bg-surface-blue-icon text-surface-blue-foreground',
            category: 'Payments',
        };
    }

    if (desc.includes('invoice') || type.includes('invoice')) {
        return {
            title: 'Invoice Updated',
            icon: FileText,
            iconClass: 'bg-surface-blue-icon text-surface-blue-foreground',
            category: 'Billing',
        };
    }

    if (
        desc.includes('maintenance') ||
        desc.includes('ticket') ||
        type.includes('ticket') ||
        type.includes('maintenance')
    ) {
        return {
            title: 'Maintenance Event',
            icon: Wrench,
            iconClass: 'bg-surface-amber-icon text-surface-amber-foreground',
            category: 'Maintenance',
        };
    }

    if (desc.includes('reminder') || desc.includes('notification')) {
        return {
            title: 'Reminder Sent',
            icon: MessageSquare,
            iconClass: 'bg-surface-blue-icon text-surface-blue-foreground',
            category: 'Reminders',
        };
    }

    if (desc.includes('lease') || type.includes('lease')) {
        return {
            title: 'Lease Event',
            icon: FileClock,
            iconClass: 'bg-surface-purple-icon text-surface-purple-foreground',
            category: 'Leases',
        };
    }

    if (desc.includes('tenant') || type.includes('tenant')) {
        return {
            title: 'Tenant Event',
            icon: UserPlus,
            iconClass: 'bg-surface-purple-icon text-surface-purple-foreground',
            category: 'Tenants',
        };
    }

    return {
        title: entry.description,
        icon: Activity,
        iconClass: 'bg-muted text-muted-foreground',
        category: 'General',
    };
}

export function getActivitySummaryChips(recentActivity: RecentActivityEntry[]) {
    const counts: Record<string, number> = {};

    for (const entry of recentActivity) {
        const cat = parseActivity(entry).category;
        counts[cat] = (counts[cat] || 0) + 1;
    }

    return Object.entries(counts).map(([label, count]) => ({ label, count }));
}

export function ActivityFeedItem({ entry }: { entry: RecentActivityEntry }) {
    const activity = parseActivity(entry);
    const Icon = activity.icon;
    const actor = entry.actor_name || 'System';

    return (
        <div className="flex items-start gap-3 text-sm">
            <div
                className={cn(
                    'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg',
                    activity.iconClass,
                )}
            >
                <Icon className="size-3.5" />
            </div>
            <div className="min-w-0 flex-1">
                <div className="flex items-baseline justify-between gap-2">
                    <p className="text-xs font-semibold text-foreground">
                        {activity.title}
                    </p>
                    <span className="shrink-0 text-xs font-medium text-muted-foreground tabular-nums">
                        {formatRelativeTime(entry.created_at)}
                    </span>
                </div>
                <div className="mt-0.5 flex items-center justify-between gap-2">
                    <p className="truncate text-xs text-muted-foreground">
                        {entry.description}{' '}
                        <span className="text-muted-foreground/70">
                            • by {actor}
                        </span>
                    </p>
                    {entry.action_url && (
                        <Link
                            href={entry.action_url}
                            className="inline-flex shrink-0 items-center gap-0.5 text-xs font-semibold text-primary transition-colors hover:underline"
                        >
                            View →
                        </Link>
                    )}
                </div>
            </div>
        </div>
    );
}
