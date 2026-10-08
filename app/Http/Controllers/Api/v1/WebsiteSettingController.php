<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WebsiteSettingController extends Controller
{
    /**
     * Cache duration in seconds (10 minutes).
     */
    private const CACHE_TTL_SECONDS = 600;

    /**
     * Get all public website branding, banners, and configuration settings.
     * Fully cached in-memory/Redis/file cache to support thousands of active users with near 0 DB load.
     */
    public function index(Request $request): JsonResponse
    {
        $settings = Cache::remember('website_public_settings_v1', self::CACHE_TTL_SECONDS, function () {
            $defaults = [
                'logo_text' => 'HS',
                'logo_gradient_start' => '#89AACC',
                'logo_gradient_end' => '#4E85BF',
                'banner_eyebrow' => 'Promo Spesial',
                'banner_title' => 'Diskon Early Bird 20%',
                'banner_description' => 'Pesan ruang impian Anda bulan ini dan nikmati potongan harga eksklusif untuk 3 bulan pertama.',
                'banner_image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1920&q=80',
                'banner_cta' => 'Klaim Promo',
                'banner_autoplay_interval' => 5000,
                'banner_enabled' => true,
                'promo_enabled' => 'false',
                'promo_text' => '',
                'logo_image' => '',
                'whatsapp_number' => '628123456789',
                'banners' => [],
                'facilities_premium' => []
            ];

            try {
                if (Schema::hasTable('settings')) {
                    $hasSettingKeyCol = Schema::hasColumn('settings', 'setting_key');
                    $keyCol = $hasSettingKeyCol ? 'setting_key' : 'key';
                    $valCol = $hasSettingKeyCol ? 'setting_value' : 'value';

                    $rows = DB::table('settings')->select([$keyCol . ' as k', $valCol . ' as v'])->get();
                    foreach ($rows as $row) {
                        $val = $row->v;
                        if (is_string($val)) {
                            $decoded = json_decode($val, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $val = $decoded;
                            }
                        }
                        $defaults[$row->k] = $val;
                    }
                }
            } catch (\Throwable $e) {
                // Fallback to safe defaults if DB is temporarily unreachable
            }

            return $defaults;
        });

        $etag = '"' . md5(json_encode($settings)) . '"';

        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=60, stale-while-revalidate=300'
            ]);
        }

        return response()->json($settings, 200, [
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=60, stale-while-revalidate=300'
        ]);
    }

    /**
     * Get banners list specifically with caching.
     */
    public function banners(Request $request): JsonResponse
    {
        $bannerPayload = Cache::remember('website_public_banners_v1', self::CACHE_TTL_SECONDS, function () use ($request) {
            $settings = $this->index($request)->getData(true) ?? [];
            $banners = $settings['banners'] ?? [];

            return [
                'success' => true,
                'banner_enabled' => $settings['banner_enabled'] ?? true,
                'banner_eyebrow' => $settings['banner_eyebrow'] ?? 'Promo Spesial',
                'banner_title' => $settings['banner_title'] ?? 'Diskon Early Bird 20%',
                'banner_description' => $settings['banner_description'] ?? 'Pesan ruang impian Anda bulan ini dan nikmati potongan harga eksklusif.',
                'banner_cta' => $settings['banner_cta'] ?? 'Klaim Promo',
                'banner_image' => $settings['banner_image'] ?? 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1920&q=80',
                'banner_autoplay_interval' => $settings['banner_autoplay_interval'] ?? 5000,
                'banners' => $banners
            ];
        });

        $etag = '"' . md5(json_encode($bannerPayload)) . '"';

        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=60, stale-while-revalidate=300'
            ]);
        }

        return response()->json($bannerPayload, 200, [
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=60, stale-while-revalidate=300'
        ]);
    }

    /**
     * Helper to invalidate public settings cache when admin updates settings.
     */
    public static function flushCache(): void
    {
        Cache::forget('website_public_settings_v1');
        Cache::forget('website_public_banners_v1');
    }
}

