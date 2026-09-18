import { Head, router, usePage } from '@inertiajs/react';
import {
    Archive,
    DoorOpen,
    EllipsisVertical,
    ExternalLink,
    Eye,
    MailPlus,
    Pencil,
    RotateCcw,
    Send,
    Trash2,
    UserX,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { TableColumn } from '@/components/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { SearchInput } from '@/components/data-table/search-input';
import {
    AssignUnitSheet,
    MoveOutSheet,
    TenantDetailSheet,
    TenantDocumentsSheet,
    TenantFormSheet,
} from '@/components/features';
import InviteToAppSheet from '@/components/features/tenants/invite-to-app-sheet';
import { Heading } from '@/components/shared';
import { StatusBadge } from '@/components/shared/status-badge';
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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTable } from '@/hooks/use-table';
import { appAccessStatus, inviteActionLabel } from '@/lib/app-access';
import tenants from '@/routes/tenants';
import type {
    Auth,
    AvailableUnit,
    Lease,
    PaginatedData,
    TableMeta,
    WorkspaceTenant,
} from '@/types';

type PageProps = {
    tenants: PaginatedData<WorkspaceTenant>;
    availableUnits: AvailableUnit[];
    sort?: string;
    search?: string;
    status?: string;
    per_page?: number;
    table: TableMeta;
};

