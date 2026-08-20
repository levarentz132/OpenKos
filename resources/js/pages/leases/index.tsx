import { Head, Link, router } from '@inertiajs/react';
import {
    ExternalLink,
    Eye,
    LogOut,
    EllipsisVertical,
    Pencil,
    RefreshCw,
    Building2,
    Banknote,
    AlertTriangle,
    Clock3,
    Calculator,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import {
    LeaseDetailSheet,
    LeaseEditSheet,
    MoveOutSheet,
    RenewLeaseSheet,
} from '@/components/features';
import { Heading } from '@/components/shared';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { useTable } from '@/hooks/use-table';
import { DUE_DAY_LABELS } from '@/lib/constants';
import { formatDate, formatPrice } from '@/lib/formatters';
import leases from '@/routes/leases';
import units from '@/routes/properties/units';
import type { AvailableUnit, Lease, PaginatedData, TableMeta } from '@/types';

type PageProps = {
    leases: PaginatedData<Lease>;
    availableUnits: AvailableUnit[];
    sort?: string;
    search?: string;
    status?: string;
    properties?: string;
    per_page?: number;
    payment_status?: string;
    table: TableMeta;
    stats?: {
        active_leases: number;
        collected_this_month: number;
        overdue_amount: number;
        pending_payment_verification: number;
    };
};

export default function Index({
    leases: data,
    availableUnits: _availableUnits,
    sort: currentSort = 'status,-start_date',
    search: currentSearch = '',
    status: currentStatus = '',
    properties: currentProperties = '',
    payment_status: currentPaymentStatus = '',
    per_page: currentPerPage = 15,
    table: tableMeta,
    stats,
}: PageProps) {
    const [detailLease, setDetailLease] = useState<Lease | null>(null);
    const [detailOpen, setDetailOpen] = useState(false);
    const [editOpen, setEditOpen] = useState(false);
    const [moveOutOpen, setMoveOutOpen] = useState(false);
    const [renewOpen, setRenewOpen] = useState(false);
    const [calculatingFees, setCalculatingFees] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [leaseToDelete, setLeaseToDelete] = useState<Lease | null>(null);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [bulkDeleteDialogOpen, setBulkDeleteDialogOpen] = useState(false);

    function handleSelectAll(checked: boolean) {
        if (checked) {
            setSelectedIds(data.data.map((l) => l.id));
        } else {
            setSelectedIds([]);
        }
    }

    function handleSelectRow(id: string | number, checked: boolean) {
        const numId = Number(id);
        if (checked) {
            setSelectedIds((prev) => [...prev, numId]);
        } else {
            setSelectedIds((prev) => prev.filter((i) => i !== numId));
        }
    }

    function handleBulkDelete() {
        if (selectedIds.length === 0) return;
        router.post(
            '/leases/bulk-delete',
            { ids: selectedIds },
            {
                onSuccess: () => {
                    setSelectedIds([]);
                    setBulkDeleteDialogOpen(false);
                },
            },
        );
    }

    function handleDeleteLease() {
        if (!leaseToDelete) return;
        router.delete(`/leases/${leaseToDelete.id}/delete`, {
            onSuccess: () => {
                setDeleteOpen(false);
                setLeaseToDelete(null);
            },
        });
    }

    function handleCalculateLateFees() {
        setCalculatingFees(true);
        router.post(
            '/leases/invoices/calculate-late-fees',
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setCalculatingFees(false),
            },
        );
    }

    const table = useTable({
        routeFn: () => leases.index(),
        params: {
            sort: currentSort,
            search: currentSearch,
            per_page: String(currentPerPage),
            status: currentStatus,
            properties: currentProperties,
            payment_status: currentPaymentStatus,
        },
        defaults: {
            sort: 'status,-start_date',
            per_page: '15',
        },
    });

    function openDetail(lease: Lease) {
        setDetailLease(lease);
        setDetailOpen(true);
    }

    function openMoveOutFromDetail() {
        setDetailOpen(false);
        setMoveOutOpen(true);
    }

    const columns: TableColumn<Lease>[] = [
        {
            key: 'reference',
            label: 'Reference',
            className: 'font-mono text-xs',
            render: (lease) => (
                <div className="flex items-center gap-2">
                    <span>{lease.reference ?? '\u2014'}</span>
                    {lease.pending_payment_review_count ? (
                        <span
                            className="relative inline-flex size-2"
                            title={`${lease.pending_payment_review_count} payment review pending`}
                        >
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-surface-purple-foreground opacity-75" />
                            <span className="relative inline-flex size-2 rounded-full bg-surface-purple-foreground" />
                        </span>
                    ) : null}
                </div>
            ),
        },
        {
            key: '_tenant',
            label: 'Tenant',
            className: 'font-medium',
            render: (lease) => (
                <div>
                    <p className="font-medium">
                        {(lease.tenants ?? []).length > 0
                            ? lease.tenants.map((t) => t.name).join(', ') ||
                              lease.tenants[0]?.name
                            : (lease.primary_tenant?.name ?? '\u2014')}
                    </p>
                    {lease.unit && (
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            <Link
                                href={units.index({
                                    property: lease.unit.property!.slug,
                                })}
                                onClick={(e: React.MouseEvent) =>
                                    e.stopPropagation()
                                }
                                className="text-primary hover:underline"
                            >
                                {lease.unit.name}
                            </Link>
                            {' · '}
                            {lease.unit.property?.name ?? '\u2014'}
                        </p>
                    )}
                </div>
            ),
        },
        {
            key: 'start_date',
            label: 'Start',
            sortable: true,
            className: 'tabular-nums',
            render: (lease) => formatDate(lease.start_date),
        },
        {
            key: 'end_date',
            label: 'End',
            sortable: true,
            className: 'text-muted-foreground tabular-nums',
            render: (lease) => formatDate(lease.end_date),
        },
        {
            key: 'rent_amount',
            label: 'Rent',
            sortable: true,
            className: 'tabular-nums',
            render: (lease) =>
                `${formatPrice(lease.rent_amount)} ${lease.billing_label ?? ''}`,
        },
        {
            key: 'rent_due_day',
            label: 'Due',
            sortable: true,
            className: 'tabular-nums',
            render: (lease) =>
                lease.status === 'active'
                    ? (DUE_DAY_LABELS[lease.rent_due_day] ??
                      `${lease.rent_due_day}th`)
                    : '—',
        },
        {
            key: 'payment_status',
            label: 'Payment',
            render: (lease) =>
                lease.status === 'active' && lease.payment_status ? (
                    <StatusBadge domain="rent" value={lease.payment_status} />
                ) : (
                    '—'
                ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (lease) => (
                <StatusBadge domain="lease" value={lease.status} />
            ),
        },
        {
            key: '_actions',
            label: '',
            render: (lease) => (
                <DropdownMenu>
                    <DropdownMenuTrigger
                        asChild
                        onClick={(e: React.MouseEvent) => e.stopPropagation()}
                    >
                        <Button variant="ghost" size="icon" className="size-8">
                            <EllipsisVertical className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        onClick={(e: React.MouseEvent) => e.stopPropagation()}
                    >
                        <DropdownMenuItem
                            onClick={() => router.get(leases.show.url(lease))}
                        >
                            <ExternalLink className="size-4" />
                            Open Workspace
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => openDetail(lease)}>
                            <Eye className="size-4" />
                            View
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onClick={() => {
                                setDetailLease(lease);
                                setDetailOpen(false);
                                setEditOpen(true);
                            }}
                        >
                            <Pencil className="size-4" />
                            Edit
                        </DropdownMenuItem>
                        {lease.status === 'active' && (
                            <>
                                <DropdownMenuItem
                                    onClick={() => {
                                        setDetailLease(lease);
                                        setDetailOpen(false);
                                        setRenewOpen(true);
                                    }}
                                >
                                    <RefreshCw className="size-4" />
                                    Renew
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    onClick={() => {
                                        setDetailLease(lease);
                                        setDetailOpen(false);
                                        setMoveOutOpen(true);
                                    }}
                                >
                                    <LogOut className="size-4" />
                                    Move Out
                                </DropdownMenuItem>
                            </>
                        )}
                        <DropdownMenuItem
                            variant="destructive"
                            onClick={() => {
                                setLeaseToDelete(lease);
                                setDeleteOpen(true);
                            }}
                        >
                            <Trash2 className="size-4" />
                            Delete Lease
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title="Leases" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Leases"
                        description="View all leases across properties"
                    />
                </div>

                {stats && (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4">
                        <Card>
                            <CardContent className="flex items-center gap-4 px-6">
                                <Building2 className="size-10 shrink-0 text-blue-600" />
                                <div className="min-w-0">
                                    <p className="text-sm text-muted-foreground">
                                        Active Leases
                                    </p>
                                    <p className="truncate text-2xl font-bold tabular-nums">
                                        {stats.active_leases}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="flex items-center gap-4 px-6">
                                <Banknote className="size-10 shrink-0 text-green-600" />
                                <div className="min-w-0">
                                    <p className="text-sm text-muted-foreground">
                                        Collected This Month
                                    </p>
                                    <p className="truncate text-2xl font-bold tabular-nums">
                                        {formatPrice(
                                            String(stats.collected_this_month),
                                        )}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="flex items-center justify-between gap-4 px-6">
                                <div className="flex items-center gap-4 min-w-0">
                                    <AlertTriangle className="size-10 shrink-0 text-red-600" />
                                    <div className="min-w-0">
                                        <p className="text-sm text-muted-foreground">
                                            Overdue
                                        </p>
                                        <p className="truncate text-2xl font-bold tabular-nums">
                                            {formatPrice(
                                                String(stats.overdue_amount),
                                            )}
                                        </p>
                                    </div>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={calculatingFees}
                                    onClick={handleCalculateLateFees}
                                    className="gap-2 shrink-0"
                                >
                                    <Calculator className="h-4 w-4 text-red-600" />
                                    {calculatingFees ? 'Calculating...' : 'Calculate Fees'}
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="flex items-center gap-4 px-6">
                                <Clock3 className="size-10 shrink-0 text-violet-600" />
                                <div className="min-w-0">
                                    <p className="text-sm text-muted-foreground">
                                        Pending Review
                                    </p>
                                    <p className="truncate text-2xl font-bold tabular-nums">
                                        {stats.pending_payment_verification}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {selectedIds.length > 0 && (
                    <div className="flex items-center justify-between rounded-lg border bg-muted/60 px-4 py-2.5 shadow-xs">
                        <span className="text-sm font-medium text-foreground">
                            {selectedIds.length} lease{selectedIds.length > 1 ? 's' : ''} selected
                        </span>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setSelectedIds([])}
                            >
                                Clear Selection
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() => setBulkDeleteDialogOpen(true)}
                                className="gap-2"
                            >
                                <Trash2 className="size-4" />
                                Delete Selected ({selectedIds.length})
                            </Button>
                        </div>
                    </div>
                )}

                <FilterBar
                    filters={tableMeta.filters}
                    activeFilters={table.activeFilters}
                    activeFilterCount={table.activeFilterCount}
                    onToggleOption={table.toggleFilterOption}
                    onClearAll={table.clearAllFilters}
                    searchInput={
                        <SearchInput
                            value={table.searchValue}
                            onChange={table.onSearchChange}
                            onClear={table.clearSearch}
                            placeholder="Search tenant, unit, property..."
                        />
                    }
                />

                <DataTable
                    columns={columns}
                    rows={data.data}
                    currentSort={currentSort}
                    onSort={table.toggleSort}
                    onRowClick={openDetail}
                    paginator={data}
                    perPage={currentPerPage}
                    onPageChange={table.goToPage}
                    onPerPageChange={table.setPerPage}
                    noun="leases"
                    empty={{
                        message: 'No leases found.',
                    }}
                    selectable
                    selectedIds={selectedIds}
                    onSelectAll={handleSelectAll}
                    onSelectRow={handleSelectRow}
                />
            </div>

            <LeaseDetailSheet
                lease={detailLease}
                open={detailOpen}
                onOpenChange={setDetailOpen}
                onEdit={() => {
                    setDetailOpen(false);
                    setEditOpen(true);
                }}
                onDelete={() => {
                    setLeaseToDelete(detailLease);
                    setDetailOpen(false);
                    setDeleteOpen(true);
                }}
                onMoveOut={
                    detailLease?.status === 'active'
                        ? openMoveOutFromDetail
                        : undefined
                }
            />

            <MoveOutSheet
                lease={
                    detailLease
                        ? {
                              id: detailLease.id,
                              tenants: detailLease.tenants,
                              primary_tenant: detailLease.primary_tenant,
                              unit: detailLease.unit,
                          }
                        : null
                }
                availableUnits={_availableUnits}
                open={moveOutOpen}
                onOpenChange={setMoveOutOpen}
            />

            <RenewLeaseSheet
                key={detailLease?.id ?? 'renew'}
                lease={detailLease}
                open={renewOpen}
                onOpenChange={setRenewOpen}
            />

            <LeaseEditSheet
                key={detailLease?.id ?? 'edit'}
                lease={detailLease}
                open={editOpen}
                onOpenChange={setEditOpen}
            />

            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Lease</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to permanently delete lease{' '}
                            <span className="font-mono font-medium">
                                {leaseToDelete?.reference ?? `#${leaseToDelete?.id}`}
                            </span>
                            ? This will remove all associated invoices and payments.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDeleteLease}>
                            Delete Lease
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={bulkDeleteDialogOpen} onOpenChange={setBulkDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Selected Leases</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to permanently delete{' '}
                            <span className="font-semibold">{selectedIds.length}</span> selected lease{selectedIds.length > 1 ? 's' : ''}?
                            This action will remove all associated invoices and payment records.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setBulkDeleteDialogOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleBulkDelete}>
                            Delete {selectedIds.length} Lease{selectedIds.length > 1 ? 's' : ''}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        {
            title: 'Leases',
            href: leases.index(),
        },
    ],
};
