import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { StatusBadge } from '@/components/shared/status-badge';
import { formatDate } from '@/lib/formatters';

type Ticket = {
    id: number;
    reference: string;
    title: string;
    description: string | null;
    status: string;
    priority: string;
    created_at: string;
    property_name: string | null;
    unit_name: string | null;
    location: string | null;
    assignee_name: string | null;
    resolution_notes: string | null;
};

type Props = {
    ticket: Ticket;
};

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value}</span>
        </div>
    );
}

export default function ShowTicket({ ticket }: Props) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4">
            <Head title={`Ticket ${ticket.reference}`} />
            
            <Link
                href="/portal/maintenance-tickets"
                className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            >
                <ChevronLeft className="size-3" />
                Back to maintenance list
            </Link>

            <div className="space-y-6">
                <div>
                    <h1 className="text-xl font-semibold">{ticket.title}</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Ticket {ticket.reference} · Created {formatDate(ticket.created_at)}
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="md:col-span-2 space-y-6">
                        <section className="rounded-lg border p-4 space-y-2">
                            <h2 className="text-sm font-semibold">Description</h2>
                            <p className="text-sm text-muted-foreground whitespace-pre-wrap">
                                {ticket.description || 'No description provided.'}
                            </p>
                        </section>

                        {ticket.resolution_notes && (
                            <section className="rounded-lg border p-4 space-y-2 bg-accent/20">
                                <h2 className="text-sm font-semibold">Resolution Notes</h2>
                                <p className="text-sm text-muted-foreground whitespace-pre-wrap">
                                    {ticket.resolution_notes}
                                </p>
                            </section>
                        )}
                    </div>

                    <div className="space-y-4">
                        <section className="rounded-lg border p-4 divide-y text-sm">
                            <Detail
                                label="Status"
                                value={ticket.status.charAt(0).toUpperCase() + ticket.status.slice(1).replace('_', ' ')}
                            />
                            <Detail
                                label="Priority"
                                value={ticket.priority.charAt(0).toUpperCase() + ticket.priority.slice(1)}
                            />
                            <Detail
                                label="Property"
                                value={ticket.property_name || '—'}
                            />
                            {ticket.unit_name ? (
                                <Detail
                                    label="Location"
                                    value={`Unit ${ticket.unit_name}`}
                                />
                            ) : (
                                <Detail
                                    label="Location"
                                    value={ticket.location ? `Common Area (${ticket.location})` : 'Common Area'}
                                />
                            )}
                            <Detail
                                label="Assignee"
                                value={ticket.assignee_name || 'Unassigned'}
                            />
                        </section>
                    </div>
                </div>
            </div>
        </div>
    );
}
