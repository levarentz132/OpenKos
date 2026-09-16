import { Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock,
    ArrowRight,
    Building2,
    ShieldCheck,
    Home,
    Settings,
    Receipt,
    AlertCircle,
} from 'lucide-react';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { formatPrice } from '@/lib/formatters';

interface PaymentStatusProps {
    reference: string;
    status: 'success' | 'pending' | 'failed';
    statusCode?: string | null;
    amount?: number | null;
    description?: string | null;
    propertyUnit?: string | null;
    gateway?: string;
    isOwner: boolean;
    isTenant: boolean;
    isGuest: boolean;
}

export default function PaymentStatusPage({
    reference,
    status = 'success',
    statusCode,
    amount,
    description,
    propertyUnit,
    gateway = 'DOKU Payment Gateway (Sandbox)',
    isOwner,
    isTenant,
    isGuest,
}: PaymentStatusProps) {
    const isSuccess = status === 'success';

    return (
        <div className="flex min-h-[80vh] items-center justify-center py-6">
            <Head title="Status Pembayaran - OpenKOS" />

            <Card className="w-full max-w-xl border-border/80 shadow-xl backdrop-blur-sm">
                <CardHeader className="text-center pb-4">
                    <div className="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400">
                        {isSuccess ? (
                            <CheckCircle2 className="h-12 w-12 animate-in zoom-in-75 duration-300" />
                        ) : (
                            <Clock className="h-12 w-12 text-amber-500 animate-pulse" />
                        )}
                    </div>

                    <div className="inline-flex items-center justify-center gap-2">
                        <Badge
                            variant={isSuccess ? 'default' : 'secondary'}
                            className={
                                isSuccess
                                    ? 'bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-600'
                                    : 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                            }
                        >
                            {isSuccess ? 'Pembayaran Berhasil / Diterima' : 'Menunggu Konfirmasi'}
                        </Badge>
                        {statusCode && (
                            <Badge variant="outline" className="font-mono text-xs">
                                Code: {statusCode}
                            </Badge>
                        )}
                    </div>

                    <CardTitle className="mt-3 text-2xl font-bold tracking-tight">
                        {isSuccess ? 'Terima Kasih atas Pembayaran Anda!' : 'Proses Verifikasi Pembayaran'}
                    </CardTitle>

                    <CardDescription className="text-sm mt-1 text-muted-foreground">
                        {isSuccess
                            ? 'Transaksi Anda telah berhasil dicatat oleh sistem payment gateway.'
                            : 'Pembayaran sedang diproses oleh payment gateway. Saldo tagihan akan diperbarui otomatis.'}
                    </CardDescription>
                </CardHeader>

                <CardContent className="space-y-4 pt-2">
                    {/* Transaction Details Box */}
                    <div className="rounded-xl border border-border/60 bg-muted/30 p-4 space-y-3">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">Nomor Referensi</span>
                            <span className="font-mono font-semibold text-foreground select-all">
                                {reference}
                            </span>
                        </div>

                        {description && (
                            <div className="flex items-center justify-between text-sm border-t border-border/40 pt-2">
                                <span className="text-muted-foreground">Keterangan</span>
                                <span className="font-medium text-foreground text-right max-w-[280px] truncate">
                                    {description}
                                </span>
                            </div>
                        )}

                        {propertyUnit && (
                            <div className="flex items-center justify-between text-sm border-t border-border/40 pt-2">
                                <span className="text-muted-foreground">Properti / Unit</span>
                                <span className="font-medium text-foreground flex items-center gap-1.5">
                                    <Building2 className="h-3.5 w-3.5 text-primary" />
                                    {propertyUnit}
                                </span>
                            </div>
                        )}

                        {amount !== null && amount !== undefined && (
                            <div className="flex items-center justify-between text-sm border-t border-border/40 pt-2">
                                <span className="text-muted-foreground">Total Nominal</span>
                                <span className="text-base font-bold text-emerald-600 dark:text-emerald-400">
                                    {formatPrice(amount, 'IDR')}
                                </span>
                            </div>
                        )}

                        <div className="flex items-center justify-between text-xs border-t border-border/40 pt-2 text-muted-foreground">
                            <span>Gateway Provider</span>
                            <span className="flex items-center gap-1 font-medium">
                                <ShieldCheck className="h-3.5 w-3.5 text-emerald-500" />
                                {gateway}
                            </span>
                        </div>
                    </div>

                    {/* Context Specific Alerts */}
                    {isOwner && (
                        <div className="rounded-lg border border-amber-500/20 bg-amber-500/10 p-3.5 text-xs text-amber-700 dark:text-amber-300">
                            <div className="flex gap-2">
                                <AlertCircle className="h-4 w-4 shrink-0 mt-0.5" />
                                <div>
                                    <p className="font-semibold">Mode Pemilik (Owner / Sandbox Testing)</p>
                                    <p className="mt-0.5 text-amber-700/90 dark:text-amber-300/90">
                                        Anda sedang login sebagai pemilik kos. Pengujian transaksi sandbox DOKU berhasil diproses dan dapat dipantau dari menu Pengaturan Gateway.
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    {isGuest && (
                        <div className="rounded-lg border border-primary/20 bg-primary/5 p-3.5 text-xs text-muted-foreground">
                            <p className="font-medium text-foreground">Konfirmasi Pesanan</p>
                            <p className="mt-0.5">
                                Rincian sewa dan tanda terima telah diteruskan. Silakan periksa pesan WhatsApp Anda untuk instruksi check-in kamar atau masuk ke portal penghuni.
                            </p>
                        </div>
                    )}
                </CardContent>

                <CardFooter className="flex flex-col sm:flex-row gap-2 pt-2 pb-6">
                    {isOwner ? (
                        <>
                            <Button asChild className="w-full sm:w-1/2" variant="default">
                                <Link href="/settings/payment-gateway">
                                    <Settings className="mr-2 h-4 w-4" />
                                    Pengaturan Gateway
                                </Link>
                            </Button>
                            <Button asChild className="w-full sm:w-1/2" variant="outline">
                                <Link href="/dashboard">
                                    <Home className="mr-2 h-4 w-4" />
                                    Dashboard Pemilik
                                </Link>
                            </Button>
                        </>
                    ) : isTenant ? (
                        <>
                            <Button asChild className="w-full sm:w-1/2" variant="default">
                                <Link href="/portal/billing">
                                    <Receipt className="mr-2 h-4 w-4" />
                                    Tagihan Saya
                                </Link>
                            </Button>
                            <Button asChild className="w-full sm:w-1/2" variant="outline">
                                <Link href="/portal/dashboard">
                                    <Home className="mr-2 h-4 w-4" />
                                    Dashboard Penghuni
                                </Link>
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button asChild className="w-full sm:w-1/2" variant="default">
                                <Link href="/login">
                                    Masuk ke Akun
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Link>
                            </Button>
                            <Button asChild className="w-full sm:w-1/2" variant="outline">
                                <Link href="/">
                                    Halaman Utama
                                </Link>
                            </Button>
                        </>
                    )}
                </CardFooter>
            </Card>
        </div>
    );
}
