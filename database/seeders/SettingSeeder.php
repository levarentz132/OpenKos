<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::set('site_name', 'OpenKOS');
        Setting::set('country_code', 'ID');
        Setting::set('locale', 'id');
        Setting::set('currency', 'IDR');
        Setting::set('timezone', 'Asia/Jakarta');
        Setting::set('lease_id_prefix', 'LSX');
        Setting::set('invoice_id_prefix', 'INV');

        $settings = app(\OpenKOS\Platform\Settings\SettingsManager::class);
        $settings->set('payment_gateway', 'doku');
        $settings->set('payment_gateway_config', [
            'doku' => [
                'environment' => 'sandbox',
                'client_id' => 'BRN-0208-1788852244810',
                'secret_key' => 'SK-vkKdx1b9ZOLoYiyeMuqz',
                'api_key' => 'doku_key_sandbox_11f95366b305485d8e9de31f9399cc8e',
                'callback_url' => 'http://127.0.0.1:8000/portal/billing',
            ],
        ]);
    }
}
