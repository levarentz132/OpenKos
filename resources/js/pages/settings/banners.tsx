import { useState, useRef } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Plus, Trash2, Image as ImageIcon, Sparkles, Megaphone, Eye, Upload, Loader2, Check } from 'lucide-react';

interface BannerSettingsProps {
    settings: {
        banner_enabled: boolean;
        banner_eyebrow: string;
        banner_title: string;
        banner_description: string;
        banner_cta: string;
        banner_image: string;
        banner_autoplay_interval: number;
        banners: Array<string | { id?: string | number; image: string; title?: string }>;
        promo_enabled: boolean;
        promo_text: string;
    };
}

export default function Banners({ settings }: BannerSettingsProps) {
    const [newBannerUrl, setNewBannerUrl] = useState('');
    const [uploadingCover, setUploadingCover] = useState(false);
    const [uploadingSlide, setUploadingSlide] = useState(false);
    const coverInputRef = useRef<HTMLInputElement>(null);
    const slideInputRef = useRef<HTMLInputElement>(null);

    const form = useForm({
        banner_enabled: settings.banner_enabled ?? true,
        banner_eyebrow: settings.banner_eyebrow || 'Promo Spesial',
        banner_title: settings.banner_title || 'Diskon Early Bird 20%',
        banner_description: settings.banner_description || '',
        banner_cta: settings.banner_cta || 'Klaim Promo',
        banner_image: settings.banner_image || '',
        banner_autoplay_interval: settings.banner_autoplay_interval || 5000,
        banners: Array.isArray(settings.banners) ? settings.banners.map(b => typeof b === 'string' ? b : b.image) : [],
        promo_enabled: settings.promo_enabled ?? false,
        promo_text: settings.promo_text || '',
    });

    const handleCoverFileUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setUploadingCover(true);
        const formData = new FormData();
        formData.append('image', file);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch('/settings/banners/upload', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken || '',
                    'Accept': 'application/json',
                },
                body: formData,
            });

            if (res.ok) {
                const data = await res.json();
                if (data.url) {
                    form.setData('banner_image', data.url);
                }
            } else {
                alert('Gagal mengunggah gambar cover banner. Pastikan ukuran file < 10MB.');
            }
        } catch (err) {
            console.error('Error uploading banner cover:', err);
            alert('Terjadi kesalahan saat mengunggah gambar.');
        } finally {
            setUploadingCover(false);
            if (coverInputRef.current) coverInputRef.current.value = '';
        }
    };

    const handleSlideFilesUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = e.target.files;
        if (!files || files.length === 0) return;

        setUploadingSlide(true);
        const uploadedUrls: string[] = [];

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const formData = new FormData();
                formData.append('image', file);

                const res = await fetch('/settings/banners/upload', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken || '',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                if (res.ok) {
                    const data = await res.json();
                    if (data.url) {
                        uploadedUrls.push(data.url);
                    }
                }
            }

            if (uploadedUrls.length > 0) {
                form.setData('banners', [...form.data.banners, ...uploadedUrls]);
            }
        } catch (err) {
            console.error('Error uploading slide images:', err);
            alert('Terjadi kesalahan saat mengunggah gambar slide.');
        } finally {
            setUploadingSlide(false);
            if (slideInputRef.current) slideInputRef.current.value = '';
        }
    };

    const handleAddSlideUrl = () => {
        if (!newBannerUrl.trim()) return;
        form.setData('banners', [...form.data.banners, newBannerUrl.trim()]);
        setNewBannerUrl('');
    };

    const handleRemoveSlide = (index: number) => {
        const updated = form.data.banners.filter((_, i) => i !== index);
        form.setData('banners', updated);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.patch('/settings/banners', {
            preserveScroll: true,
        });
    };

    return (
        <div className="space-y-6 max-w-4xl pb-10">
            <div>
                <h2 className="text-xl font-semibold tracking-tight">Banner & Promo Website</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Atur konten promosi, upload gambar banner/slide, teks diskon, dan banner di halaman utama aplikasi & web.
                </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* 1. Main Promo Banner Configuration */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="space-y-1">
                                <CardTitle className="flex items-center gap-2">
                                    <Sparkles className="w-5 h-5 text-amber-500" />
                                    <span>Banner Promosi Utama (Home Carousel)</span>
                                </CardTitle>
                                <CardDescription>
                                    Konfigurasi teks, tombol aksi (CTA), dan foto cover utama yang tampil di beranda depan.
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <Label htmlFor="banner_enabled" className="text-xs font-medium">
                                    {form.data.banner_enabled ? 'Aktif' : 'Nonaktif'}
                                </Label>
                                <Switch
                                    id="banner_enabled"
                                    checked={form.data.banner_enabled}
                                    onCheckedChange={(checked) => form.setData('banner_enabled', checked)}
                                />
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        {/* Live Banner Preview Box */}
                        <div className="rounded-xl border border-muted bg-muted/30 p-4 overflow-hidden">
                            <div className="flex items-center gap-2 text-xs font-semibold text-muted-foreground mb-3">
                                <Eye className="w-4 h-4" />
                                <span>Live Preview Tampilan Banner Utama</span>
                            </div>
                            <div className="relative rounded-xl overflow-hidden min-h-[180px] sm:min-h-[220px] flex flex-col justify-end p-5 bg-zinc-900 text-white shadow-inner">
                                {form.data.banner_image ? (
                                    <img
                                        src={form.data.banner_image}
                                        alt="Preview"
                                        className="absolute inset-0 w-full h-full object-cover opacity-60"
                                        onError={(e) => {
                                            (e.target as HTMLElement).style.display = 'none';
                                        }}
                                    />
                                ) : (
                                    <div className="absolute inset-0 bg-gradient-to-r from-blue-900 to-indigo-950 opacity-80" />
                                )}
                                <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent" />
                                <div className="relative z-10 space-y-1.5 max-w-lg">
                                    <span className="inline-block px-2.5 py-0.5 rounded-full bg-amber-500/30 text-amber-300 border border-amber-500/40 text-[10px] font-bold">
                                        {form.data.banner_eyebrow || 'Promo'}
                                    </span>
                                    <h3 className="text-base sm:text-xl font-bold line-clamp-1">
                                        {form.data.banner_title || 'Judul Promosi'}
                                    </h3>
                                    <p className="text-xs text-zinc-300 line-clamp-2">
                                        {form.data.banner_description || 'Deskripsi promo akan muncul di sini.'}
                                    </p>
                                    <div className="pt-1">
                                        <span className="inline-flex px-3.5 py-1 rounded-full bg-amber-500 text-black text-xs font-bold shadow">
                                            {form.data.banner_cta || 'Klaim Promo'} →
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Image Upload for Cover */}
                        <div className="p-4 rounded-xl border border-dashed bg-muted/20 space-y-3">
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <Label className="text-sm font-semibold flex items-center gap-1.5">
                                        <ImageIcon className="w-4 h-4 text-primary" />
                                        <span>Upload Foto / Gambar Cover Banner</span>
                                    </Label>
                                    <p className="text-xs text-muted-foreground mt-0.5">
                                        Upload gambar dari komputer/HP Anda (format JPG, PNG, WEBP max 10MB)
                                    </p>
                                </div>
                                <div>
                                    <input
                                        type="file"
                                        ref={coverInputRef}
                                        accept="image/*"
                                        className="hidden"
                                        onChange={handleCoverFileUpload}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={uploadingCover}
                                        onClick={() => coverInputRef.current?.click()}
                                        className="flex items-center gap-2 shrink-0 bg-background"
                                    >
                                        {uploadingCover ? (
                                            <>
                                                <Loader2 className="w-4 h-4 animate-spin" />
                                                <span>Mengunggah...</span>
                                            </>
                                        ) : (
                                            <>
                                                <Upload className="w-4 h-4" />
                                                <span>Pilih Foto dari Perangkat</span>
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="banner_image" className="text-xs text-muted-foreground">
                                    Atau gunakan URL Gambar Langsung:
                                </Label>
                                <Input
                                    id="banner_image"
                                    value={form.data.banner_image}
                                    onChange={(e) => form.setData('banner_image', e.target.value)}
                                    placeholder="https://images.unsplash.com/... atau /storage/banners/..."
                                    className="text-xs font-mono"
                                />
                            </div>
                        </div>

                        {/* Input Fields */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="banner_eyebrow">Badge / Eyebrow (Teks Kecil Atas)</Label>
                                <Input
                                    id="banner_eyebrow"
                                    value={form.data.banner_eyebrow}
                                    onChange={(e) => form.setData('banner_eyebrow', e.target.value)}
                                    placeholder="Contoh: Promo Spesial / Early Bird"
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="banner_cta">Label Tombol CTA</Label>
                                <Input
                                    id="banner_cta"
                                    value={form.data.banner_cta}
                                    onChange={(e) => form.setData('banner_cta', e.target.value)}
                                    placeholder="Contoh: Klaim Promo / Lihat Kamar"
                                />
                            </div>

                            <div className="space-y-1.5 md:col-span-2">
                                <Label htmlFor="banner_title">Judul Banner Promosi</Label>
                                <Input
                                    id="banner_title"
                                    value={form.data.banner_title}
                                    onChange={(e) => form.setData('banner_title', e.target.value)}
                                    placeholder="Contoh: Diskon Early Bird 20% Bulan Ini"
                                    required
                                />
                            </div>

                            <div className="space-y-1.5 md:col-span-2">
                                <Label htmlFor="banner_description">Deskripsi Lengkap Promosi</Label>
                                <Input
                                    id="banner_description"
                                    value={form.data.banner_description}
                                    onChange={(e) => form.setData('banner_description', e.target.value)}
                                    placeholder="Rincian penawaran khusus atau keuntungan sewa..."
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="banner_autoplay_interval">Kecepatan Putar Otomatis (Detik)</Label>
                                <div className="flex items-center gap-3">
                                    <Input
                                        id="banner_autoplay_interval"
                                        type="number"
                                        min="2"
                                        max="30"
                                        value={Math.round((form.data.banner_autoplay_interval || 5000) / 1000)}
                                        onChange={(e) => form.setData('banner_autoplay_interval', (parseInt(e.target.value, 10) || 5) * 1000)}
                                        className="w-24"
                                    />
                                    <span className="text-xs text-muted-foreground">detik per pergantian slide</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* 2. Multi-Slide Banners List */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ImageIcon className="w-5 h-5 text-indigo-500" />
                            <span>Daftar Slide Banner Tambahan (Carousel)</span>
                        </CardTitle>
                        <CardDescription>
                            Tambahkan foto-foto banner tambahan untuk diputar otomatis di carousel halaman depan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        {/* Existing Slides */}
                        {form.data.banners.length > 0 ? (
                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                {form.data.banners.map((url, index) => (
                                    <div key={index} className="relative group rounded-lg overflow-hidden border border-muted bg-muted/20">
                                        <div className="aspect-[16/9] w-full bg-zinc-950 overflow-hidden">
                                            <img
                                                src={url}
                                                alt={`Slide ${index + 1}`}
                                                className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                                                onError={(e) => {
                                                    (e.target as HTMLElement).src = 'https://via.placeholder.com/400x225?text=Image+Not+Found';
                                                }}
                                            />
                                        </div>
                                        <div className="p-2 flex items-center justify-between text-xs bg-background/90 backdrop-blur">
                                            <span className="font-semibold truncate max-w-[140px]">Slide #{index + 1}</span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 w-7 p-0 text-red-500 hover:text-red-700 hover:bg-red-50"
                                                onClick={() => handleRemoveSlide(index)}
                                            >
                                                <Trash2 className="w-3.5 h-3.5" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-6 text-sm text-muted-foreground border border-dashed rounded-lg">
                                Belum ada slide banner tambahan. (Sistem akan menampilkan cover banner utama).
                            </div>
                        )}

                        {/* Upload Slide Images Box */}
                        <div className="p-4 rounded-xl border border-dashed bg-muted/20 space-y-3">
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <Label className="text-sm font-semibold flex items-center gap-1.5">
                                        <Upload className="w-4 h-4 text-indigo-500" />
                                        <span>Upload Slide Baru dari Perangkat</span>
                                    </Label>
                                    <p className="text-xs text-muted-foreground mt-0.5">
                                        Pilih satu atau beberapa file foto banner untuk ditambahkan ke slide carousel
                                    </p>
                                </div>
                                <div>
                                    <input
                                        type="file"
                                        ref={slideInputRef}
                                        multiple
                                        accept="image/*"
                                        className="hidden"
                                        onChange={handleSlideFilesUpload}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={uploadingSlide}
                                        onClick={() => slideInputRef.current?.click()}
                                        className="flex items-center gap-2 shrink-0 bg-background"
                                    >
                                        {uploadingSlide ? (
                                            <>
                                                <Loader2 className="w-4 h-4 animate-spin" />
                                                <span>Mengunggah Slide...</span>
                                            </>
                                        ) : (
                                            <>
                                                <Upload className="w-4 h-4" />
                                                <span>Upload Foto Slide</span>
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </div>

                            {/* Add Slide via URL */}
                            <div className="flex gap-2 pt-1">
                                <Input
                                    value={newBannerUrl}
                                    onChange={(e) => setNewBannerUrl(e.target.value)}
                                    placeholder="Atau masukkan URL gambar langsung (https://...)"
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            handleAddSlideUrl();
                                        }
                                    }}
                                    className="text-xs"
                                />
                                <Button type="button" variant="secondary" onClick={handleAddSlideUrl} className="shrink-0 flex items-center gap-1.5 text-xs">
                                    <Plus className="w-3.5 h-3.5" />
                                    <span>Tambah URL</span>
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* 3. Promo Notification Text Bar */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="space-y-1">
                                <CardTitle className="flex items-center gap-2">
                                    <Megaphone className="w-5 h-5 text-emerald-500" />
                                    <span>Teks Pengumuman Promo Tambahan</span>
                                </CardTitle>
                                <CardDescription>
                                    Baris teks pengumuman/voucher promo khusus (opsional).
                                </CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <Label htmlFor="promo_enabled" className="text-xs font-medium">
                                    {form.data.promo_enabled ? 'Aktif' : 'Nonaktif'}
                                </Label>
                                <Switch
                                    id="promo_enabled"
                                    checked={form.data.promo_enabled}
                                    onCheckedChange={(checked) => form.setData('promo_enabled', checked)}
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-1.5">
                            <Label htmlFor="promo_text">Teks Pengumuman Promo</Label>
                            <Input
                                id="promo_text"
                                value={form.data.promo_text}
                                onChange={(e) => form.setData('promo_text', e.target.value)}
                                placeholder="Contoh: Gunakan kode FIRSTMO untuk diskon 10% di bulan pertama!"
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Save Button */}
                <div className="flex items-center justify-end gap-3 pt-2">
                    <Button type="submit" disabled={form.processing || uploadingCover || uploadingSlide} className="min-w-[180px]">
                        {form.processing ? (
                            <>
                                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                <span>Menyimpan...</span>
                            </>
                        ) : (
                            <>
                                <Check className="w-4 h-4 mr-1.5" />
                                <span>Simpan Pengaturan</span>
                            </>
                        )}
                    </Button>
                </div>
            </form>
        </div>
    );
}

Banners.layout = {
    breadcrumbs: [{ title: 'Banner & Promo', href: '/settings/banners' }],
};

