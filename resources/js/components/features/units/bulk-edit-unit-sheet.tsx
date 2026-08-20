import { router } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import properties from '@/routes/properties';
import type { Property, Unit } from '@/types';

export default function BulkEditUnitSheet({
    property,
    units,
    selectedIds,
    open,
    onOpenChange,
    onSuccess,
}: {
    property: Property;
    units: Unit[];
    selectedIds: number[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSuccess?: () => void;
}) {
    const [targetIds, setTargetIds] = useState<number[]>(selectedIds);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const [enabledFields, setEnabledFields] = useState<Record<string, boolean>>({
        floor: false,
        capacity: false,
        status: false,
        monthly_rate: false,
        size_sqm: false,
        description: false,
        notes: false,
    });

    const [formData, setFormData] = useState({
        floor: '',
        capacity: '1',
        status: 'available',
        monthly_rate: '',
        size_sqm: '',
        description: '',
        notes: '',
    });

    useEffect(() => {
        if (open) {
            setTargetIds(selectedIds.length > 0 ? selectedIds : units.map((u) => u.id));
        }
    }, [open, selectedIds, units]);

    function toggleField(field: string, checked: boolean) {
        setEnabledFields((prev) => ({ ...prev, [field]: checked }));
    }

    function toggleSelectUnit(id: number, checked: boolean) {
        if (checked) {
            setTargetIds((prev) => [...prev, id]);
        } else {
            setTargetIds((prev) => prev.filter((item) => item !== id));
        }
    }

    function toggleSelectAllUnits(checked: boolean) {
        if (checked) {
            setTargetIds(units.map((u) => u.id));
        } else {
            setTargetIds([]);
        }
    }

    function resetForm() {
        setFormData({
            floor: '',
            capacity: '1',
            status: 'available',
            monthly_rate: '',
            size_sqm: '',
            description: '',
            notes: '',
        });
        setEnabledFields({
            floor: false,
            capacity: false,
            status: false,
            monthly_rate: false,
            size_sqm: false,
            description: false,
            notes: false,
        });
        setErrors({});
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();

        const activeFields = Object.keys(enabledFields).filter(
            (k) => enabledFields[k],
        );

        if (targetIds.length === 0 || activeFields.length === 0) {
            return;
        }

        const payload: Record<string, any> = {
            unit_ids: targetIds,
            fields: activeFields,
        };

        if (enabledFields.floor) payload.floor = formData.floor;
        if (enabledFields.capacity) payload.capacity = formData.capacity;
        if (enabledFields.status) payload.status = formData.status;
        if (enabledFields.monthly_rate) payload.monthly_rate = formData.monthly_rate;
        if (enabledFields.size_sqm) payload.size_sqm = formData.size_sqm;
        if (enabledFields.description) payload.description = formData.description;
        if (enabledFields.notes) payload.notes = formData.notes;

        setProcessing(true);
        router.put(properties.units.bulkUpdate.url({ property: property.slug }), payload, {
            onSuccess: () => {
                onOpenChange(false);
                resetForm();
                onSuccess?.();
            },
            onError: (errs) => {
                setErrors(errs as Record<string, string>);
            },
            onFinish: () => {
                setProcessing(false);
            },
        });
    }

    const activeFieldsCount = Object.values(enabledFields).filter(Boolean).length;
    const allUnitsSelected = units.length > 0 && targetIds.length === units.length;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Bulk Edit Units</SheetTitle>
                    <SheetDescription>
                        Update shared details across multiple units in {property.name}.
                    </SheetDescription>
                </SheetHeader>

                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6"
                >
                    <div className="grid gap-6">
                        {/* Target Unit Selection */}
                        <div className="space-y-2 rounded-lg border bg-muted/30 p-3">
                            <div className="flex items-center justify-between">
                                <Label className="font-semibold text-sm">
                                    Target Units ({targetIds.length} of {units.length} selected)
                                </Label>
                                <div className="flex items-center gap-2 text-xs">
                                    <Checkbox
                                        id="select_all_units"
                                        checked={allUnitsSelected}
                                        onCheckedChange={(c) => toggleSelectAllUnits(Boolean(c))}
                                    />
                                    <label htmlFor="select_all_units" className="cursor-pointer">
                                        Select All
                                    </label>
                                </div>
                            </div>

                            <div className="max-h-32 overflow-y-auto grid grid-cols-2 gap-2 pt-2">
                                {units.map((unit) => {
                                    const isChecked = targetIds.includes(unit.id);
                                    return (
                                        <div
                                            key={unit.id}
                                            className="flex items-center gap-2 rounded border px-2 py-1 text-xs bg-background"
                                        >
                                            <Checkbox
                                                id={`target_unit_${unit.id}`}
                                                checked={isChecked}
                                                onCheckedChange={(c) =>
                                                    toggleSelectUnit(unit.id, Boolean(c))
                                                }
                                            />
                                            <label
                                                htmlFor={`target_unit_${unit.id}`}
                                                className="truncate cursor-pointer"
                                            >
                                                {unit.name}
                                            </label>
                                        </div>
                                    );
                                })}
                            </div>
                            <InputError message={errors.unit_ids} />
                        </div>

                        <p className="text-xs text-muted-foreground">
                            Check the box next to any attribute you want to update. Unchecked fields will remain unchanged.
                        </p>

                        {/* Attribute Toggles & Controls */}
                        <div className="grid gap-4">
                            {/* Floor */}
                            <div className="grid gap-2 rounded-md border p-3">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="update_floor"
                                        checked={enabledFields.floor}
                                        onCheckedChange={(c) => toggleField('floor', Boolean(c))}
                                    />
                                    <Label htmlFor="update_floor" className="cursor-pointer font-medium">
                                        Update Floor
                                    </Label>
                                </div>
                                {enabledFields.floor && (
                                    <div className="pt-2">
                                        <Input
                                            id="floor"
                                            value={formData.floor}
                                            onChange={(e) =>
                                                setFormData((prev) => ({
                                                    ...prev,
                                                    floor: e.target.value,
                                                }))
                                            }
                                            placeholder="e.g. 2"
                                        />
                                        <InputError message={errors.floor} />
                                    </div>
                                )}
                            </div>

                            {/* Capacity */}
                            <div className="grid gap-2 rounded-md border p-3">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="update_capacity"
                                        checked={enabledFields.capacity}
                                        onCheckedChange={(c) => toggleField('capacity', Boolean(c))}
                                    />
                                    <Label htmlFor="update_capacity" className="cursor-pointer font-medium">
                                        Update Capacity (Persons)
                                    </Label>
                                </div>
                                {enabledFields.capacity && (
                                    <div className="pt-2">
                                        <Input
                                            id="capacity"
                                            type="number"
                                            min={1}
                                            max={20}
                                            value={formData.capacity}
                                            onChange={(e) =>
                                                setFormData((prev) => ({
                                                    ...prev,
                                                    capacity: e.target.value,
                                                }))
                                            }
                                        />
                                        <InputError message={errors.capacity} />
                                    </div>
                                )}
                            </div>

                            {/* Status */}
                            <div className="grid gap-2 rounded-md border p-3">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="update_status"
                                        checked={enabledFields.status}
                                        onCheckedChange={(c) => toggleField('status', Boolean(c))}
                                    />
                                    <Label htmlFor="update_status" className="cursor-pointer font-medium">
                                        Update Status
                                    </Label>
                                </div>
                                {enabledFields.status && (
                                    <div className="pt-2">
                                        <Select
                                            value={formData.status}
                                            onValueChange={(val) =>
                                                setFormData((prev) => ({
                                                    ...prev,
                                                    status: val,
                                                }))
                                            }
                                        >
                                            <SelectTrigger id="status">
                                                <SelectValue placeholder="Select status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="available">Available</SelectItem>
                                                <SelectItem value="maintenance">Maintenance</SelectItem>
                                                <SelectItem value="unavailable">Unavailable</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.status} />
                                    </div>
                                )}
                            </div>

                            {/* Monthly Rent Rate */}
                            <div className="grid gap-2 rounded-md border p-3">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="update_monthly_rate"
                                        checked={enabledFields.monthly_rate}
                                        onCheckedChange={(c) => toggleField('monthly_rate', Boolean(c))}
                                    />
                                    <Label htmlFor="update_monthly_rate" className="cursor-pointer font-medium">
                                        Update Monthly Rent Rate (IDR)
                                    </Label>
                                </div>
                                {enabledFields.monthly_rate && (
                                    <div className="pt-2">
                                        <Input
                                            id="monthly_rate"
                                            type="number"
                                            min={0}
                                            value={formData.monthly_rate}
                                            onChange={(e) =>
                                                setFormData((prev) => ({
                                                    ...prev,
                                                    monthly_rate: e.target.value,
                                                }))
                                            }
                                            placeholder="e.g. 1800000"
                                        />
                                        <InputError message={errors.monthly_rate} />
                                    </div>
                                )}
                            </div>

                            {/* Size sqm */}
                            <div className="grid gap-2 rounded-md border p-3">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="update_size_sqm"
                                        checked={enabledFields.size_sqm}
                                        onCheckedChange={(c) => toggleField('size_sqm', Boolean(c))}
                                    />
                                    <Label htmlFor="update_size_sqm" className="cursor-pointer font-medium">
                                        Update Room Size (sqm)
                                    </Label>
                                </div>
                                {enabledFields.size_sqm && (
                                    <div className="pt-2">
                                        <Input
                                            id="size_sqm"
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            value={formData.size_sqm}
                                            onChange={(e) =>
                                                setFormData((prev) => ({
                                                    ...prev,
                                                    size_sqm: e.target.value,
                                                }))
                                            }
                                            placeholder="e.g. 16.5"
                                        />
                                        <InputError message={errors.size_sqm} />
                                    </div>
                                )}
                            </div>

                            {/* Description */}
                            <div className="grid gap-2 rounded-md border p-3">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="update_description"
                                        checked={enabledFields.description}
                                        onCheckedChange={(c) => toggleField('description', Boolean(c))}
                                    />
                                    <Label htmlFor="update_description" className="cursor-pointer font-medium">
                                        Update Description
                                    </Label>
                                </div>
                                {enabledFields.description && (
                                    <div className="pt-2">
                                        <Textarea
                                            id="description"
                                            value={formData.description}
                                            onChange={(e) =>
                                                setFormData((prev) => ({
                                                    ...prev,
                                                    description: e.target.value,
                                                }))
                                            }
                                            placeholder="Description for selected units..."
                                            rows={2}
                                        />
                                        <InputError message={errors.description} />
                                    </div>
                                )}
                            </div>

                            {/* Notes */}
                            <div className="grid gap-2 rounded-md border p-3">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="update_notes"
                                        checked={enabledFields.notes}
                                        onCheckedChange={(c) => toggleField('notes', Boolean(c))}
                                    />
                                    <Label htmlFor="update_notes" className="cursor-pointer font-medium">
                                        Update Internal Notes
                                    </Label>
                                </div>
                                {enabledFields.notes && (
                                    <div className="pt-2">
                                        <Textarea
                                            id="notes"
                                            value={formData.notes}
                                            onChange={(e) =>
                                                setFormData((prev) => ({
                                                    ...prev,
                                                    notes: e.target.value,
                                                }))
                                            }
                                            placeholder="Internal notes..."
                                            rows={2}
                                        />
                                        <InputError message={errors.notes} />
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 pt-4 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || targetIds.length === 0 || activeFieldsCount === 0}
                        >
                            {processing ? 'Updating Units...' : `Update ${targetIds.length} Units`}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
