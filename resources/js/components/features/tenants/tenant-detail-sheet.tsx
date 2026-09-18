import { router } from '@inertiajs/react';
import { MailPlus, Send, UserX } from 'lucide-react';
import { useState } from 'react';
import { StatusBadge } from '@/components/shared/status-badge';
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
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { appAccessStatus, inviteActionLabel } from '@/lib/app-access';
import { formatDate, formatPrice } from '@/lib/formatters';
import tenants from '@/routes/tenants';
import type { Lease, TenantDocument, WorkspaceTenant } from '@/types';

export default function TenantDetailSheet({
    tenant,
    open,
    onOpenChange,
    onEdit,
    onDocuments,
    onAssignToUnit,
    onMoveOut,
    onInvite,
    onResend,
    onDisableAccess,
}: {
    tenant?:
        | (WorkspaceTenant & { leases?: Lease[]; documents?: TenantDocument[] })
        | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onEdit: () => void;
    onDocuments: () => void;
    onAssignToUnit?: () => void;
    onMoveOut?: () => void;
    onInvite?: () => void;
    onResend?: () => void;
    onDisableAccess?: () => void;
}) {
    const [archiveConfirm, setArchiveConfirm] = useState(false);
    const [deleteConfirm, setDeleteConfirm] = useState(false);

    function archive() {
        if (!tenant) {
            return;
        }

        setArchiveConfirm(true);
    }

    function confirmArchive() {
        if (!tenant) {
            return;
        }

        router.delete(tenants.destroy.url(tenant), {
            onSuccess: () => onOpenChange(false),
        });
        setArchiveConfirm(false);
    }

    function confirmDelete() {
        if (!tenant) {
            return;
        }

        router.delete(tenants.destroy.url(tenant), {
            data: isArchived ? { force: true } : undefined,
            onSuccess: () => onOpenChange(false),
        });
        setDeleteConfirm(false);
    }

    function restore() {
        if (!tenant) {
            return;
        }

        router.post(tenants.restore.url(tenant), {}, {
            onSuccess: () => onOpenChange(false),
        });
    }

    const activeLease = tenant?.leases?.[0];
    const isArchived = Boolean(tenant?.deleted_at);

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                className="sm:max-w-lg"
                expandTo={tenant ? tenants.show.url(tenant) : undefined}
            >
                <SheetHeader>
                    <SheetTitle>{tenant?.name}</SheetTitle>
                    <SheetDescription>Tenant details</SheetDescription>
                </SheetHeader>

                {tenant && (
                    <div className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                        <div className="space-y-5">
                            <div className="flex flex-wrap items-center gap-2">
                                <span>Status:</span>
                                {(() => {
                                    const status = isArchived
                                        ? 'archived'
                                        : tenant.is_active
                                          ? 'active'
                                          : 'inactive';

                                    return (
                                        <StatusBadge
                                            domain="tenant"
                                            value={status}
                                        />
                                    );
                                })()}
                                <span className="ml-2">App access:</span>
                                <StatusBadge
                                    domain="app_access"
                                    value={appAccessStatus(tenant.user)}
                                />
                            </div>

                            <div className="rounded-lg border bg-muted/30 p-4">
                                <p className="mb-2 text-xs font-medium text-muted-foreground uppercase">
                                    Current Lease
                                </p>
                                {activeLease ? (
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-medium">
                                                {activeLease.unit?.name ??
                                                    'Unknown Unit'}
                                            </span>
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {activeLease.reference}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-muted-foreground">
                                                {activeLease.unit?.property
                                                    ?.name ??
                                                    'Unknown Property'}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {formatDate(
                                                    activeLease.start_date,
                                                )}
                                                {activeLease.end_date
                                                    ? ` — ${formatDate(activeLease.end_date)}`
                                                    : ' — Present'}
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {formatPrice(
                                                    activeLease.rent_amount,
                                                )}
                                                /mo
                                            </span>
                                        </div>
                                        {(activeLease.tenants ?? []).length >
                                            1 && (
                                            <div className="border-t pt-2">
                                                {activeLease.primary_tenant
                                                    ?.id === tenant.id ? (
                                                    <>
                                                        <p className="mb-1 text-xs text-muted-foreground">
                                                            Co-tenants
                                                        </p>
                                                        <div className="space-y-1">
                                                            {activeLease.tenants
                                                                .filter(
                                                                    (t) =>
                                                                        !t.pivot
                                                                            ?.is_primary,
                                                                )
                                                                .map((t) => (
                                                                    <div
                                                                        key={
                                                                            t.id
                                                                        }
                                                                        className="flex items-center justify-between text-sm"
                                                                    >
                                                                        <span>
                                                                            {
                                                                                t.name
                                                                            }
                                                                        </span>
                                                                        {t.phone && (
                                                                            <span className="text-xs text-muted-foreground">
                                                                                {
                                                                                    t.phone
                                                                                }
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                ))}
                                                        </div>
                                                    </>
                                                ) : activeLease.primary_tenant ? (
                                                    <>
                                                        <p className="mb-1 text-xs text-muted-foreground">
                                                            Main tenant
                                                        </p>
                                                        <div className="flex items-center justify-between text-sm">
                                                            <span>
                                                                {
                                                                    activeLease
                                                                        .primary_tenant
                                                                        .name
                                                                }
                                                            </span>
                                                            {activeLease
                                                                .primary_tenant
                                                                .phone && (
                                                                <span className="text-xs text-muted-foreground">
                                                                    {
                                                                        activeLease
                                                                            .primary_tenant
                                                                            .phone
                                                                    }
                                                                </span>
                                                            )}
                                                        </div>
                                                    </>
                                                ) : null}
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No active lease
                                    </p>
                                )}
                            </div>

                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase">
                                    Phone
                                </p>
                                <p className="mt-1 text-sm">
                                    {tenant.phone ?? '—'}
                                </p>
                            </div>

                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase">
                                    ID Card (KTP)
                                </p>
                                <p className="mt-1 text-sm tabular-nums">
                                    {tenant.id_card_number ?? '—'}
                                </p>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        Emergency Contact
                                    </p>
                                    <p className="mt-1 text-sm">
                                        {tenant.emergency_contact_name ?? '—'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        Emergency Phone
                                    </p>
                                    <p className="mt-1 text-sm tabular-nums">
                                        {tenant.emergency_contact_phone ?? '—'}
                                    </p>
                                </div>
                            </div>

                            {tenant.notes && (
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        Notes
                                    </p>
                                    <p className="mt-1 text-sm whitespace-pre-wrap">
                                        {tenant.notes}
                                    </p>
                                </div>
                            )}

                            {!isArchived && tenant && (
                                <Button
                                    variant="outline"
                                    onClick={onDocuments}
                                    className="w-full"
                                >
                                    Documents
                                </Button>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                            <Button
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                            >
                                Close
                            </Button>
                            {isArchived ? (
                                <>
                                    <Button variant="outline" onClick={restore}>
                                        Restore
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        onClick={() => setDeleteConfirm(true)}
                                    >
                                        Delete Permanently
                                    </Button>
                                </>
                            ) : (
                                tenant && (
                                    <>
                                        {!tenant.user_id && onInvite && (
                                            <Button
                                                variant="outline"
                                                onClick={onInvite}
                                            >
                                                <MailPlus className="size-4" />
                                                Invite to App
                                            </Button>
                                        )}
                                        {inviteActionLabel(
                                            appAccessStatus(tenant.user),
                                        ) &&
                                            onResend && (
                                                <Button
                                                    variant="outline"
                                                    onClick={onResend}
                                                >
                                                    <Send className="size-4" />
                                                    {inviteActionLabel(
                                                        appAccessStatus(
                                                            tenant.user,
                                                        ),
                                                    )}
                                                </Button>
                                            )}
                                        {['invited', 'active'].includes(
                                            appAccessStatus(tenant.user),
                                        ) &&
                                            onDisableAccess && (
                                                <Button
                                                    variant="outline"
                                                    onClick={onDisableAccess}
                                                >
                                                    <UserX className="size-4" />
                                                    Disable Access
                                                </Button>
                                            )}
                                        {!activeLease && onAssignToUnit && (
                                            <Button onClick={onAssignToUnit}>
                                                Assign to Unit
                                            </Button>
                                        )}
                                        {activeLease && onMoveOut && (
                                            <Button
                                                variant="destructive"
                                                onClick={onMoveOut}
                                            >
                                                Move Out
                                            </Button>
                                        )}
                                        <Button variant="outline" onClick={archive}>
                                            Archive
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            onClick={() => setDeleteConfirm(true)}
                                        >
                                            Delete
                                        </Button>
                                        <Button onClick={onEdit}>Edit</Button>
                                    </>
                                )
                            )}
                        </div>
                    </div>
                )}
            </SheetContent>

            <Dialog open={archiveConfirm} onOpenChange={setArchiveConfirm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Archive tenant</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to archive{' '}
                            <span className="font-medium">{tenant?.name}</span>?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setArchiveConfirm(false)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmArchive}>
                            Archive
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={deleteConfirm} onOpenChange={setDeleteConfirm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {isArchived ? 'Permanently delete tenant' : 'Delete tenant'}
                        </DialogTitle>
                        <DialogDescription>
                            {isArchived
                                ? `Are you sure you want to permanently delete ${tenant?.name}? This action cannot be undone.`
                                : `Are you sure you want to delete ${tenant?.name}?`}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteConfirm(false)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            {isArchived ? 'Delete Permanently' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Sheet>
    );
}