export default function Index({
    tenants: data,
    availableUnits: _availableUnits,
    sort: currentSort = 'name',
    search: currentSearch = '',
    status: currentStatus = '',
    per_page: currentPerPage = 15,
    table: tableMeta,
}: PageProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const permissions = auth.permissions;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingTenant, setEditingTenant] = useState<WorkspaceTenant | null>(
        null,
    );

    const [detailOpen, setDetailOpen] = useState(false);
    const [viewingTenant, setViewingTenant] = useState<WorkspaceTenant | null>(
        null,
    );

    const [assignUnitOpen, setAssignUnitOpen] = useState(false);
    const [assignTenant, setAssignTenant] = useState<WorkspaceTenant | null>(
        null,
    );

    const [moveOutOpen, setMoveOutOpen] = useState(false);
    const [moveOutTenant, setMoveOutTenant] = useState<WorkspaceTenant | null>(
        null,
    );

    const [documentsOpen, setDocumentsOpen] = useState(false);
    const [documentsTenant, setDocumentsTenant] =
        useState<WorkspaceTenant | null>(null);

    const [archiveConfirm, setArchiveConfirm] =
        useState<WorkspaceTenant | null>(null);

    const [deleteConfirm, setDeleteConfirm] =
        useState<{ tenant: WorkspaceTenant; force: boolean } | null>(null);

    const [disableConfirm, setDisableConfirm] =
        useState<WorkspaceTenant | null>(null);

    const [inviteTenant, setInviteTenant] = useState<WorkspaceTenant | null>(
        null,
    );

    const table = useTable({
        routeFn: () => tenants.index(),
        params: {
            sort: currentSort,
            search: currentSearch,
            per_page: String(currentPerPage),
            status: currentStatus,
        },
        defaults: {
            sort: 'name',
            per_page: '15',
        },
    });

    function openCreate() {
        setEditingTenant(null);
        setDialogOpen(true);
    }

    function openEdit(tenant: WorkspaceTenant) {
        setEditingTenant(tenant);
        setDialogOpen(true);
    }

    function openDetail(tenant: WorkspaceTenant) {
        setViewingTenant(tenant);
        setDetailOpen(true);
    }

    function editFromDetail() {
        if (!viewingTenant) {
            return;
        }

        setEditingTenant(viewingTenant);
        setDetailOpen(false);
        setDialogOpen(true);
    }

    function openAssignUnit() {
        if (!viewingTenant) {
            return;
        }

        setAssignTenant(viewingTenant);
        setDetailOpen(false);
        setAssignUnitOpen(true);
    }

    function openMoveOut() {
        if (!viewingTenant) {
            return;
        }

        setMoveOutTenant(viewingTenant);
        setDetailOpen(false);
        setMoveOutOpen(true);
    }

    function openDocuments() {
        if (!viewingTenant) {
            return;
        }

        setDocumentsTenant(viewingTenant);
        setDetailOpen(false);
        setDocumentsOpen(true);
    }

    function archive(tenant: WorkspaceTenant) {
        setArchiveConfirm(tenant);
    }

    function confirmArchive() {
        if (!archiveConfirm) {
            return;
        }

        router.delete(tenants.destroy.url(archiveConfirm));
        setArchiveConfirm(null);
    }

    function deleteTenant(tenant: WorkspaceTenant, force = false) {
        setDeleteConfirm({ tenant, force });
    }

    function confirmDelete() {
        if (!deleteConfirm) {
            return;
        }

        router.delete(tenants.destroy.url(deleteConfirm.tenant), {
            data: deleteConfirm.force ? { force: true } : undefined,
            onFinish: () => setDeleteConfirm(null),
        });
    }

    function restore(tenant: WorkspaceTenant) {
        router.post(tenants.restore.url(tenant));
    }

    function confirmDisable() {
        if (!disableConfirm) {
            return;
        }

        router.post(tenants.disableAccess(disableConfirm.id).url);
        setDisableConfirm(null);
    }

    function resendInvitation(tenant: WorkspaceTenant) {
        router.post(tenants.resendInvitation(tenant.id).url);
    }

    const columns: TableColumn<WorkspaceTenant>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            className: 'font-medium',
        },
        {
            key: 'phone',
            label: 'Phone',
            sortable: true,
            render: (t) => {
                if (!t.phone) {
                    return <span className="text-muted-foreground">—</span>;
                }

                const isVerified = Boolean(t.phone_verified_at);

                return (
                    <div className="flex flex-col gap-1 items-start sm:flex-row sm:items-center sm:gap-2">
                        <span className="text-muted-foreground">{t.phone}</span>
                        {isVerified ? (
                            <Badge
                                variant="outline"
                                className="h-5 px-1.5 text-[11px] font-medium bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800"
                            >
                                Verified
                            </Badge>
                        ) : (
                            <Badge
                                variant="outline"
                                className="h-5 px-1.5 text-[11px] font-medium bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800"
                            >
                                Unverified
                            </Badge>
                        )}
                    </div>
                );
            },
        },
        {
            key: '_lease',
            label: 'Lease',
            render: (t) =>
                (t.active_leases_count ?? 0) > 0 ? (
                    <StatusBadge status="active" />
                ) : (
                    <Badge variant="outline">None</Badge>
                ),
        },
        {
            key: '_status',
            label: 'Status',
            render: (t) => {
                const status = t.deleted_at
                    ? 'archived'
                    : t.is_active
                      ? 'active'
                      : 'inactive';

                return <StatusBadge domain="tenant" value={status} />;
            },
        },
        {
            key: '_app_access',
            label: 'App Access',
            render: (t) => {
                const status = appAccessStatus(t.user);

                return status === 'none' ? (
                    <span className="text-muted-foreground">—</span>
                ) : (
                    <StatusBadge domain="app_access" value={status} />
                );
            },
        },
        {
            key: '_actions',
            label: '',
            render: (t) => (
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
                            onClick={() => router.get(tenants.show.url(t))}
                        >
                            <ExternalLink className="size-4" />
                            Open Workspace
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => openDetail(t)}>
                            <Eye className="size-4" />
                            View
                        </DropdownMenuItem>
                        {!t.deleted_at && t.active_leases_count === 0 && (
                            <DropdownMenuItem
                                onClick={() => {
                                    setAssignTenant(t);
                                    setAssignUnitOpen(true);
                                }}
                            >
                                <DoorOpen className="size-4" />
                                Assign to Unit
                            </DropdownMenuItem>
                        )}
                        {!t.deleted_at && (
                            <DropdownMenuItem onClick={() => openEdit(t)}>
                                <Pencil className="size-4" />
                                Edit
                            </DropdownMenuItem>
                        )}

                        {!t.deleted_at && <DropdownMenuSeparator />}

                        {permissions.includes('tenants.invite') &&
                            !t.deleted_at &&
                            !t.user_id && (
                                <DropdownMenuItem
                                    onClick={() => setInviteTenant(t)}
                                >
                                    <MailPlus className="size-4" />
                                    Invite to App
                                </DropdownMenuItem>
                            )}
                        {permissions.includes('tenants.invite') &&
                            !t.deleted_at &&
                            inviteActionLabel(appAccessStatus(t.user)) && (
                                <DropdownMenuItem
                                    onClick={() => resendInvitation(t)}
                                >
                                    <Send className="size-4" />
                                    {inviteActionLabel(appAccessStatus(t.user))}
                                </DropdownMenuItem>
                            )}
                        {permissions.includes('tenants.invite') &&
                            !t.deleted_at &&
                            ['invited', 'active'].includes(
                                appAccessStatus(t.user),
                            ) && (
                                <DropdownMenuItem
                                    variant="destructive"
                                    onClick={() => setDisableConfirm(t)}
                                >
                                    <UserX className="size-4" />
                                    Disable Access
                                </DropdownMenuItem>
                            )}

                        <DropdownMenuSeparator />

                        {t.deleted_at ? (
                            <>
                                <DropdownMenuItem onClick={() => restore(t)}>
                                    <RotateCcw className="size-4" />
                                    Restore
                                </DropdownMenuItem>
                                {permissions.includes('tenants.delete') && (
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onClick={() => deleteTenant(t, true)}
                                    >
                                        <Trash2 className="size-4" />
                                        Delete Permanently
                                    </DropdownMenuItem>
                                )}
                            </>
                        ) : (
                            <>
                                <DropdownMenuItem onClick={() => archive(t)}>
                                    <Archive className="size-4" />
                                    Archive
                                </DropdownMenuItem>
                                {permissions.includes('tenants.delete') && (
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onClick={() => deleteTenant(t, false)}
                                    >
                                        <Trash2 className="size-4" />
                                        Delete
                                    </DropdownMenuItem>
                                )}
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title="Tenants" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Tenants"
                        description="Manage your tenants"
                    />

                    <Button onClick={openCreate}>New Tenant</Button>
                </div>

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
                            placeholder="Search by name, phone, or ID card..."
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
                    noun="tenants"
                    empty={{
                        message: 'No tenants yet.',
                        createLabel: 'Create your first tenant',
                        onCreate: openCreate,
                    }}
                />
            </div>

            <TenantDetailSheet
                tenant={viewingTenant}
                open={detailOpen}
                onOpenChange={setDetailOpen}
                onEdit={editFromDetail}
                onAssignToUnit={openAssignUnit}
                onMoveOut={openMoveOut}
                onDocuments={openDocuments}
                onInvite={
                    viewingTenant?.user_id ||
                    !permissions.includes('tenants.invite')
                        ? undefined
                        : () => {
                              if (viewingTenant) {
                                  setDetailOpen(false);
                                  setInviteTenant(viewingTenant);
                              }
                          }
                }
                onResend={
                    viewingTenant && permissions.includes('tenants.invite')
                        ? () => resendInvitation(viewingTenant)
                        : undefined
                }
                onDisableAccess={
                    viewingTenant && permissions.includes('tenants.invite')
                        ? () => {
                              setDetailOpen(false);
                              setDisableConfirm(viewingTenant);
                          }
                        : undefined
                }
            />

            <TenantFormSheet
                key={editingTenant?.id ?? 'new'}
                tenant={editingTenant}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
            />

            {assignTenant && (
                <AssignUnitSheet
                    tenant={assignTenant}
                    availableUnits={_availableUnits}
                    open={assignUnitOpen}
                    onOpenChange={setAssignUnitOpen}
                />
            )}

            <TenantDocumentsSheet
                tenant={documentsTenant}
                open={documentsOpen}
                onOpenChange={setDocumentsOpen}
            />

            <MoveOutSheet
                lease={
                    moveOutTenant
                        ? {
                              id:
                                  (
                                      moveOutTenant as WorkspaceTenant & {
                                          leases?: Lease[];
                                      }
                                  ).leases?.[0]?.id ?? 0,
                              tenants: [
                                  {
                                      id: moveOutTenant.id,
                                      name: moveOutTenant.name,
                                      phone: moveOutTenant.phone,
                                      pivot: { is_primary: true },
                                  },
                              ],
                              primary_tenant: {
                                  id: moveOutTenant.id,
                                  name: moveOutTenant.name,
                                  phone: moveOutTenant.phone,
                              },
                              unit:
                                  (
                                      moveOutTenant as WorkspaceTenant & {
                                          leases?: Lease[];
                                      }
                                  ).leases?.[0]?.unit ?? null,
                          }
                        : null
                }
                availableUnits={_availableUnits}
                open={moveOutOpen}
                onOpenChange={setMoveOutOpen}
            />

            <InviteToAppSheet
                tenantId={inviteTenant?.id ?? null}
                open={inviteTenant !== null}
                onOpenChange={(open) => !open && setInviteTenant(null)}
            />

            <Dialog
                open={archiveConfirm !== null}
                onOpenChange={() => setArchiveConfirm(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Archive tenant</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to archive{' '}
                            <span className="font-medium">
                                {archiveConfirm?.name}
                            </span>
                            ?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setArchiveConfirm(null)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmArchive}>
                            Archive
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={disableConfirm !== null}
                onOpenChange={() => setDisableConfirm(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Disable app access</DialogTitle>
                        <DialogDescription>
                            This signs{' '}
                            <span className="font-medium">
                                {disableConfirm?.name}
                            </span>{' '}
                            out and revokes their portal access. They'll still
                            receive notifications, and you can re-invite them
                            later.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDisableConfirm(null)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDisable}>
                            Disable Access
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={deleteConfirm !== null}
                onOpenChange={() => setDeleteConfirm(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {deleteConfirm?.force
                                ? 'Permanently delete tenant'
                                : 'Delete tenant'}
                        </DialogTitle>
                        <DialogDescription>
                            {deleteConfirm?.force
                                ? `Are you sure you want to permanently delete ${deleteConfirm?.tenant?.name}? This action cannot be undone.`
                                : `Are you sure you want to delete ${deleteConfirm?.tenant?.name}?`}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteConfirm(null)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            {deleteConfirm?.force
                                ? 'Delete Permanently'
                                : 'Delete'}
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
            title: 'Tenants',
            href: tenants.index(),
        },
    ],
};
