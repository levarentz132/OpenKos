import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, Download, Printer } from 'lucide-react';
import { useState } from 'react';
import { PaymentDetailSheet } from '@/components/features';
import { DocumentPreview } from '@/components/shared';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { PAYMENT_METHOD_LABELS } from '@/lib/constants/billing';
import { formatDate, formatPeriod, formatPrice } from '@/lib/formatters';
import invoiceRoutes from '@/routes/leases/workspace/invoices';
import paymentRoutes from '@/routes/payments';
import type { Invoice, Payment, PaymentProof, WorkspaceLease } from '@/types';

export default function InvoiceDetail({
    lease,
    invoice,
    invoicePdf,
}: {
    lease: WorkspaceLease;
    invoice: Invoice;
    invoicePdf: {
        status: 'disabled' | 'pending' | 'available';
    };
}) {
    const { auth } = usePage<{ auth: { permissions: string[] } }>().props;
    const [verifyingId, setVerifyingId] = useState<number | null>(null);
    const [selectedPaymentId, setSelectedPaymentId] = useState<number | null>(
        null,
    );
    const [previewProof, setPreviewProof] = useState<{
        src: string;
        mimeType: string;
        name: string;
    } | null>(null);
    const canVerify = auth.permissions.includes('payments.verify');
    const selectedPayment = (
        invoice.payments?.find((payment) => payment.id === selectedPaymentId)
            ? {
                  ...invoice.payments.find(
                      (payment) => payment.id === selectedPaymentId,
                  )!,
                  invoice: {
                      id: invoice.id,
                      reference: invoice.reference,
                      period_start: invoice.period_start,
                      period_end: invoice.period_end,
                      status: invoice.status,
                  },
              }
            : null
    ) as Payment | null;
    const detailOpen = selectedPaymentId !== null && selectedPayment !== null;

    function handlePreview(payment: Payment, proof: PaymentProof) {
        setPreviewProof({
            src: paymentRoutes.proof.url([payment, proof]),
            mimeType: proof.mime_type,
            name: proof.original_name,
        });
    }

    function handleVerify(payment: Payment, action: 'confirm' | 'reject') {
        setVerifyingId(payment.id);
        router.post(
            paymentRoutes.verify.url(payment),
            { action },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setVerifyingId(null),
            },
        );
    }

    return (
        <div className="workspace-enter flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <Head title={`Invoice ${invoice.reference} — Lease #${lease.id}`} />

            <Link
                href={`/leases/${lease.id}/invoices`}
                className="mb-2 inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            >
                <ChevronLeft className="size-3" />
                Back to invoices
            </Link>

            <div className="flex-1 space-y-6">
                {/* Summary card */}
                <div className="rounded-lg border p-6">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-semibold">
                                {invoice.reference ?? 'Invoice'}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {formatPeriod(invoice.period_start, 'id-ID')}
                            </p>
                        </div>
                        <div className="flex flex-col items-end gap-3">
                            <StatusBadge
                                domain="invoice"
                                value={invoice.display_status ?? invoice.status}
                            />
                            {invoicePdf.status === 'available' ? (
                                <Button asChild size="sm" variant="outline">
                                    <a
                                        href={invoiceRoutes.download.url([
                                            lease,
                                            invoice,
                                        ])}
                                    >
                                        <Download className="size-4" />
                                        Download PDF
                                    </a>
                                </Button>
                            ) : invoicePdf.status === 'pending' ? (
                                <Button disabled size="sm" variant="outline">
                                    PDF pending
                                </Button>
                            ) : (
                                <Button asChild size="sm" variant="outline">
                                    <a
                                        href={invoiceRoutes.print.url([
                                            lease,
                                            invoice,
                                        ])}
                                        rel="noreferrer"
                                        target="_blank"
                                    >
                                        <Printer className="size-4" />
                                        Print / Save as PDF
                                    </a>
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="mt-6 grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <p className="text-muted-foreground">Total</p>
                            <p className="mt-1 font-medium tabular-nums">
                                {formatPrice(invoice.total)}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Paid</p>
                            <p className="mt-1 font-medium tabular-nums">
                                {formatPrice(invoice.amount_paid)}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Outstanding</p>
                            <p className="mt-1 font-medium tabular-nums">
                                {formatPrice(invoice.outstanding ?? '0')}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">
                                Billing period
                            </p>
                            <p className="mt-1 tabular-nums">
                                {formatDate(invoice.period_start)} —{' '}
                                {formatDate(invoice.period_end)}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Due date</p>
                            <p className="mt-1 tabular-nums">
                                {formatDate(invoice.due_date)}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Line items */}
                {invoice.line_items && invoice.line_items.length > 0 && (
                    <div className="rounded-lg border">
                        <div className="border-b px-4 py-3">
                            <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                Line Items
                            </h3>
                        </div>
                        <div className="divide-y">
                            {invoice.line_items.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-center justify-between px-4 py-3 text-sm"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {item.description}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {item.type}
                                        </p>
                                    </div>
                                    <p className="tabular-nums">
                                        {formatPrice(item.amount)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Activity timeline */}
                <section className="rounded-lg border">
                    <div className="border-b px-4 py-3">
                        <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                            Activity timeline
                        </h3>
                    </div>
                    {invoice.payments && invoice.payments.length > 0 ? (
                        <div className="divide-y">
                            {invoice.payments.map((payment) => (
                                <div
                                    key={payment.id}
                                    className="flex cursor-pointer gap-3 px-4 py-3 text-sm hover:bg-muted/30"
                                    onClick={() => {
                                        setSelectedPaymentId(payment.id);
                                    }}
                                >
                                    <div className="flex w-3 shrink-0 justify-center">
                                        <div className="mt-1.5 size-2 rounded-full bg-primary" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="font-medium">
                                                {payment.status === 'confirmed'
                                                    ? 'Payment confirmed'
                                                    : payment.status ===
                                                        'cancelled'
                                                      ? 'Payment cancelled'
                                                      : 'Payment submitted'}
                                            </p>
                                            <StatusBadge
                                                domain="payment"
                                                value={payment.status}
                                            />
                                        </div>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {formatDate(payment.payment_date)} ·{' '}
                                            {PAYMENT_METHOD_LABELS[
                                                payment.payment_method
                                            ] ?? payment.payment_method}
                                        </p>
                                        <p className="mt-1 font-medium tabular-nums">
                                            {formatPrice(payment.amount)}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="px-4 py-6 text-sm text-muted-foreground">
                            No payment activity yet.
                        </p>
                    )}
                </section>

                {invoicePdf.status === 'pending' && (
                    <p className="text-right text-xs text-muted-foreground">
                        A queue worker is preparing this PDF. Refresh this page
                        when it is ready.
                    </p>
                )}
            </div>

            <PaymentDetailSheet
                payment={selectedPayment}
                open={detailOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedPaymentId(null);
                    }
                }}
                verifyingId={verifyingId}
                canVerify={canVerify}
                onPreview={handlePreview}
                onVerify={handleVerify}
            />

            {previewProof && (
                <DocumentPreview
                    src={previewProof.src}
                    mimeType={previewProof.mimeType}
                    title={previewProof.name}
                    subtitle="Payment Proof"
                    onClose={() => setPreviewProof(null)}
                />
            )}
        </div>
    );
}
