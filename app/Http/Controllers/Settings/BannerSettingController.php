<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateSettings;
use App\Http\Controllers\Api\v1\WebsiteSettingController;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BannerSettingController extends Controller
{
    public function __construct(
        private UpdateSettings $updateSettings,
    ) {}

    public function edit(): Response
    {
        $settings = Setting::some([
            'banner_enabled',
            'banner_eyebrow',
            'banner_title',
            'banner_description',
            'banner_cta',
            'banner_image',
            'banner_autoplay_interval',
            'banners',
            'promo_enabled',
            'promo_text',
        ]);

        $bannerImage = (string) ($settings['banner_image'] ?? 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1920&q=80');
        if ($bannerImage && ! str_starts_with($bannerImage, 'http') && ! str_starts_with($bannerImage, '/storage') && ! str_starts_with($bannerImage, '/uploads')) {
            $bannerImage = Storage::disk('public')->url($bannerImage);
        }

        $banners = is_array($settings['banners'] ?? null) ? $settings['banners'] : [];
        $normalizedBanners = array_map(function ($item) {
            if (is_string($item)) {
                if ($item && ! str_starts_with($item, 'http') && ! str_starts_with($item, '/storage') && ! str_starts_with($item, '/uploads')) {
                    return Storage::disk('public')->url($item);
                }
                return $item;
            }
            if (is_array($item) && ! empty($item['image'])) {
                if (! str_starts_with($item['image'], 'http') && ! str_starts_with($item['image'], '/storage') && ! str_starts_with($item['image'], '/uploads')) {
                    $item['image'] = Storage::disk('public')->url($item['image']);
                }
            }
            return $item;
        }, $banners);

        return Inertia::render('settings/banners', [
            'settings' => [
                'banner_enabled' => (bool) ($settings['banner_enabled'] ?? true),
                'banner_eyebrow' => (string) ($settings['banner_eyebrow'] ?? 'Promo Spesial'),
                'banner_title' => (string) ($settings['banner_title'] ?? 'Diskon Early Bird 20%'),
                'banner_description' => (string) ($settings['banner_description'] ?? 'Pesan ruang impian Anda bulan ini dan nikmati potongan harga eksklusif untuk 3 bulan pertama.'),
                'banner_cta' => (string) ($settings['banner_cta'] ?? 'Klaim Promo'),
                'banner_image' => $bannerImage,
                'banner_autoplay_interval' => (int) ($settings['banner_autoplay_interval'] ?? 5000),
                'banners' => $normalizedBanners,
                'promo_enabled' => filter_var($settings['promo_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'promo_text' => (string) ($settings['promo_text'] ?? ''),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'banner_enabled' => ['sometimes', 'boolean'],
            'banner_eyebrow' => ['nullable', 'string', 'max:100'],
            'banner_title' => ['nullable', 'string', 'max:255'],
            'banner_description' => ['nullable', 'string', 'max:1000'],
            'banner_cta' => ['nullable', 'string', 'max:100'],
            'banner_image' => ['nullable', 'string', 'max:1000'],
            'banner_image_file' => ['nullable', 'image', 'max:10240'],
            'banner_autoplay_interval' => ['nullable', 'integer', 'min:2000', 'max:60000'],
            'banners' => ['nullable', 'array'],
            'banners.*' => ['nullable'],
            'new_slide_files' => ['nullable', 'array'],
            'new_slide_files.*' => ['nullable', 'image', 'max:10240'],
            'promo_enabled' => ['sometimes', 'boolean'],
            'promo_text' => ['nullable', 'string', 'max:500'],
        ]);

        if ($request->hasFile('banner_image_file')) {
            $path = $request->file('banner_image_file')->store('banners', 'public');
            $validated['banner_image'] = Storage::disk('public')->url($path);
        }
        unset($validated['banner_image_file']);

        $banners = $validated['banners'] ?? [];
        if ($request->hasFile('new_slide_files')) {
            foreach ($request->file('new_slide_files') as $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('banners', 'public');
                    $banners[] = Storage::disk('public')->url($path);
                }
            }
        }
        $validated['banners'] = array_values(array_filter($banners));
        unset($validated['new_slide_files']);

        $this->updateSettings->execute($validated, $request->user());
        WebsiteSettingController::flushCache();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Banner and promo settings updated successfully.')]);

        return back();
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $path = $request->file('image')->store('banners', 'public');
        $url = Storage::disk('public')->url($path);

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => $url,
        ]);
    }
}
