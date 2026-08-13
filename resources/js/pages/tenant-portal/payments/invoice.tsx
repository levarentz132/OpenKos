import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, Download, Printer } from 'lucide-react';
import { useState } from 'react';
import SubmitPortalPaymentSheet from '@/components/features/payments/submit-portal-payment-sheet';
import { StatusBadge } from '@/components/shared/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { PAYMENT_METHOD_LABELS } from '@/lib/constants/billing';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import { index } from '@/routes/portal/billing';
import { download, print } from '@/routes/portal/billing/invoices';
import type { Invoice, Payment } from '@/types';

type InvoiceLease = {
    reference: string | null;
    unit_name: string | null;
    property_name: string | null;
};

export default function InvoiceDetail({
    invoice,
    lease,
    invoicePdf,
}: {
    invoice: Invoice;
    lease: InvoiceLease;
    invoicePdf: {
        status: 'disabled' | 'pending' | 'available';
    };
}) {
    const [paymentOpen, setPaymentOpen] = useState(false);
    const isPayable = ['pending', 'partial'].includes(invoice.status);
    const payments = invoice.payments ?? [];
    const latestPayment = [...payments].sort(
        (a, b) =>
            new Date(a.payment_date).getTime() -
                new Date(b.payment_date).getTime() || a.id - b.id,
    )[payments.length - 1];
    const invoiceStatus = invoice.display_status ?? invoice.status;
    const primaryContext = getPrimaryContext(invoiceStatus, latestPayment);

    return (
        <div className="workspace-enter flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <Head title={`Invoice ${invoice.reference ?? ''}`} />

            <Link
                href={index()}
                className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            >
                <ChevronLeft className="size-3" />
                Back to billing
            </Link>

            <div className="space-y-6">
                <header>
                    <h1 className="text-xl font-semibold">
                        {invoice.reference ?? 'Invoice'}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {formatPeriod(invoice.period_start, 'id-ID')}
                    </p>
                    <p className="mt-3 text-sm text-muted-foreground">
                        {lease.reference ?? 'Lease'}
                        {lease.unit_name && ` · ${lease.unit_name}`}
                        {lease.property_name && ` · ${lease.property_name}`}
                    </p>
                </header>

                {primaryContext && (
                    <Alert
                        variant={
                            primaryContext.variant === 'destructive'
                                ? 'destructive'
                                : 'default'
                        }
                        className={
                            primaryContext.variant === 'warning'
                                ? 'border-surface-amber-border bg-surface-amber/30'
                                : undefined
                        }
                    >
                        <AlertTitle>{primaryContext.title}</AlertTitle>
                        <AlertDescription>
                            {primaryContext.description}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
                    <div className="space-y-6">
                        <section className="rounded-lg border">
                            <h2 className="border-b px-4 py-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                What this invoice covers
                            </h2>
                            {invoice.line_items &&
                            invoice.line_items.length > 0 ? (
                                <div className="divide-y">
                                    {invoice.line_items.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex items-center justify-between gap-4 px-4 py-3 text-sm"
                                        >
                                            <span>{item.description}</span>
                                            <span className="tabular-nums">
                                                {formatPrice(item.amount)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="px-4 py-6 text-sm text-muted-foreground">
                                    No itemized charges.
                                </p>
                            )}
                        </section>

                        {payments.length > 0 && (
                            <section className="rounded-lg border">
                                <h2 className="border-b px-4 py-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Payment history
                                </h2>
                                <div className="divide-y">
                                    {payments.map((payment) => (
                                        <div
                                            key={payment.id}
                                            className="grid gap-3 px-4 py-3 text-sm sm:grid-cols-[1fr_auto] sm:items-center"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {formatDate(
                                                        payment.payment_date,
                                                    )}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {PAYMENT_METHOD_LABELS[
                                                        payment.payment_method
                                                    ] ?? payment.payment_method}
                                                </p>
                                                {payment.status ===
                                                    'cancelled' &&
                                                    payment.notes && (
                                                        <p className="mt-2 text-xs text-destructive">
                                                            <span className="font-medium">
                                                                Reason:{' '}
                                                            </span>
                                                            {payment.notes}
                                                        </p>
                                                    )}
                                            </div>
                                            <div className="flex items-center justify-between gap-3 sm:justify-end">
                                                <p className="font-medium tabular-nums">
                                                    {formatPrice(
                                                        payment.amount,
                                                    )}
                                                </p>
                                                <StatusBadge
                                                    domain="tenant_payment"
                                                    value={payment.status}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        )}
                    </div>

                    <aside className="order-first rounded-lg border p-5 lg:sticky lg:top-16 lg:order-none">
                        <p className="text-sm text-muted-foreground">
                            Outstanding balance
                        </p>
                        <p className="mt-1 text-2xl font-semibold tabular-nums">
                            {formatPrice(invoice.outstanding ?? '0')}
                        </p>
                        <div className="mt-5 grid gap-4 text-sm">
                            <Detail
                                label="Due date"
                                value={formatDate(invoice.due_date)}
                            />
                            <StatusDetail
                                label="Invoice status"
                                domain="tenant_invoice"
                                value={invoiceStatus}
                            />
                            <div>
                                <p className="text-muted-foreground">
                                    Latest payment
                                </p>
                                <div className="mt-1">
                                    {latestPayment ? (
                                        <>
                                            <StatusBadge
                                                domain="tenant_payment"
                                                value={latestPayment.status}
                                            />
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Submitted{' '}
                                                {formatDate(
                                                    latestPayment.payment_date,
                                                )}
                                            </p>
                                        </>
                                    ) : (
                                        <p className="font-medium">
                                            No payment submitted
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                        <div className="mt-6 grid gap-2">
                            {isPayable && (
                                <Button
                                    className="w-full"
                                    onClick={() => setPaymentOpen(true)}
                                >
                                    Pay invoice
                                </Button>
                            )}
                            {invoicePdf.status === 'available' ? (
                                <Button
                                    asChild
                                    className="w-full"
                                    variant="outline"
                                >
                                    <a href={download.url(invoice)}>
                                        <Download className="size-4" />
                                        Download PDF
                                    </a>
                                </Button>
                            ) : invoicePdf.status === 'pending' ? (
                                <>
                                    <Button
                                        className="w-full"
                                        variant="outline"
                                        disabled
                                    >
                                        PDF pending
                                    </Button>
                                    <p className="text-xs text-muted-foreground">
                                        A queue worker is preparing this PDF.
                                        Refresh this page when it is ready.
                                    </p>
                                </>
                            ) : (
                                <Button
                                    asChild
                                    className="w-full"
                                    variant="outline"
                                >
                                    <a
                                        href={print.url(invoice)}
                                        rel="noreferrer"
                                        target="_blank"
                                    >
                                        <Printer className="size-4" />
                                        Print / Save as PDF
                                    </a>
                                </Button>
                            )}
                        </div>
                    </aside>
                </div>
            </div>

            <SubmitPortalPaymentSheet
                invoice={invoice}
                open={paymentOpen}
                onOpenChange={setPaymentOpen}
            />
        </div>
    );
}

type PrimaryContext = {
    title: string;
    description: string;
    variant: 'warning' | 'destructive' | 'default';
};

function getPrimaryContext(
    invoiceStatus: string,
    latestPayment?: Payment,
): PrimaryContext | null {
    if (latestPayment?.status === 'pending') {
        return {
            title: 'Payment Under Review',
            description:
                'Your payment has been submitted and is awaiting verification. Your invoice will update automatically after verification.',
            variant: 'warning',
        };
    }

    if (latestPayment?.status === 'cancelled') {
        return {
            title: 'Payment Rejected',
            description:
                latestPayment.notes ||
                'Your payment was not accepted. Submit a new payment to keep this invoice on track.',
            variant: 'destructive',
        };
    }

    if (invoiceStatus === 'overdue') {
        return {
            title: 'Invoice Overdue',
            description:
                'This invoice is still outstanding. Submit a payment to bring it up to date.',
            variant: 'destructive',
        };
    }

    if (invoiceStatus === 'pending') {
        return {
            title: 'Payment Due',
            description: 'No payment has been submitted for this invoice yet.',
            variant: 'warning',
        };
    }

    if (invoiceStatus === 'paid') {
        return {
            title: 'Invoice Paid',
            description: 'This invoice has been fully paid.',
            variant: 'default',
        };
    }

    return null;
}

function StatusDetail({
    label,
    domain,
    value,
}: {
    label: string;
    domain: 'tenant_invoice' | 'tenant_payment';
    value: string;
}) {
    return (
        <div>
            <p className="text-muted-foreground">{label}</p>
            <div className="mt-1">
                <StatusBadge domain={domain} value={value} />
            </div>
        </div>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-muted-foreground">{label}</p>
            <p className="mt-1 font-medium tabular-nums">{value}</p>
        </div>
    );
}
