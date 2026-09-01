import { Head, router } from '@inertiajs/react';
import {
    Calendar,
    Coins,
    Edit2,
    Filter,
    MoreHorizontal,
    Plus,
    PlusCircle,
    RefreshCw,
    Search,
    Trash2,
    Wallet,
} from 'lucide-react';
import React, { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatRupiah } from '@/lib/formatters';

interface AdditionalIncomeEntry {
    id: number;
    title: string;
    category: string;
    amount: string | number;
    income_date: string;
    notes: string | null;
    recorded_by: number | null;
    recorded_by_user?: { id: number; name: string } | null;
}

interface CategoryOption {
    value: string;
    label: string;
}

interface PageProps {
    incomes: {
        data: AdditionalIncomeEntry[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    total_amount: number;
    filters: {
        search: string;
        category: string;
        start_date: string;
        end_date: string;
    };
    categories: CategoryOption[];
}

export default function AdditionalIncomesIndex({
    incomes,
    total_amount,
    filters,
    categories,
}: PageProps) {
    const [search, setSearch] = useState(filters.search || '');
    const [category, setCategory] = useState(filters.category || 'all');
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');

    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingIncome, setEditingIncome] = useState<AdditionalIncomeEntry | null>(null);
    const [deletingIncome, setDeletingIncome] = useState<AdditionalIncomeEntry | null>(null);

    // Form states
    const [formData, setFormData] = useState({
        title: '',
        category: 'laundry',
        amount: '',
        income_date: new Date().toISOString().split('T')[0],
        notes: '',
    });

    const handleFilterSubmit = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        router.get(
            '/additional-incomes',
            { search, category, start_date: startDate, end_date: endDate },
            { preserveState: true },
        );
    };

    const handleOpenCreate = () => {
        setFormData({
            title: '',
            category: 'laundry',
            amount: '',
            income_date: new Date().toISOString().split('T')[0],
            notes: '',
        });
        setIsCreateOpen(true);
    };

    const handleOpenEdit = (entry: AdditionalIncomeEntry) => {
        setEditingIncome(entry);
        setFormData({
            title: entry.title,
            category: entry.category,
            amount: String(entry.amount),
            income_date: entry.income_date,
            notes: entry.notes || '',
        });
    };

