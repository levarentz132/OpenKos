import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    Copy,
    CreditCard,
    DollarSign,
    Edit3,
    ExternalLink,
    Eye,
    Filter,
    HelpCircle,
    MoreHorizontal,
    Plus,
    RefreshCw,
    Search,
    ShieldAlert,
    ShoppingCart,
    Trash2,
    User,
    X,
    XCircle,
    Zap,
} from 'lucide-react';
import React, { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatRupiah } from '@/lib/formatters';

interface BookingOrder {
    id: number;
    cart_token: string;
    reference: string;
    unit_id: number;
    tenant_id: number | null;
    guest_name: string;
    guest_phone: string;
    guest_email: string | null;
    start_date: string;
    end_date: string;
    duration_months: number;
    amount: string | number;
    currency: string;
    status: 'pending' | 'paid' | 'payment_conflict' | 'expired' | 'cancelled';
    lease_id: number | null;
    invoice_id: number | null;
    doku_checkout_url: string | null;
    notes: string | null;
    expires_at: string | null;
    paid_at: string | null;
    created_at: string;
    unit?: {
        id: number;
        name: string;
        slug: string;
        property?: {
            id: number;
            name: string;
            slug: string;
        } | null;
    } | null;
    tenant?: {
        id: number;
        first_name: string;
        last_name: string;
        email: string;
        phone: string;
    } | null;
    lease?: {
        id: number;
        lease_number: string;
        status: string;
        start_date: string;
        end_date: string;
    } | null;
    invoice?: {
        id: number;
        invoice_number: string;
        status: string;
        total_amount: string | number;
    } | null;
}

interface UnitRate {
    id: number;
    unit_id: number;
    amount: string | number;
    billing_unit: string;
    billing_interval: number;
}

interface PropertyOption {
    id: number;
    name: string;
    type: string;
    units: Array<{
        id: number;
        property_id: number;
        name: string;
        status: string;
        capacity: number;
        active_rates?: UnitRate[];
    }>;
}

interface StatsData {
    total_orders: number;
    total_amount: number;
    pending_orders: number;
    pending_amount: number;
    paid_orders: number;
    paid_amount: number;
    conflict_orders: number;
    expired_orders: number;
}

interface PageProps {
    orders: {
        data: BookingOrder[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    stats: StatsData;
    filters: {
        search: string;
        status: string;
        property_id: string;
        start_date: string;
        end_date: string;
    };
    properties: PropertyOption[];
}

export default function CartOrdersIndex({
    orders,
    stats,
    filters,
    properties,
}: PageProps) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'all');
    const [propertyId, setPropertyId] = useState(filters.property_id || 'all');
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');

    // Selection state for multiple delete
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [isBulkDeleteOpen, setIsBulkDeleteOpen] = useState(false);

    // Modals state
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [viewingOrder, setViewingOrder] = useState<BookingOrder | null>(null);
    const [editingOrder, setEditingOrder] = useState<BookingOrder | null>(null);
    const [fulfillingOrder, setFulfillingOrder] = useState<BookingOrder | null>(null);
    const [deletingOrder, setDeletingOrder] = useState<BookingOrder | null>(null);
    const [copiedRef, setCopiedRef] = useState<string | null>(null);

    // Form state for Create/Edit
    const [selectedPropertyId, setSelectedPropertyId] = useState<number | ''>('');
    const [formData, setFormData] = useState({
        unit_id: '',
        guest_name: '',
        guest_phone: '',
        guest_email: '',
        start_date: new Date().toISOString().split('T')[0],
        duration_months: 1,
        amount: '',
        status: 'pending',
        notes: '',
    });

    const isAllSelected =
        orders.data.length > 0 &&
        orders.data.every((order) => selectedIds.includes(order.id));

    const handleToggleSelectAll = () => {
        if (isAllSelected) {
            const currentIds = orders.data.map((o) => o.id);
            setSelectedIds(selectedIds.filter((id) => !currentIds.includes(id)));
        } else {
            const currentIds = orders.data.map((o) => o.id);
            const combined = Array.from(new Set([...selectedIds, ...currentIds]));
            setSelectedIds(combined);
        }
    };

    const handleToggleSelect = (id: number) => {
        if (selectedIds.includes(id)) {
            setSelectedIds(selectedIds.filter((item) => item !== id));
        } else {
            setSelectedIds([...selectedIds, id]);
        }
    };

