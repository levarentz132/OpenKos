import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import properties from '@/routes/properties';
import type { Property } from '@/types';

export default function BulkUnitSheet({
    property,
    open,
    onOpenChange,
}: {
    property: Property;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        prefix: 'Room',
        start_number: '101',
        count: '10',
        floor: '1',
        capacity: '1',
        monthly_rate: '',
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(properties.units.bulkStore.url({ property: property.slug }), {
            onSuccess: () => {
                onOpenChange(false);
                reset();
            },
        });
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Bulk Create Units</SheetTitle>
                    <SheetDescription>
                        Generate multiple rooms or units at once for {property.name}.
                    </SheetDescription>
                </SheetHeader>

                <form onSubmit={handleSubmit} className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6">
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="prefix">Unit Prefix / Name</Label>
                            <Input
                                id="prefix"
                                value={data.prefix}
                                onChange={(e) => setData('prefix', e.target.value)}
                                placeholder="e.g. Room, Kamar, or A-"
                                required
                            />
                            <p className="text-xs text-muted-foreground">
                                Example: Entering "Room" will create "Room 101", "Room 102", etc.
                            </p>
                            <InputError message={errors.prefix} />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="start_number">Starting Number</Label>
                                <Input
                                    id="start_number"
                                    type="number"
                                    min={1}
                                    value={data.start_number}
                                    onChange={(e) => setData('start_number', e.target.value)}
                                    required
                                />
                                <InputError message={errors.start_number} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="count">Number of Units</Label>
                                <Input
                                    id="count"
                                    type="number"
                                    min={1}
                                    max={100}
                                    value={data.count}
                                    onChange={(e) => setData('count', e.target.value)}
                                    required
                                />
                                <InputError message={errors.count} />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="floor">Floor</Label>
                                <Input
                                    id="floor"
                                    value={data.floor}
                                    onChange={(e) => setData('floor', e.target.value)}
                                    placeholder="e.g. 1"
                                />
                                <InputError message={errors.floor} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="capacity">Capacity (Persons)</Label>
                                <Input
                                    id="capacity"
                                    type="number"
                                    min={1}
                                    max={20}
                                    value={data.capacity}
                                    onChange={(e) => setData('capacity', e.target.value)}
                                />
                                <InputError message={errors.capacity} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="monthly_rate">Monthly Rent Rate (IDR)</Label>
                            <Input
                                id="monthly_rate"
                                type="number"
                                min={0}
                                value={data.monthly_rate}
                                onChange={(e) => setData('monthly_rate', e.target.value)}
                                placeholder="e.g. 1500000 (Optional)"
                            />
                            <InputError message={errors.monthly_rate} />
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
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Creating Units...' : `Create ${data.count || ''} Units`}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
