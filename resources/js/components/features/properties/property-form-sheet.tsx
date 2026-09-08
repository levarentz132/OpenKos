import { router, useForm, usePage } from '@inertiajs/react';
import { Image as ImageIcon, Upload, Video as VideoIcon, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { InputError, PhoneInput, SearchableSelect } from '@/components/shared';
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
import { store, update } from '@/routes/properties';
import type { Property, PropertyTypeOption, Region } from '@/types';

export default function PropertyFormSheet({
    property,
    open,
    onOpenChange,
}: {
    property?: Property | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { regions, propertyTypes } = usePage<{
        regions: Region[];
        propertyTypes: PropertyTypeOption[];
    }>().props;

    const isEdit = Boolean(property);
    const city =
        property?.city && typeof property.city !== 'string'
            ? property.city
            : null;

    const fileInputRef = useRef<HTMLInputElement>(null);
    const galleryInputRef = useRef<HTMLInputElement>(null);
    const videoInputRef = useRef<HTMLInputElement>(null);

    const [imagePreview, setImagePreview] = useState<string | null>(
        property?.image_url ?? null,
    );
    const [videoPreview, setVideoPreview] = useState<string | null>(
        property?.video_url ?? null,
    );

    // Gallery state
    const [existingGallery, setExistingGallery] = useState<
        { path: string; url: string }[]
    >(() => {
        const rawPaths = (property?.images as string[] | null) ?? [];
        const publicUrls = property?.image_urls ?? [];
        return rawPaths.map((path, idx) => ({
            path,
            url: publicUrls[idx] ?? path,
        }));
    });
    const [newGalleryFiles, setNewGalleryFiles] = useState<File[]>([]);
    const [newGalleryPreviews, setNewGalleryPreviews] = useState<string[]>([]);
    const [removedGalleryPaths, setRemovedGalleryPaths] = useState<string[]>([]);

    const { data, setData, reset, processing, errors } = useForm({
        name: property?.name ?? '',
        type: property?.type ?? propertyTypes[0]?.slug ?? '',
        slug: property?.slug ?? '',
        address: property?.address ?? '',
        address_url: property?.address_url ?? '',
        description: property?.description ?? '',
        region_id: property?.region_id ?? property?.region?.id ?? null,
        city_id: property?.city_id ?? city?.id ?? null,
        kecamatan: property?.kecamatan ?? '',
        postal_code: property?.postal_code ?? '',
        phone: property?.phone ?? '',
        image: null as File | null,
        images: [] as File[],
        removed_images: [] as string[],
        remove_image: false,
        video: (property?.video ?? '') as File | string | null,
        remove_video: false,
    });

    useEffect(() => {
        setImagePreview(property?.image_url ?? null);
        setVideoPreview(property?.video_url ?? null);

        const rawPaths = (property?.images as string[] | null) ?? [];
        const publicUrls = property?.image_urls ?? [];
        setExistingGallery(
            rawPaths.map((path, idx) => ({
                path,
                url: publicUrls[idx] ?? path,
            })),
        );
        setNewGalleryFiles([]);
        setNewGalleryPreviews([]);
        setRemovedGalleryPaths([]);
    }, [property]);

    function handleOpenChange(next: boolean) {
        onOpenChange(next);

        if (!next) {
            reset();
            setImagePreview(property?.image_url ?? null);
            setVideoPreview(property?.video_url ?? null);
            setNewGalleryFiles([]);
            setNewGalleryPreviews([]);
            setRemovedGalleryPaths([]);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
            if (galleryInputRef.current) {
                galleryInputRef.current.value = '';
            }
            if (videoInputRef.current) {
                videoInputRef.current.value = '';
            }
        }
    }

    function handleImageChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (file) {
            setData((prev) => ({
                ...prev,
                image: file,
                remove_image: false,
            }));
            const previewUrl = URL.createObjectURL(file);
            setImagePreview(previewUrl);
        }
    }

    function handleRemoveImage() {
        setData((prev) => ({
            ...prev,
            image: null,
            remove_image: true,
        }));
        setImagePreview(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    }

    function handleGalleryFilesAdd(e: React.ChangeEvent<HTMLInputElement>) {
        const files = Array.from(e.target.files ?? []);
        if (files.length > 0) {
            const newFiles = [...newGalleryFiles, ...files];
            setNewGalleryFiles(newFiles);

            const newPreviews = files.map((file) => URL.createObjectURL(file));
            setNewGalleryPreviews((prev) => [...prev, ...newPreviews]);
        }
    }

    function handleRemoveExistingGalleryItem(index: number) {
        const itemToRemove = existingGallery[index];
        if (itemToRemove) {
            setRemovedGalleryPaths((prev) => [...prev, itemToRemove.path]);
            setExistingGallery((prev) => prev.filter((_, i) => i !== index));
        }
    }

    function handleRemoveNewGalleryItem(index: number) {
        setNewGalleryFiles((prev) => prev.filter((_, i) => i !== index));
        setNewGalleryPreviews((prev) => prev.filter((_, i) => i !== index));
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

        const formData: Record<string, any> = {
            ...data,
            images: newGalleryFiles,
            removed_images: removedGalleryPaths,
        };

        if (isEdit && property) {
            router.post(
                update.url(property),
                {
                    ...formData,
                    _method: 'put',
                },
                {
                    forceFormData: true,
                    onSuccess: () => handleOpenChange(false),
                },
            );
        } else {
            router.post(store.url(), formData, {
                forceFormData: true,
                onSuccess: () => handleOpenChange(false),
            });
        }
    }

    const availableCities =
        regions
            .find((r) => r.id === data.region_id)
            ?.cities.map((c) => ({
                value: c.id,
                label: c.name,
            })) ?? [];

    const regionOptions = regions.map((r) => ({
        value: r.id,
        label: r.name,
    }));

    return (
        <Sheet
            key={property?.id ?? 'new'}
            open={open}
            onOpenChange={handleOpenChange}
        >
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        {isEdit ? 'Edit Property' : 'New Property'}
                    </SheetTitle>
                    <SheetDescription>
                        {isEdit
                            ? 'Update property details'
                            : 'Add a new property to manage'}
                    </SheetDescription>
                </SheetHeader>

                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col justify-between gap-6 overflow-y-auto px-4 pt-4 pb-6"
                >
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label>Property Image</Label>
                            {imagePreview ? (
                                <div className="relative aspect-video w-full overflow-hidden rounded-lg border border-border bg-muted">
                                    <img
                                        src={imagePreview}
                                        alt="Property preview"
                                        className="size-full object-cover"
                                    />
                                    <div className="absolute top-2 right-2 flex gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="secondary"
                                            className="h-8 shadow-sm"
                                            onClick={() =>
                                                fileInputRef.current?.click()
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
                                        fileInputRef.current?.click()
                                    }
                                    className="flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-input p-6 text-center transition-colors hover:border-primary/50 hover:bg-accent/40"
                                >
                                    <div className="rounded-full bg-muted p-3 text-muted-foreground">
                                        <ImageIcon className="size-6" />
                                    </div>
                                    <p className="mt-2 text-sm font-medium">
                                        Click to upload image
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        PNG, JPG, WEBP up to 5MB
                                    </p>
                                </div>
                            )}
                            <input
                                ref={fileInputRef}
                                type="file"
                                accept="image/png,image/jpeg,image/jpg,image/webp"
                                className="hidden"
                                onChange={handleImageChange}
                            />
                            <InputError message={errors.image} />
                        </div>

                        {/* Property Photo Gallery (Multiple Images) */}
                        <div className="grid gap-2">
                            <Label>Property Gallery (Multiple Photos)</Label>
                            <div className="grid grid-cols-3 gap-2">
                                {existingGallery.map((item, idx) => (
                                    <div
                                        key={`exist-${idx}`}
                                        className="group relative aspect-square overflow-hidden rounded-md border bg-muted"
                                    >
                                        <img
                                            src={item.url}
                                            alt={`Gallery ${idx + 1}`}
                                            className="size-full object-cover transition-transform group-hover:scale-105"
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                handleRemoveExistingGalleryItem(idx)
                                            }
                                            className="absolute top-1 right-1 rounded-full bg-destructive/80 p-1 text-white opacity-90 transition-opacity hover:bg-destructive"
                                        >
                                            <X className="size-3" />
                                        </button>
                                    </div>
                                ))}

                                {newGalleryPreviews.map((src, idx) => (
                                    <div
                                        key={`new-${idx}`}
                                        className="group relative aspect-square overflow-hidden rounded-md border bg-muted"
                                    >
                                        <img
                                            src={src}
                                            alt={`New Gallery ${idx + 1}`}
                                            className="size-full object-cover transition-transform group-hover:scale-105"
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                handleRemoveNewGalleryItem(idx)
                                            }
                                            className="absolute top-1 right-1 rounded-full bg-destructive/80 p-1 text-white opacity-90 transition-opacity hover:bg-destructive"
                                        >
                                            <X className="size-3" />
                                        </button>
                                    </div>
                                ))}

                                <div
                                    onClick={() =>
                                        galleryInputRef.current?.click()
                                    }
                                    className="flex aspect-square cursor-pointer flex-col items-center justify-center rounded-md border border-dashed border-input p-2 text-center transition-colors hover:border-primary/50 hover:bg-accent/40"
                                >
                                    <ImageIcon className="size-5 text-muted-foreground" />
                                    <span className="mt-1 text-[11px] font-medium text-muted-foreground">
                                        + Add Photos
                                    </span>
                                </div>
                            </div>
                            <input
                                ref={galleryInputRef}
                                type="file"
                                multiple
                                accept="image/png,image/jpeg,image/jpg,image/webp"
                                className="hidden"
                                onChange={handleGalleryFilesAdd}
                            />
                            <InputError message={errors.images} />
                        </div>

                        {/* Property Video Upload / Link */}
                        <div className="grid gap-2">
                            <Label>Property Video / Tour</Label>
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
                                        className="flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-input p-6 text-center transition-colors hover:border-primary/50 hover:bg-accent/40"
                                    >
                                        <div className="rounded-full bg-muted p-3 text-muted-foreground">
                                            <VideoIcon className="size-6" />
                                        </div>
                                        <p className="mt-2 text-sm font-medium">
                                            Click to upload video file
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            MP4, WebM up to 50MB
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2 pt-1">
                                        <span className="text-xs text-muted-foreground shrink-0">
                                            Or video URL:
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
                                accept="video/mp4,video/webm,video/ogg"
                                className="hidden"
                                onChange={handleVideoFileChange}
                            />
                            <InputError message={errors.video} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                required
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="e.g. Kos Melati"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="type">Property Type</Label>
                            <Select
                                value={data.type}
                                onValueChange={(val) => setData('type', val)}
                            >
                                <SelectTrigger id="type">
                                    <SelectValue placeholder="Select type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {propertyTypes.map((pt) => (
                                        <SelectItem
                                            key={pt.slug}
                                            value={pt.slug}
                                        >
                                            {pt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.type} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="slug">
                                Slug (URL Identifier)
                            </Label>
                            <Input
                                id="slug"
                                value={data.slug}
                                onChange={(e) =>
                                    setData('slug', e.target.value)
                                }
                                placeholder="e.g. kos-melati (leave empty to auto-generate)"
                            />
                            <InputError message={errors.slug} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="phone">Phone Number</Label>
                            <PhoneInput
                                value={data.phone}
                                onChange={(val) => setData('phone', val)}
                            />
                            <InputError message={errors.phone} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="region_id">Province / Region</Label>
                            <SearchableSelect
                                options={regionOptions}
                                value={data.region_id ?? undefined}
                                onChange={(val) =>
                                    setData((prev) => ({
                                        ...prev,
                                        region_id: val as number | null,
                                        city_id: null,
                                    }))
                                }
                                placeholder="Select region..."
                            />
                            <InputError message={errors.region_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="city_id">City</Label>
                            <SearchableSelect
                                options={availableCities}
                                value={data.city_id ?? undefined}
                                onChange={(val) =>
                                    setData('city_id', val as number | null)
                                }
                                placeholder={
                                    data.region_id
                                        ? 'Select city...'
                                        : 'Select a region first'
                                }
                                disabled={!data.region_id}
                            />
                            <InputError message={errors.city_id} />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="kecamatan">
                                    District (Kecamatan)
                                </Label>
                                <Input
                                    id="kecamatan"
                                    value={data.kecamatan}
                                    onChange={(e) =>
                                        setData('kecamatan', e.target.value)
                                    }
                                    placeholder="e.g. Setiabudi"
                                />
                                <InputError message={errors.kecamatan} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="postal_code">Postal Code</Label>
                                <Input
                                    id="postal_code"
                                    value={data.postal_code}
                                    onChange={(e) =>
                                        setData('postal_code', e.target.value)
                                    }
                                    placeholder="e.g. 12920"
                                />
                                <InputError message={errors.postal_code} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="address">Full Address</Label>
                            <Textarea
                                id="address"
                                value={data.address}
                                onChange={(e) =>
                                    setData('address', e.target.value)
                                }
                                placeholder="Street address..."
                            />
                            <InputError message={errors.address} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="address_url">
                                Google Maps URL
                            </Label>
                            <Input
                                id="address_url"
                                type="url"
                                value={data.address_url}
                                onChange={(e) =>
                                    setData('address_url', e.target.value)
                                }
                                placeholder="https://maps.google.com/..."
                            />
                            <InputError message={errors.address_url} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                placeholder="Property description, amenities, rules..."
                            />
                            <InputError message={errors.description} />
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 pt-4 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => handleOpenChange(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {isEdit ? 'Save Changes' : 'Create Property'}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