    const handleFilterSubmit = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        router.get(
            '/cart-orders',
            {
                search,
                status,
                property_id: propertyId,
                start_date: startDate,
                end_date: endDate,
            },
            { preserveState: true },
        );
    };

    const handleResetFilters = () => {
        setSearch('');
        setStatus('all');
        setPropertyId('all');
        setStartDate('');
        setEndDate('');
        setSelectedIds([]);
        router.get('/cart-orders', {}, { preserveState: true });
    };

    const handleOpenCreate = () => {
        const firstProp = properties[0];
        const firstUnit = firstProp?.units?.[0];
        const initialPropId = firstProp ? firstProp.id : '';
        const initialUnitId = firstUnit ? String(firstUnit.id) : '';

        let calculatedAmount = '';
        if (firstUnit?.active_rates?.[0]?.amount) {
            calculatedAmount = String(Number(firstUnit.active_rates[0].amount));
        }

        setSelectedPropertyId(initialPropId);
        setFormData({
            unit_id: initialUnitId,
            guest_name: '',
            guest_phone: '',
            guest_email: '',
            start_date: new Date().toISOString().split('T')[0],
            duration_months: 1,
            amount: calculatedAmount,
            status: 'pending',
            notes: '',
        });
        setIsCreateOpen(true);
    };

    const handlePropertyChange = (pId: number) => {
        setSelectedPropertyId(pId);
        const prop = properties.find((p) => p.id === pId);
        const firstUnit = prop?.units?.[0];
        const newUnitId = firstUnit ? String(firstUnit.id) : '';
        let calculatedAmount = '';
        if (firstUnit?.active_rates?.[0]?.amount) {
            calculatedAmount = String(
                Number(firstUnit.active_rates[0].amount) * Number(formData.duration_months || 1),
            );
        }
        setFormData((prev) => ({
            ...prev,
            unit_id: newUnitId,
            amount: calculatedAmount || prev.amount,
        }));
    };

    const handleUnitChange = (uId: string) => {
        const prop = properties.find((p) => p.id === selectedPropertyId);
        const unit = prop?.units?.find((u) => String(u.id) === uId);
        let calculatedAmount = formData.amount;
        if (unit?.active_rates?.[0]?.amount) {
            calculatedAmount = String(
                Number(unit.active_rates[0].amount) * Number(formData.duration_months || 1),
            );
        }
        setFormData((prev) => ({
            ...prev,
            unit_id: uId,
            amount: calculatedAmount,
        }));
    };

    const handleDurationChange = (months: number) => {
        const prop = properties.find((p) => p.id === selectedPropertyId);
        const unit = prop?.units?.find((u) => String(u.id) === formData.unit_id);
        let calculatedAmount = formData.amount;
        if (unit?.active_rates?.[0]?.amount) {
            calculatedAmount = String(Number(unit.active_rates[0].amount) * Number(months || 1));
        }
        setFormData((prev) => ({
            ...prev,
            duration_months: months,
            amount: calculatedAmount,
        }));
    };

    const handleOpenEdit = (order: BookingOrder) => {
        setEditingOrder(order);
        const propId = order.unit?.property?.id || properties[0]?.id || '';
        setSelectedPropertyId(propId ? Number(propId) : '');
        setFormData({
            unit_id: String(order.unit_id),
            guest_name: order.guest_name,
            guest_phone: order.guest_phone,
            guest_email: order.guest_email || '',
            start_date: order.start_date ? order.start_date.split('T')[0] : '',
            duration_months: order.duration_months || 1,
            amount: String(order.amount),
            status: order.status,
            notes: order.notes || '',
        });
    };

    const handleStoreSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/cart-orders', formData, {
            onSuccess: () => {
                setIsCreateOpen(false);
            },
        });
    };

    const handleUpdateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingOrder) return;
        router.put(`/cart-orders/${editingOrder.id}`, formData, {
            onSuccess: () => {
                setEditingOrder(null);
            },
        });
    };

    const handleDeleteSubmit = () => {
        if (!deletingOrder) return;
        router.delete(`/cart-orders/${deletingOrder.id}`, {
            onSuccess: () => {
                setSelectedIds(selectedIds.filter((id) => id !== deletingOrder.id));
                setDeletingOrder(null);
            },
        });
    };

    const handleBulkDeleteSubmit = () => {
        if (selectedIds.length === 0) return;
        router.post(
            '/cart-orders/bulk-delete',
            { ids: selectedIds },
            {
                onSuccess: () => {
                    setSelectedIds([]);
                    setIsBulkDeleteOpen(false);
                },
            },
        );
    };

    const handleFulfillSubmit = () => {
        if (!fulfillingOrder) return;
        router.post(`/cart-orders/${fulfillingOrder.id}/fulfill`, {}, {
            onSuccess: () => {
                setFulfillingOrder(null);
            },
        });
    };

    const copyToClipboard = (text: string, ref: string) => {
        navigator.clipboard.writeText(text);
        setCopiedRef(ref);
        setTimeout(() => setCopiedRef(null), 2000);
    };

    const renderStatusBadge = (orderStatus: string) => {
        switch (orderStatus) {
            case 'paid':
                return (
                    <Badge className="bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 gap-1 font-medium text-[11px]">
                        <CheckCircle2 className="size-3" />
                        Paid / Fulfilled
                    </Badge>
                );
            case 'pending':
                return (
                    <Badge className="bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30 gap-1 font-medium text-[11px]">
                        <Clock className="size-3" />
                        Pending
                    </Badge>
                );
            case 'payment_conflict':
                return (
                    <Badge className="bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30 gap-1 font-medium text-[11px]">
                        <ShieldAlert className="size-3" />
                        Conflict / Double Booking
                    </Badge>
                );
            case 'expired':
                return (
                    <Badge variant="outline" className="text-muted-foreground gap-1 text-[11px]">
                        <XCircle className="size-3" />
                        Expired
                    </Badge>
                );
            case 'cancelled':
                return (
                    <Badge variant="outline" className="text-muted-foreground gap-1 text-[11px]">
                        <XCircle className="size-3" />
                        Cancelled
                    </Badge>
                );
            default:
                return <Badge variant="outline">{orderStatus}</Badge>;
        }
    };

    const currentSelectedProperty = properties.find((p) => p.id === selectedPropertyId);
    const availableUnits = currentSelectedProperty?.units || [];

    return (
        <>
            <Head title="Cart Orders Management" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                {/* Header Title Bar */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <ShoppingCart className="size-5" />
                            </div>
                            <h1 className="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                                Cart Orders (Pesanan Keranjang)
                            </h1>
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
                            Kelola pesanan booking online pra-sewa, pembayaran keranjang, dan konversi ke kontrak sewa aktif.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.reload({ only: ['orders', 'stats'] })}
                            className="gap-1.5 text-xs shadow-2xs"
                        >
                            <RefreshCw className="size-3.5" />
                            Refresh
                        </Button>
                        <Button
                            size="sm"
                            onClick={handleOpenCreate}
                            className="gap-1.5 text-xs shadow-2xs bg-primary text-primary-foreground hover:bg-primary/90"
                        >
                            <Plus className="size-4" />
                            Tambah Pesanan Cart
                        </Button>
                    </div>
                </div>

                {/* Metric Summary Cards */}
                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {/* Total Orders */}
                    <div className="rounded-xl border border-border bg-card p-4 shadow-2xs">
                        <div className="flex items-center justify-between text-xs font-medium text-muted-foreground">
                            <span>Total Pesanan Cart</span>
                            <ShoppingCart className="size-4 text-primary" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-foreground tabular-nums">
                            {stats.total_orders}
                        </p>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Total potensi: {formatRupiah(stats.total_amount)}
                        </p>
                    </div>

                    {/* Pending Orders */}
                    <div className="rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 shadow-2xs">
                        <div className="flex items-center justify-between text-xs font-medium text-amber-600 dark:text-amber-400">
                            <span>Menunggu Pembayaran</span>
                            <Clock className="size-4" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400 tabular-nums">
                            {stats.pending_orders}
                        </p>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Nominal pending: {formatRupiah(stats.pending_amount)}
                        </p>
                    </div>

                    {/* Paid Orders */}
                    <div className="rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-4 shadow-2xs">
                        <div className="flex items-center justify-between text-xs font-medium text-emerald-600 dark:text-emerald-400">
                            <span>Terbayar & Aktif</span>
                            <CheckCircle2 className="size-4" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                            {stats.paid_orders}
                        </p>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Total terkonfirmasi: {formatRupiah(stats.paid_amount)}
                        </p>
                    </div>

                    {/* Conflict & Expired */}
                    <div className="rounded-xl border border-rose-500/20 bg-rose-500/5 p-4 shadow-2xs">
                        <div className="flex items-center justify-between text-xs font-medium text-rose-600 dark:text-rose-400">
                            <span>Perlu Perhatian / Konflik</span>
                            <AlertCircle className="size-4" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400 tabular-nums">
                            {stats.conflict_orders}
                        </p>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            {stats.expired_orders} pesanan expired / dibatalkan
                        </p>
                    </div>
                </section>

                {/* Filter Control Bar */}
                <section className="rounded-xl border border-border bg-card p-4 shadow-2xs">
                    <form onSubmit={handleFilterSubmit} className="flex flex-col gap-4">
                        <div className="grid gap-3 sm:grid-cols-12 sm:items-end">
                            {/* Search */}
                            <div className="space-y-1 sm:col-span-4">
                                <label className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
                                    <Search className="size-3.5" />
                                    Cari Pesanan / Tamu / Referensi
                                </label>
                                <Input
                                    type="text"
                                    placeholder="Contoh: BK-..., Nama, HP, Email..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="h-9 text-xs"
                                />
                            </div>

                            {/* Status Filter */}
                            <div className="space-y-1 sm:col-span-2">
                                <label className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
                                    <Filter className="size-3.5" />
                                    Status Cart
                                </label>
                                <select
                                    value={status}
                                    onChange={(e) => setStatus(e.target.value)}
                                    aria-label="Filter status cart"
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    <option value="all">Semua Status</option>
                                    <option value="pending">Pending (Menunggu)</option>
                                    <option value="paid">Paid (Terbayar/Aktif)</option>
                                    <option value="payment_conflict">Payment Conflict</option>
                                    <option value="expired">Expired</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>

                            {/* Property Filter */}
                            <div className="space-y-1 sm:col-span-3">
                                <label className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
                                    <Building2 className="size-3.5" />
                                    Properti
                                </label>
                                <select
                                    value={propertyId}
                                    onChange={(e) => setPropertyId(e.target.value)}
                                    aria-label="Filter berdasarkan properti"
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    <option value="all">Semua Properti</option>
                                    {properties.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Date Filter */}
                            <div className="space-y-1 sm:col-span-3">
                                <label className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
                                    <Calendar className="size-3.5" />
                                    Mulai Dari
                                </label>
                                <Input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="h-9 text-xs"
                                />
                            </div>
                        </div>

                        {/* Submit & Reset actions */}
                        <div className="flex items-center justify-between border-t border-border/50 pt-3">
                            <span className="text-xs text-muted-foreground">
                                Menampilkan {orders.total} total data pesanan keranjang
                            </span>
                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={handleResetFilters}
                                    className="h-8 text-xs text-muted-foreground hover:text-foreground"
                                >
                                    Reset Filter
                                </Button>
                                <Button type="submit" size="sm" className="h-8 gap-1 text-xs">
                                    <Filter className="size-3" />
                                    Terapkan Filter
                                </Button>
                            </div>
                        </div>
                    </form>
                </section>

                {/* Bulk Actions Banner (When Items Selected) */}
                {selectedIds.length > 0 && (
                    <section className="flex items-center justify-between rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 shadow-2xs transition-all animate-in fade-in slide-in-from-top-2">
                        <div className="flex items-center gap-3">
                            <span className="flex size-7 items-center justify-center rounded-full bg-destructive/20 text-xs font-bold text-destructive">
                                {selectedIds.length}
                            </span>
                            <div>
                                <h4 className="text-xs font-semibold text-foreground">
                                    {selectedIds.length} Pesanan Keranjang Dipilih
                                </h4>
                                <p className="text-[11px] text-muted-foreground">
                                    Anda dapat menghapus pesanan terpilih secara sekaligus.
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setSelectedIds([])}
                                className="h-8 text-xs gap-1"
                            >
                                <X className="size-3.5" />
                                Batal Pilihan
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                onClick={() => setIsBulkDeleteOpen(true)}
                                className="h-8 text-xs gap-1.5 shadow-2xs"
                            >
                                <Trash2 className="size-3.5" />
                                Hapus Terpilih ({selectedIds.length})
                            </Button>
                        </div>
                    </section>
                )}

                {/* Data Table */}
                <section className="overflow-hidden rounded-xl border border-border bg-card shadow-2xs">
                    {orders.data && orders.data.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="border-b border-border bg-muted/40 text-muted-foreground font-medium">
                                        <th className="w-10 px-4 py-3">
                                            <Checkbox
                                                checked={isAllSelected}
                                                onCheckedChange={handleToggleSelectAll}
                                                aria-label="Pilih semua pesanan di halaman ini"
                                            />
                                        </th>
                                        <th className="px-4 py-3">Referensi</th>
                                        <th className="px-4 py-3">Pemesan (Tamu)</th>
                                        <th className="px-4 py-3">Kamar & Properti</th>
                                        <th className="px-4 py-3">Periode & Durasi</th>
                                        <th className="px-4 py-3">Nominal</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60">
                                    {orders.data.map((order) => {
                                        const isPaid = order.status === 'paid';
                                        const isPending = order.status === 'pending';
                                        const isConflict = order.status === 'payment_conflict';
                                        const isSelected = selectedIds.includes(order.id);

                                        return (
                                            <tr
                                                key={order.id}
                                                className={`transition-colors ${
                                                    isSelected
                                                        ? 'bg-destructive/5 hover:bg-destructive/10'
                                                        : 'hover:bg-muted/30'
                                                }`}
                                            >
                                                {/* Row Checkbox */}
                                                <td className="w-10 px-4 py-3">
                                                    <Checkbox
                                                        checked={isSelected}
                                                        onCheckedChange={() => handleToggleSelect(order.id)}
                                                        aria-label={`Pilih pesanan ${order.reference}`}
                                                    />
                                                </td>

                                                {/* Reference */}
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-1.5">
                                                        <span className="font-mono font-bold text-foreground">
                                                            {order.reference}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            onClick={() => copyToClipboard(order.reference, order.reference)}
                                                            className="text-muted-foreground hover:text-primary transition-colors p-0.5 rounded"
                                                            title="Salin Referensi"
                                                        >
                                                            <Copy className="size-3" />
                                                        </button>
                                                    </div>
                                                    {copiedRef === order.reference && (
                                                        <span className="text-[10px] text-emerald-500 font-medium block">
                                                            Disalin!
                                                        </span>
                                                    )}
                                                    <span className="text-[10px] text-muted-foreground block mt-0.5">
                                                        {formatDate(order.created_at)}
                                                    </span>
                                                </td>

                                                {/* Guest */}
                                                <td className="px-4 py-3">
                                                    <div className="font-semibold text-foreground flex items-center gap-1">
                                                        <User className="size-3 text-muted-foreground" />
                                                        {order.guest_name}
                                                    </div>
                                                    <div className="text-[11px] text-muted-foreground">
                                                        {order.guest_phone}
                                                    </div>
                                                    {order.guest_email && (
                                                        <div className="text-[11px] text-muted-foreground/80">
                                                            {order.guest_email}
                                                        </div>
                                                    )}
                                                </td>

                                                {/* Room & Property */}
                                                <td className="px-4 py-3">
                                                    <div className="font-medium text-foreground">
                                                        {order.unit?.name || `Unit #${order.unit_id}`}
                                                    </div>
                                                    <div className="text-[11px] text-muted-foreground flex items-center gap-1">
                                                        <Building2 className="size-3 text-muted-foreground" />
                                                        {order.unit?.property?.name || 'Properti'}
                                                    </div>
                                                </td>

                                                {/* Period & Duration */}
                                                <td className="px-4 py-3">
                                                    <div className="font-medium text-foreground">
                                                        {formatDate(order.start_date)} - {formatDate(order.end_date)}
                                                    </div>
                                                    <div className="text-[11px] text-muted-foreground">
                                                        Durasi: <span className="font-semibold text-foreground">{order.duration_months} Bulan</span>
                                                    </div>
                                                </td>

                                                {/* Amount */}
                                                <td className="px-4 py-3 font-semibold text-foreground tabular-nums">
                                                    {formatRupiah(Number(order.amount))}
                                                </td>

                                                {/* Status */}
                                                <td className="px-4 py-3">
                                                    {renderStatusBadge(order.status)}
                                                </td>

                                                {/* Actions */}
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        {(isPending || isConflict) && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => setFulfillingOrder(order)}
                                                                className="h-7 px-2 text-[11px] font-medium border-emerald-500/40 text-emerald-600 hover:bg-emerald-500/10 dark:text-emerald-400 gap-1"
                                                                title="Konversi ke Kontrak Sewa"
                                                            >
                                                                <Zap className="size-3" />
                                                                Fulfill
                                                            </Button>
                                                        )}

                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger asChild>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-7 text-muted-foreground hover:text-foreground"
                                                                >
                                                                    <MoreHorizontal className="size-4" />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end" className="w-44">
                                                                <DropdownMenuItem
                                                                    onClick={() => setViewingOrder(order)}
                                                                    className="gap-2 text-xs"
                                                                >
                                                                    <Eye className="size-3.5 text-muted-foreground" />
                                                                    Lihat Detail
                                                                </DropdownMenuItem>

                                                                <DropdownMenuItem
                                                                    onClick={() => handleOpenEdit(order)}
                                                                    className="gap-2 text-xs"
                                                                >
                                                                    <Edit3 className="size-3.5 text-muted-foreground" />
                                                                    Edit Pesanan
                                                                </DropdownMenuItem>

                                                                {order.doku_checkout_url && isPending && (
                                                                    <DropdownMenuItem asChild>
                                                                        <a
                                                                            href={order.doku_checkout_url}
                                                                            target="_blank"
                                                                            rel="noreferrer"
                                                                            className="flex items-center gap-2 text-xs"
                                                                        >
                                                                            <CreditCard className="size-3.5 text-muted-foreground" />
                                                                            Buka Checkout DOKU
                                                                        </a>
                                                                    </DropdownMenuItem>
                                                                )}

                                                                {order.lease_id && (
                                                                    <DropdownMenuItem asChild>
                                                                        <Link
                                                                            href={`/leases/${order.lease_id}`}
                                                                            className="flex items-center gap-2 text-xs text-primary"
                                                                        >
                                                                            <ExternalLink className="size-3.5" />
                                                                            Buka Kontrak Sewa
                                                                        </Link>
                                                                    </DropdownMenuItem>
                                                                )}

                                                                <DropdownMenuSeparator />

                                                                <DropdownMenuItem
                                                                    onClick={() => setDeletingOrder(order)}
                                                                    className="gap-2 text-xs text-destructive focus:text-destructive"
                                                                >
                                                                    <Trash2 className="size-3.5" />
                                                                    Hapus / Batalkan
                                                                </DropdownMenuItem>
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center p-12 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <ShoppingCart className="size-6" />
                            </div>
                            <h3 className="mt-3 text-sm font-semibold text-foreground">
                                Tidak ada data pesanan keranjang
                            </h3>
                            <p className="mt-1 text-xs text-muted-foreground max-w-sm">
                                Belum ada pesanan booking yang masuk atau tidak ada yang sesuai dengan filter pencarian saat ini.
                            </p>
                            <Button
                                size="sm"
                                onClick={handleOpenCreate}
                                className="mt-4 gap-1.5 text-xs"
                            >
                                <Plus className="size-3.5" />
                                Tambah Pesanan Baru
                            </Button>
                        </div>
                    )}

                    {/* Pagination */}
                    {orders.links && orders.links.length > 3 && (
                        <div className="flex items-center justify-between border-t border-border bg-muted/20 px-4 py-3 text-xs">
                            <span className="text-muted-foreground">
                                Menampilkan {orders.from || 0} - {orders.to || 0} dari {orders.total} pesanan
                            </span>
                            <div className="flex items-center gap-1">
                                {orders.links.map((link, idx) => {
                                    if (!link.url) {
                                        return (
                                            <span
                                                key={idx}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                                className="px-2.5 py-1 text-muted-foreground/50"
                                            />
                                        );
                                    }
                                    return (
                                        <Link
                                            key={idx}
                                            href={link.url}
                                            preserveState
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                            className={`rounded px-2.5 py-1 text-xs font-medium transition-colors ${
                                                link.active
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'text-foreground hover:bg-muted'
                                            }`}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </section>
            </div>

            {/* Create Order Dialog */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="sm:max-w-lg">
                    <form onSubmit={handleStoreSubmit}>
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <ShoppingCart className="size-4 text-primary" />
                                Tambah Pesanan Keranjang Baru
                            </DialogTitle>
                            <DialogDescription className="text-xs">
                                Masukkan rincian kamar kos, data tamu, periode sewa, dan nominal tagihan booking.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4 py-4 text-xs">
                            {/* Property & Unit */}
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1">
                                    <label className="font-medium text-foreground">Properti</label>
                                    <select
                                        value={selectedPropertyId}
                                        onChange={(e) => handlePropertyChange(Number(e.target.value))}
                                        aria-label="Pilih Properti"
                                        required
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    >
                                        <option value="" disabled>Pilih Properti</option>
                                        {properties.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="space-y-1">
                                    <label className="font-medium text-foreground">Kamar / Unit</label>
                                    <select
                                        value={formData.unit_id}
                                        onChange={(e) => handleUnitChange(e.target.value)}
                                        aria-label="Pilih Kamar / Unit"
                                        required
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    >
                                        <option value="" disabled>Pilih Kamar</option>
                                        {availableUnits.map((u) => {
                                            const rate = u.active_rates?.[0]?.amount;
                                            return (
                                                <option key={u.id} value={u.id}>
                                                    {u.name} {rate ? `(${formatRupiah(Number(rate))}/bln)` : ''}
                                                </option>
                                            );
                                        })}
                                    </select>
                                </div>
                            </div>

                            {/* Guest Details */}
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1">
                                    <label className="font-medium text-foreground">Nama Tamu / Pemesan</label>
                                    <Input
                                        type="text"
                                        required
                                        placeholder="Contoh: Budi Santoso"
                                        value={formData.guest_name}
                                        onChange={(e) => setFormData({ ...formData, guest_name: e.target.value })}
                                        className="h-9 text-xs"
                                    />
                                </div>
                                <div className="space-y-1">
                                    <label className="font-medium text-foreground">Nomor Telepon / WA</label>
                                    <Input
                                        type="tel"
                                        required
                                        placeholder="Contoh: 08123456789"
                                        value={formData.guest_phone}
                                        onChange={(e) => setFormData({ ...formData, guest_phone: e.target.value })}
                                        className="h-9 text-xs"
                                    />
                                </div>
                            </div>

                            <div className="space-y-1">
                                <label className="font-medium text-foreground">Email Tamu (Opsional)</label>
                                <Input
                                    type="email"
                                    placeholder="tamu@example.com"
                                    value={formData.guest_email}
                                    onChange={(e) => setFormData({ ...formData, guest_email: e.target.value })}
                                    className="h-9 text-xs"
                                />
                            </div>

                            {/* Dates & Duration */}
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1">
                                    <label className="font-medium text-foreground">Tanggal Mulai Sewa</label>
                                    <Input
                                        type="date"
                                        required
                                        value={formData.start_date}
                                        onChange={(e) => setFormData({ ...formData, start_date: e.target.value })}
                                        className="h-9 text-xs"
                                    />
                                </div>
                                <div className="space-y-1">
                                    <label className="font-medium text-foreground">Durasi (Bulan)</label>
                                    <Input
                                        type="number"
                                        min="1"
                                        max="60"
                                        required
                                        value={formData.duration_months}
                                        onChange={(e) => handleDurationChange(Number(e.target.value))}
                                        className="h-9 text-xs"
                                    />
                                </div>
                            </div>

                            {/* Amount */}
                            <div className="space-y-1">
                                <label className="font-medium text-foreground">Total Nominal (Rp)</label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="1000"
                                    required
                                    placeholder="Contoh: 1500000"
                                    value={formData.amount}
                                    onChange={(e) => setFormData({ ...formData, amount: e.target.value })}
                                    className="h-9 text-xs font-semibold"
                                />
                            </div>

                            {/* Notes */}
                            <div className="space-y-1">
                                <label className="font-medium text-foreground">Catatan Tambahan (Opsional)</label>
                                <Textarea
                                    rows={2}
                                    placeholder="Catatan pesanan, permintaan khusus, dll."
                                    value={formData.notes}
                                    onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                                    className="text-xs resize-none"
                                />
                            </div>
                        </div>

                        <DialogFooter className="gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setIsCreateOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button type="submit" size="sm">
                                Simpan Pesanan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Order Dialog */}
            <Dialog open={!!editingOrder} onOpenChange={(open) => !open && setEditingOrder(null)}>
                <DialogContent className="sm:max-w-lg">
                    <form onSubmit={handleUpdateSubmit}>
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <Edit3 className="size-4 text-primary" />
                                Edit Pesanan Cart: {editingOrder?.reference}
                            </DialogTitle>
                            <DialogDescription className="text-xs">
                                Perbarui data pemesan, kamar, nominal, status, atau catatan pesanan.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4 py-4 text-xs">
                            {/* Property & Unit */}
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1">
                                    <label className="font-medium text-foreground">Properti</label>
                                    <select
                                        value={selectedPropertyId}
                                        onChange={(e) => handlePropertyChange(Number(e.target.value))}
                                        aria-label="Pilih Properti (Edit)"
                                        required
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    >
                                        <option value="" disabled>Pilih Properti</option>
                                        {properties.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="space-y-1">
                                    <label className="font-medium text-foreground">Kamar / Unit</label>
                                    <select
                                        value={formData.unit_id}
                                        onChange={(e) => handleUnitChange(e.target.value)}
                                        aria-label="Pilih Kamar / Unit (Edit)"
                                        required
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    >
                                        <option value="" disabled>Pilih Kamar</option>
                                        {availableUnits.map((u) => (
                                            <option key={u.id} value={u.id}>
                                                {u.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            {/* Guest Details */}
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1">
                                    <label className="font-medium text-foreground">Nama Tamu</label>
                                    <Input
                                        type="text"
                                        required
                                        value={formData.guest_name}
                                        onChange={(e) => setFormData({ ...formData, guest_name: e.target.value })}
                                        className="h-9 text-xs"
                                    />
                                </div>
                                <div className="space-y-1">
                                    <label className="font-medium text-foreground">Nomor Telepon / WA</label>
                                    <Input
                                        type="tel"
                                        required
                                        value={formData.guest_phone}
                                        onChange={(e) => setFormData({ ...formData, guest_phone: e.target.value })}
                                        className="h-9 text-xs"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1">
                                    <label className="font-medium text-foreground">Email Tamu</label>
                                    <Input
                                        type="email"
                                        value={formData.guest_email}
                                        onChange={(e) => setFormData({ ...formData, guest_email: e.target.value })}
                                        className="h-9 text-xs"
                                    />
                                </div>
                                <div className="space-y-1">
                                    <label className="font-medium text-foreground">Status Cart</label>
                                    <select
                                        value={formData.status}
                                        onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                                        aria-label="Ubah Status Cart"
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-2xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring font-medium"
                                    >
                                        <option value="pending">Pending</option>
                                        <option value="paid">Paid</option>
                                        <option value="payment_conflict">Payment Conflict</option>
                                        <option value="expired">Expired</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                            </div>

                            {/* Dates & Duration */}
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1">
                                    <label className="font-medium text-foreground">Tanggal Mulai</label>
                                    <Input
                                        type="date"
                                        required
                                        value={formData.start_date}
                                        onChange={(e) => setFormData({ ...formData, start_date: e.target.value })}
                                        className="h-9 text-xs"
                                    />
                                </div>
                                <div className="space-y-1">
                                    <label className="font-medium text-foreground">Durasi (Bulan)</label>
                                    <Input
                                        type="number"
                                        min="1"
                                        max="60"
                                        required
                                        value={formData.duration_months}
                                        onChange={(e) => handleDurationChange(Number(e.target.value))}
                                        className="h-9 text-xs"
                                    />
                                </div>
                            </div>

                            {/* Amount */}
                            <div className="space-y-1">
                                <label className="font-medium text-foreground">Total Nominal (Rp)</label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="1000"
                                    required
                                    value={formData.amount}
                                    onChange={(e) => setFormData({ ...formData, amount: e.target.value })}
                                    className="h-9 text-xs font-semibold"
                                />
                            </div>

                            {/* Notes */}
                            <div className="space-y-1">
                                <label className="font-medium text-foreground">Catatan Tambahan</label>
                                <Textarea
                                    rows={2}
                                    value={formData.notes}
                                    onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                                    className="text-xs resize-none"
                                />
                            </div>
                        </div>

                        <DialogFooter className="gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setEditingOrder(null)}
                            >
                                Batal
                            </Button>
                            <Button type="submit" size="sm">
                                Perbarui Pesanan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* View Order Details Dialog */}
            <Dialog open={!!viewingOrder} onOpenChange={(open) => !open && setViewingOrder(null)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center justify-between">
                            <span className="flex items-center gap-2">
                                <ShoppingCart className="size-4 text-primary" />
                                Rincian Pesanan: {viewingOrder?.reference}
                            </span>
                            {viewingOrder && renderStatusBadge(viewingOrder.status)}
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Detail lengkap booking keranjang online dan riwayat pembayaran.
                        </DialogDescription>
                    </DialogHeader>

                    {viewingOrder && (
                        <div className="grid gap-3 py-3 text-xs">
                            <div className="grid grid-cols-2 gap-3 rounded-lg border border-border/70 bg-muted/30 p-3">
                                <div>
                                    <span className="text-[11px] text-muted-foreground block">Nama Tamu</span>
                                    <span className="font-semibold text-foreground">{viewingOrder.guest_name}</span>
                                </div>
                                <div>
                                    <span className="text-[11px] text-muted-foreground block">Nomor HP / WhatsApp</span>
                                    <span className="font-semibold text-foreground">{viewingOrder.guest_phone}</span>
                                </div>
                                <div>
                                    <span className="text-[11px] text-muted-foreground block">Email</span>
                                    <span className="font-medium text-foreground">{viewingOrder.guest_email || '—'}</span>
                                </div>
                                <div>
                                    <span className="text-[11px] text-muted-foreground block">Kamar & Properti</span>
                                    <span className="font-medium text-foreground">
                                        {viewingOrder.unit?.name} ({viewingOrder.unit?.property?.name || 'Properti'})
                                    </span>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3 rounded-lg border border-border/70 bg-muted/30 p-3">
                                <div>
                                    <span className="text-[11px] text-muted-foreground block">Periode Sewa</span>
                                    <span className="font-medium text-foreground">
                                        {formatDate(viewingOrder.start_date)} - {formatDate(viewingOrder.end_date)}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-[11px] text-muted-foreground block">Durasi</span>
                                    <span className="font-semibold text-foreground">{viewingOrder.duration_months} Bulan</span>
                                </div>
                                <div>
                                    <span className="text-[11px] text-muted-foreground block">Total Tagihan</span>
                                    <span className="font-bold text-foreground tabular-nums text-sm text-primary">
                                        {formatRupiah(Number(viewingOrder.amount))}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-[11px] text-muted-foreground block">Dibuat Pada</span>
                                    <span className="font-medium text-foreground">{formatDate(viewingOrder.created_at)}</span>
                                </div>
                            </div>

                            {viewingOrder.notes && (
                                <div className="rounded-lg border border-border/70 bg-muted/30 p-3">
                                    <span className="text-[11px] text-muted-foreground block font-medium">Catatan / Alasan:</span>
                                    <p className="mt-1 text-foreground leading-relaxed">{viewingOrder.notes}</p>
                                </div>
                            )}

                            {viewingOrder.lease_id && (
                                <div className="flex items-center justify-between rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3">
                                    <div>
                                        <span className="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium block">
                                            Kontrak Sewa Terbit
                                        </span>
                                        <span className="font-bold text-foreground">
                                            Lease #{viewingOrder.lease?.lease_number || viewingOrder.lease_id}
                                        </span>
                                    </div>
                                    <Button size="sm" variant="outline" asChild className="h-7 text-xs">
                                        <Link href={`/leases/${viewingOrder.lease_id}`}>
                                            Buka Kontrak
                                        </Link>
                                    </Button>
                                </div>
                            )}

                            {viewingOrder.doku_checkout_url && viewingOrder.status === 'pending' && (
                                <div className="flex items-center justify-between rounded-lg border border-amber-500/30 bg-amber-500/10 p-3">
                                    <div>
                                        <span className="text-[11px] text-amber-600 dark:text-amber-400 font-medium block">
                                            Link Checkout DOKU
                                        </span>
                                        <span className="text-xs text-muted-foreground font-mono truncate max-w-[240px] block">
                                            {viewingOrder.doku_checkout_url}
                                        </span>
                                    </div>
                                    <Button size="sm" variant="outline" asChild className="h-7 text-xs gap-1">
                                        <a href={viewingOrder.doku_checkout_url} target="_blank" rel="noreferrer">
                                            <ExternalLink className="size-3" />
                                            Buka
                                        </a>
                                    </Button>
                                </div>
                            )}
                        </div>
                    )}

                    <DialogFooter className="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setViewingOrder(null)}
                        >
                            Tutup
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Fulfill Confirmation Dialog */}
            <Dialog open={!!fulfillingOrder} onOpenChange={(open) => !open && setFulfillingOrder(null)}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                            <Zap className="size-4" />
                            Konfirmasi Fulfill Pesanan
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Apakah Anda yakin ingin memproses pesanan <strong>{fulfillingOrder?.reference}</strong> secara manual?
                            Tindakan ini akan membuat data penyewa, mengubah status kamar menjadi terisi (occupied), menerbitkan invoice, dan mengaktifkan kontrak sewa.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setFulfillingOrder(null)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            onClick={handleFulfillSubmit}
                            className="bg-emerald-600 text-white hover:bg-emerald-700"
                        >
                            Ya, Fulfill Pesanan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Single Delete Confirmation Dialog */}
            <Dialog open={!!deletingOrder} onOpenChange={(open) => !open && setDeletingOrder(null)}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle className="text-destructive flex items-center gap-2">
                            <Trash2 className="size-4" />
                            Hapus Pesanan Cart
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Apakah Anda yakin ingin menghapus pesanan <strong>{deletingOrder?.reference}</strong> ({deletingOrder?.guest_name})? Data yang telah dihapus tidak dapat dikembalikan.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setDeletingOrder(null)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={handleDeleteSubmit}
                        >
                            Hapus Pesanan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Bulk Delete Confirmation Dialog */}
            <Dialog open={isBulkDeleteOpen} onOpenChange={setIsBulkDeleteOpen}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle className="text-destructive flex items-center gap-2">
                            <Trash2 className="size-4" />
                            Hapus {selectedIds.length} Pesanan Terpilih
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            Apakah Anda yakin ingin menghapus <strong>{selectedIds.length} pesanan keranjang</strong> yang dipilih? Tindakan ini bersifat permanen dan tidak dapat dibatalkan.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setIsBulkDeleteOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={handleBulkDeleteSubmit}
                        >
                            Hapus Semua Terpilih
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
