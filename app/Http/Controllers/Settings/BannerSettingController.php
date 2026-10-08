<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateSettings;
use App\Http\Controllers\Api\v1\WebsiteSettingController;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return Inertia::render('settings/banners', [
            'settings' => [
                'banner_enabled' => (bool) ($settings['banner_enabled'] ?? true),
                'banner_eyebrow' => (string) ($settings['banner_eyebrow'] ?? 'Promo Spesial'),
                'banner_title' => (string) ($settings['banner_title'] ?? 'Diskon Early Bird 20%'),
                'banner_description' => (string) ($settings['banner_description'] ?? 'Pesan ruang impian Anda bulan ini dan nikmati potongan harga eksklusif untuk 3 bulan pertama.'),
                'banner_cta' => (string) ($settings['banner_cta'] ?? 'Klaim Promo'),
                'banner_image' => (string) ($settings['banner_image'] ?? 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1920&q=80'),
                'banner_autoplay_interval' => (int) ($settings['banner_autoplay_interval'] ?? 5000),
                'banners' => is_array($settings['banners'] ?? null) ? $settings['banners'] : [],
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
            'banner_autoplay_interval' => ['nullable', 'integer', 'min:2000', 'max:60000'],
            'banners' => ['nullable', 'array'],
            'banners.*' => ['nullable'],
            'promo_enabled' => ['sometimes', 'boolean'],
            'promo_text' => ['nullable', 'string', 'max:500'],
        ]);

        $this->updateSettings->execute($validated, $request->user());
        WebsiteSettingController::flushCache();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Banner and promo settings updated successfully.')]);

        return back();
    }
}
