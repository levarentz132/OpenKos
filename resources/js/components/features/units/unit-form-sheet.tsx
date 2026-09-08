import { useForm } from '@inertiajs/react';
import { Image, Plus, Trash2, Upload, Video, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
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
import { BILLING_UNITS } from '@/lib/constants';
import properties from '@/routes/properties';
import type { Property, Unit, UnitRate } from '@/types';

const emptyRate: UnitRate = {
    billing_interval: 1,
    billing_unit: 'month',
    amount: '',
};

export default function UnitFormSheet({
    unit,
    property,
    open,
    onOpenChange,
}: {
    unit?: Unit | null;
    property: Property;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const isEdit = Boolean(unit);

    const imageInputRef = useRef<HTMLInputElement>(null);
    const videoInputRef = useRef<HTMLInputElement>(null);

    const [imagePreview, setImagePreview] = useState<string | null>(
        unit?.image_url ?? null,
    );
    const [videoPreview, setVideoPreview] = useState<string | null>(
        unit?.video_url ?? null,
    );

    const { data, setData, submit, reset, processing, errors } = useForm({
        name: unit?.name ?? '',
        floor: unit?.floor ?? '',
        capacity: String(unit?.capacity ?? 1),
        rates: unit?.active_rates?.length ? unit.active_rates : [emptyRate],
        size_sqm: unit?.size_sqm ?? '',
        status: unit?.status ?? 'available',
        description: unit?.description ?? '',
        notes: unit?.notes ?? '',
        image: null as File | string | null,
        remove_image: false,
        video: (unit?.video ?? '') as File | string | null,
        remove_video: false,
    });

    function handleOpenChange(next: boolean) {
        onOpenChange(next);

        if (!next) {
            reset();
            setImagePreview(null);
            setVideoPreview(null);
        }
    }

    function handleImageFileChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (file) {
            setData((prev) => ({
                ...prev,
                image: file,
                remove_image: false,
            }));
            setImagePreview(URL.createObjectURL(file));
        }
    }

    function handleRemoveImage() {
        setData((prev) => ({
            ...prev,
            image: null,
            remove_image: true,
        }));
        setImagePreview(null);
        if (imageInputRef.current) {
            imageInputRef.current.value = '';
        }
    }

    function handleVideoFileChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (file) {
            setData((prev) => ({
                ...prev,
                video: file,
                remove_video: false,
            }));
            setVideoPreview(URL.createObjectURL(file));
        }
    }

    function handleRemoveVideo() {
        setData((prev) => ({
            ...prev,
            video: '',
            remove_video: true,
        }));
        setVideoPreview(null);
        if (videoInputRef.current) {
            videoInputRef.current.value = '';
        }
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(
            isEdit
                ? properties.units.update({
                      property: property.slug,
                      unit: unit!.slug,
                  })
                : properties.units.store(property.slug),
            { onSuccess: () => handleOpenChange(false) },
        );
    }

    function updateRate(
        index: number,
        field: keyof UnitRate,
        value: string | number,
    ) {
        setData((prev) => {
            const next = [...prev.rates];
            next[index] = { ...next[index], [field]: value };

            return { ...prev, rates: next };
        });
    }

    function addRate() {
        setData((prev) => ({
            ...prev,
            rates: [...prev.rates, { ...emptyRate }],
        }));
    }

    function removeRate(index: number) {
        setData((prev) => ({
            ...prev,
            rates: prev.rates.filter((_, i) => i !== index),
        }));
    }

    return (
        <Sheet open={open} onOpenChange={handleOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{isEdit ? 'Edit Unit' : 'New Unit'}</SheetTitle>
                    <SheetDescription>
                        {isEdit
                            ? 'Update unit details'
                            : `Add a unit to ${property.name}`}
                    </SheetDescription>
                </SheetHeader>

                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6"
                >
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                required
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="e.g. Unit 101"
                            />
                            <InputError message={errors.name} />
                        </div>

                        {/* Room Image Upload */}
                        <div className="grid gap-2">
                            <Label>Room Image</Label>
                            {imagePreview ? (
                                <div className="relative aspect-video w-full overflow-hidden rounded-lg border border-border bg-muted">
                                    <img
                                        src={imagePreview}
                                        alt="Room preview"
                                        className="size-full object-cover"
                                    />
                                    <div className="absolute top-2 right-2 flex gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="secondary"
                                            className="h-8 shadow-sm"
                                            onClick={() =>
                                                imageInputRef.current?.click()
                                            }
                                        >
                                            <Upload className="mr-1.5 size-3.5" />
                                            Change
                                        </Button>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="destructive"
                                            className="size-8 shadow-sm"
                                            onClick={handleRemoveImage}
                                        >
                                            <X className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <div
                                    onClick={() =>
                                        imageInputRef.current?.click()
                                    }
                                    className="flex aspect-video w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border bg-muted/40 p-4 transition hover:bg-muted/70"
                                >
                                    <Image className="size-8 text-muted-foreground" />
                                    <div className="text-center text-xs text-muted-foreground">
                                        <span className="font-semibold text-primary">
                                            Click to upload room image
                                        </span>
                                        <p>JPG, PNG, WebP (max 10MB)</p>
                                    </div>
                                </div>
                            )}
                            <input
                                ref={imageInputRef}
                                type="file"
                                accept="image/*"
                                className="hidden"
                                onChange={handleImageFileChange}
                            />
                            <InputError message={errors.image} />
                        </div>

                        {/* Room Video Upload / Link */}
                        <div className="grid gap-2">
                            <Label>Room Video</Label>
                            {videoPreview ? (
                                <div className="relative aspect-video w-full overflow-hidden rounded-lg border border-border bg-black/90">
                                    <video
                                        src={videoPreview}
                                        controls
                                        className="size-full object-contain"
                                    />
                                    <div className="absolute top-2 right-2 flex gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="secondary"
                                            className="h-8 shadow-sm"
                                            onClick={() =>
                                                videoInputRef.current?.click()
                                            }
                                        >
                                            <Upload className="mr-1.5 size-3.5" />
                                            Change
                                        </Button>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="destructive"
                                            className="size-8 shadow-sm"
                                            onClick={handleRemoveVideo}
                                        >
                                            <X className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <div
                                        onClick={() =>
                                            videoInputRef.current?.click()
                                        }
                                        className="flex aspect-video w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border bg-muted/40 p-4 transition hover:bg-muted/70"
                                    >
                                        <Video className="size-8 text-muted-foreground" />
                                        <div className="text-center text-xs text-muted-foreground">
                                            <span className="font-semibold text-primary">
                                                Upload video file
                                            </span>
                                            <p>MP4, WebM (max 50MB)</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs text-muted-foreground">
                                            Or enter video URL:
                                        </span>
                                        <Input
                                            type="url"
                                            placeholder="https://..."
                                            value={
                                                typeof data.video === 'string'
                                                    ? data.video
                                                    : ''
                                            }
                                            onChange={(e) => {
                                                const url = e.target.value;
                                                setData((prev) => ({
                                                    ...prev,
                                                    video: url,
                                                    remove_video: false,
                                                }));
                                                setVideoPreview(url || null);
                                            }}
                                            className="h-8 text-xs"
                                        />
                                    </div>
                                </div>
                            )}
                            <input
                                ref={videoInputRef}
                                type="file"
                                accept="video/*"
                                className="hidden"
                                onChange={handleVideoFileChange}
                            />
                            <InputError message={errors.video} />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="floor">Floor</Label>
                                <Input
                                    id="floor"
                                    value={data.floor}
                                    onChange={(e) =>
                                        setData('floor', e.target.value)
                                    }
                                    placeholder="e.g. 1"
                                />
                                <InputError message={errors.floor} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="capacity">Capacity</Label>
                                <Input
                                    id="capacity"
                                    type="number"
                                    min={1}
                                    value={data.capacity}
                                    onChange={(e) =>
                                        setData('capacity', e.target.value)
                                    }
                                />
                                <InputError message={errors.capacity} />
                            </div>
                        </div>

                        {/* Pricing Rates */}
                        <section>
                            <div className="mb-3 flex items-center justify-between">
                                <h3 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Pricing Rates
                                </h3>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={addRate}
                                >
                                    <Plus className="mr-1 size-3" />
                                    Add Rate
                                </Button>
                            </div>

                            <div className="space-y-3">
                                {data.rates.map((rate, index) => (
                                    <div
                                        key={index}
                                        className="flex items-end gap-2 rounded-lg border p-3"
                                    >
                                        <div className="grid flex-1 gap-1">
                                            <Label className="text-xs">
                                                Amount (IDR)
                                            </Label>
                                            <Input
                                                type="number"
                                                min={0}
                                                step="0.01"
                                                required
                                                value={rate.amount}
                                                onChange={(e) =>
                                                    updateRate(
                                                        index,
                                                        'amount',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="e.g. 1000000"
                                            />
                                        </div>
                                        <div className="grid w-20 gap-1">
                                            <Label className="text-xs">
                                                Every
                                            </Label>
                                            <Input
                                                type="number"
                                                min={1}
                                                value={rate.billing_interval}
                                                onChange={(e) =>
                                                    updateRate(
                                                        index,
                                                        'billing_interval',
                                                        Number.parseInt(
                                                            e.target.value,
                                                        ) || 1,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="grid w-28 gap-1">
                                            <Label className="text-xs">
                                                Unit
                                            </Label>
                                            <Select
                                                value={rate.billing_unit}
                                                onValueChange={(val) =>
                                                    updateRate(
                                                        index,
                                                        'billing_unit',
                                                        val,
                                                    )
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {BILLING_UNITS.map(
                                                        (unit) => (
                                                            <SelectItem
                                                                key={unit}
                                                                value={unit}
                                                            >
                                                                {unit}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        {data.rates.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-9 shrink-0 text-destructive"
                                                onClick={() =>
                                                    removeRate(index)
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        )}
                                    </div>
                                ))}
                            </div>
                            <InputError message={errors.rates} />
                        </section>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="size_sqm">Size (m²)</Label>
                                <Input
                                    id="size_sqm"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={data.size_sqm}
                                    onChange={(e) =>
                                        setData('size_sqm', e.target.value)
                                    }
                                    placeholder="e.g. 20"
                                />
                                <InputError message={errors.size_sqm} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="status">Status</Label>
                                <Select
                                    value={data.status}
                                    onValueChange={(v) => setData('status', v)}
                                >
                                    <SelectTrigger
                                        id="status"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {[
                                            {
                                                value: 'available',
                                                label: 'Available',
                                            },
                                            {
                                                value: 'occupied',
                                                label: 'Occupied',
                                            },
                                            {
                                                value: 'employee',
                                                label: 'Employee / Staff',
                                            },
                                            {
                                                value: 'vendor',
                                                label: 'Vendor',
                                            },
                                            {
                                                value: 'maintenance',
                                                label: 'Maintenance',
                                            },
                                            {
                                                value: 'unavailable',
                                                label: 'Unavailable',
                                            },
                                        ].map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.status} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                placeholder="Unit description"
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                                placeholder="Additional notes"
                            />
                            <InputError message={errors.notes} />
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-4">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => handleOpenChange(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button disabled={processing}>
                            {isEdit ? 'Save' : 'Create'}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
