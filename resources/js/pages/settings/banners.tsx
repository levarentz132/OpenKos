import { useState } from 'react';
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
import { Plus, Trash2, Image as ImageIcon, Sparkles, Megaphone, Eye } from 'lucide-react';

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

    const handleAddSlide = () => {
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
        <div className="space-y-6 max-w-4xl">
            <div>
                <h2 className="text-xl font-semibold tracking-tight">Banner & Promo Website</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Atur konten promosi, gambar slide carousel, teks diskon, dan banner di halaman utama aplikasi & web.
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
                                    Konfigurasi teks, tombol aksi (CTA), dan gambar cover utama yang tampil di beranda.
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
                                <span>Live Preview Tampilan Banner</span>
                            </div>
                            <div className="relative rounded-xl overflow-hidden min-h-[160px] flex flex-col justify-end p-5 bg-zinc-900 text-white shadow-inner">
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
                                    <span className="inline-block px-2 py-0.5 rounded-full bg-amber-500/30 text-amber-300 border border-amber-500/40 text-[10px] font-bold">
                                        {form.data.banner_eyebrow || 'Promo'}
                                    </span>
                                    <h3 className="text-base sm:text-lg font-bold line-clamp-1">
                                        {form.data.banner_title || 'Judul Promosi'}
                                    </h3>
                                    <p className="text-xs text-zinc-300 line-clamp-2">
                                        {form.data.banner_description || 'Deskripsi promo akan muncul di sini.'}
                                    </p>
                                    <div className="pt-1">
                                        <span className="inline-flex px-3 py-1 rounded-full bg-amber-500 text-black text-xs font-bold shadow">
                                            {form.data.banner_cta || 'Klaim Promo'} →
                                        </span>
                                    </div>
                                </div>
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

                            <div className="space-y-1.5 md:col-span-2">
                                <Label htmlFor="banner_image">URL Foto / Gambar Cover Banner Utama</Label>
                                <Input
                                    id="banner_image"
                                    value={form.data.banner_image}
                                    onChange={(e) => form.setData('banner_image', e.target.value)}
                                    placeholder="https://images.unsplash.com/... atau /uploads/banner.jpg"
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
                            Tambahkan beberapa gambar banner untuk diputar otomatis di carousel halaman depan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
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
                                            <span className="font-semibold truncate max-w-[120px]">Slide #{index + 1}</span>
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
                                Belum ada slide banner tambahan. (Sistem akan menggunakan cover banner utama & default high-quality slide).
                            </div>
                        )}

                        {/* Add Slide Input */}
                        <div className="flex gap-2 pt-2">
                            <Input
                                value={newBannerUrl}
                                onChange={(e) => setNewBannerUrl(e.target.value)}
                                placeholder="Masukkan URL gambar banner baru (https://...)"
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        handleAddSlide();
                                    }
                                }}
                            />
                            <Button type="button" variant="secondary" onClick={handleAddSlide} className="shrink-0 flex items-center gap-1.5">
                                <Plus className="w-4 h-4" />
                                <span>Tambah Slide</span>
                            </Button>
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
                    <Button type="submit" disabled={form.processing} className="min-w-[160px]">
                        {form.processing ? 'Menyimpan...' : 'Simpan Pengaturan'}
                    </Button>
                </div>
            </form>
        </div>
    );
}

Banners.layout = {
    breadcrumbs: [{ title: 'Banner & Promo', href: '/settings/banners' }],
};
