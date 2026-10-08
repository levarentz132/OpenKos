<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WebsiteSettingController extends Controller
{
    /**
     * Get all public website branding, banners, and configuration settings.
     */
    public function index(): JsonResponse
    {
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
                // Support both key-value schema (setting_key / setting_value) and (key / value)
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

        return response()->json($defaults);
    }

    /**
     * Get banners list specifically.
     */
    public function banners(): JsonResponse
    {
        $settings = $this->index()->getData(true);
        $banners = $settings['banners'] ?? [];

        return response()->json([
            'success' => true,
            'banner_enabled' => $settings['banner_enabled'] ?? true,
            'banner_eyebrow' => $settings['banner_eyebrow'] ?? 'Promo Spesial',
            'banner_title' => $settings['banner_title'] ?? 'Diskon Early Bird 20%',
            'banner_description' => $settings['banner_description'] ?? 'Pesan ruang impian Anda bulan ini dan nikmati potongan harga eksklusif.',
            'banner_cta' => $settings['banner_cta'] ?? 'Klaim Promo',
            'banner_image' => $settings['banner_image'] ?? 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1920&q=80',
            'banner_autoplay_interval' => $settings['banner_autoplay_interval'] ?? 5000,
            'banners' => $banners
        ]);
    }
}