    const handleStoreSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/additional-incomes', formData, {
            onSuccess: () => {
                setIsCreateOpen(false);
            },
        });
    };

    const handleUpdateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingIncome) return;
        router.put(`/additional-incomes/${editingIncome.id}`, formData, {
            onSuccess: () => {
                setEditingIncome(null);
            },
        });
    };

    const handleDelete = () => {
        if (!deletingIncome) return;
        router.delete(`/additional-incomes/${deletingIncome.id}`, {
            onSuccess: () => {
                setDeletingIncome(null);
            },
        });
    };

    const categoryLabel = (catVal: string) => {
        const found = categories.find((c) => c.value === catVal);
        return found ? found.label : catVal;
    };

    return (
        <>
            <Head title="Non-Property Additional Income" />
            <div className="flex h-full flex-1 flex-col overflow-x-auto p-4 md:p-6 lg:p-8">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <span className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary shadow-xs">
                            <PlusCircle className="size-5" />
                        </span>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                                Additional (Non-Property) Income
                            </h1>
                            <p className="mt-0.5 text-xs text-muted-foreground sm:text-sm">
                                Record and manage extra revenues not tied to specific room leases (e.g. laundry, vending, parking, services).
                            </p>
                        </div>
                    </div>

                    <Button onClick={handleOpenCreate} className="gap-2 shadow-xs cursor-pointer">
                        <Plus className="size-4" />
                        Record Additional Income
                    </Button>
                </div>

                {/* Stat Cards */}
                <section className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div className="rounded-xl border border-border bg-card p-5 shadow-2xs">
                        <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground font-medium">
                            <span>Total Filtered Additional Income</span>
                            <Coins className="size-4 text-surface-green-foreground" />
                        </div>
                        <p className="text-2xl font-bold text-foreground tabular-nums">
                            {formatRupiah(total_amount)}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Aggregated revenue from selected filter criteria
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card p-5 shadow-2xs">
                        <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground font-medium">
                            <span>Total Entries</span>
                            <Wallet className="size-4 text-primary" />
                        </div>
                        <p className="text-2xl font-bold text-primary tabular-nums">
                            {incomes.total}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Recorded non-property transaction items
                        </p>
                    </div>
                </section>

                {/* Filter Control Bar */}
                <section className="mb-6 rounded-xl border border-border bg-card p-4 shadow-2xs">
                    <form onSubmit={handleFilterSubmit} className="flex flex-col gap-4">
                        <div className="grid gap-3 sm:grid-cols-12 sm:items-end">
                            {/* Search */}
                            <div className="space-y-1 sm:col-span-4">
                                <label className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                    <Search className="size-3.5" />
                                    Search Title / Description
                                </label>
                                <Input
                                    type="text"
                                    placeholder="Search e.g. Laundry, Vending..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="h-9 text-xs"
                                />
                            </div>

                            {/* Category */}
                            <div className="space-y-1 sm:col-span-3">
                                <label className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                    <Filter className="size-3.5" />
                                    Category
                                </label>
                                <select
                                    value={category}
                                    onChange={(e) => setCategory(e.target.value)}
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-2xs transition-colors focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    <option value="all">All Categories</option>
                                    {categories.map((c) => (
                                        <option key={c.value} value={c.value}>
                                            {c.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Start Date */}
                            <div className="space-y-1 sm:col-span-2">
                                <label className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                    <Calendar className="size-3.5" />
                                    From Date
                                </label>
                                <Input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="h-9 text-xs"
                                />
                            </div>

                            {/* End Date */}
                            <div className="space-y-1 sm:col-span-2">
                                <label className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                    <Calendar className="size-3.5" />
                                    To Date
                                </label>
                                <Input
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="h-9 text-xs"
                                />
                            </div>

                            {/* Submit */}
                            <div className="sm:col-span-1">
                                <Button type="submit" size="sm" variant="outline" className="w-full h-9 p-0 cursor-pointer">
                                    <RefreshCw className="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </form>
                </section>

                {/* Table */}
                <div className="rounded-xl border border-border bg-card shadow-2xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-border bg-muted/50 font-semibold text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3.5">Title / Description</th>
                                    <th className="px-4 py-3.5">Category</th>
                                    <th className="px-4 py-3.5 text-right">Amount</th>
                                    <th className="px-4 py-3.5">Income Date</th>
                                    <th className="px-4 py-3.5">Notes</th>
                                    <th className="px-4 py-3.5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {incomes.data.length > 0 ? (
                                    incomes.data.map((item) => (
                                        <tr key={item.id} className="hover:bg-muted/20 transition-colors">
                                            <td className="px-4 py-3.5 font-semibold text-foreground">
                                                {item.title}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <Badge variant="secondary" className="text-[11px] capitalize font-medium">
                                                    {categoryLabel(item.category)}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3.5 text-right font-bold text-surface-green-foreground tabular-nums">
                                                {formatRupiah(Number(item.amount))}
                                            </td>
                                            <td className="px-4 py-3.5 text-muted-foreground font-mono">
                                                {item.income_date}
                                            </td>
                                            <td className="px-4 py-3.5 text-muted-foreground max-w-xs truncate">
                                                {item.notes || '—'}
                                            </td>
                                            <td className="px-4 py-3.5 text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="xs" className="size-7 p-0 cursor-pointer">
                                                            <MoreHorizontal className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" className="text-xs">
                                                        <DropdownMenuItem onClick={() => handleOpenEdit(item)} className="gap-2 cursor-pointer">
                                                            <Edit2 className="size-3.5 text-primary" />
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem onClick={() => setDeletingIncome(item)} className="gap-2 text-destructive cursor-pointer">
                                                            <Trash2 className="size-3.5" />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">
                                            No additional income entries recorded. Click <strong>"Record Additional Income"</strong> to add one.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Create Dialog */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Record Additional Income</DialogTitle>
                        <DialogDescription>
                            Add a new non-property income entry (e.g. laundry, vending machine, service fees).
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleStoreSubmit} className="space-y-4 py-2">
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-foreground">Income Title / Description *</label>
                            <Input
                                required
                                placeholder="e.g. Laundry Service Collection"
                                value={formData.title}
                                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                                className="text-xs"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <label className="text-xs font-medium text-foreground">Category *</label>
                                <select
                                    value={formData.category}
                                    onChange={(e) => setFormData({ ...formData, category: e.target.value })}
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-2xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    {categories.map((c) => (
                                        <option key={c.value} value={c.value}>
                                            {c.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-medium text-foreground">Amount (Rp) *</label>
                                <Input
                                    type="number"
                                    required
                                    min="0"
                                    step="1000"
                                    placeholder="e.g. 500000"
                                    value={formData.amount}
                                    onChange={(e) => setFormData({ ...formData, amount: e.target.value })}
                                    className="text-xs tabular-nums"
                                />
                            </div>
                        </div>

                        <div className="space-y-1">
                            <label className="text-xs font-medium text-foreground">Income Date *</label>
                            <Input
                                type="date"
                                required
                                value={formData.income_date}
                                onChange={(e) => setFormData({ ...formData, income_date: e.target.value })}
                                className="text-xs"
                            />
                        </div>

                        <div className="space-y-1">
                            <label className="text-xs font-medium text-foreground">Notes (Optional)</label>
                            <Textarea
                                placeholder="Additional details or reference..."
                                value={formData.notes}
                                onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                                className="text-xs min-h-[60px]"
                            />
                        </div>

                        <DialogFooter className="pt-2">
                            <Button type="button" variant="outline" size="sm" onClick={() => setIsCreateOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" size="sm">
                                Save Income
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={!!editingIncome} onOpenChange={(open) => !open && setEditingIncome(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit Additional Income</DialogTitle>
                        <DialogDescription>
                            Update the non-property income entry details.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleUpdateSubmit} className="space-y-4 py-2">
                        <div className="space-y-1">
                            <label className="text-xs font-medium text-foreground">Income Title / Description *</label>
                            <Input
                                required
                                value={formData.title}
                                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                                className="text-xs"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <label className="text-xs font-medium text-foreground">Category *</label>
                                <select
                                    value={formData.category}
                                    onChange={(e) => setFormData({ ...formData, category: e.target.value })}
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-2xs focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    {categories.map((c) => (
                                        <option key={c.value} value={c.value}>
                                            {c.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-medium text-foreground">Amount (Rp) *</label>
                                <Input
                                    type="number"
                                    required
                                    min="0"
                                    step="1000"
                                    value={formData.amount}
                                    onChange={(e) => setFormData({ ...formData, amount: e.target.value })}
                                    className="text-xs tabular-nums"
                                />
                            </div>
                        </div>

                        <div className="space-y-1">
                            <label className="text-xs font-medium text-foreground">Income Date *</label>
                            <Input
                                type="date"
                                required
                                value={formData.income_date}
                                onChange={(e) => setFormData({ ...formData, income_date: e.target.value })}
                                className="text-xs"
                            />
                        </div>

                        <div className="space-y-1">
                            <label className="text-xs font-medium text-foreground">Notes (Optional)</label>
                            <Textarea
                                value={formData.notes}
                                onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                                className="text-xs min-h-[60px]"
                            />
                        </div>

                        <DialogFooter className="pt-2">
                            <Button type="button" variant="outline" size="sm" onClick={() => setEditingIncome(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" size="sm">
                                Update Income
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <Dialog open={!!deletingIncome} onOpenChange={(open) => !open && setDeletingIncome(null)}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Delete Income Entry</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete <strong>"{deletingIncome?.title}"</strong>? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 pt-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => setDeletingIncome(null)}>
                            Cancel
                        </Button>
                        <Button type="button" variant="destructive" size="sm" onClick={handleDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
