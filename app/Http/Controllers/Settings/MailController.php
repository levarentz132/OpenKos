<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateSettings;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\MailManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use OpenKOS\Platform\Notification\NotificationDriverRegistration;
use OpenKOS\Platform\Notification\NotificationRegistry;

class MailController extends Controller
{
    public function __construct(
        private UpdateSettings $updateSettings,
        private NotificationRegistry $registry,
        private MailManager $mailManager,
    ) {}

    public function edit(): Response
    {
        $drivers = collect($this->registry->forChannel('mail'))
            ->map(function (NotificationDriverRegistration $registration) {
                $schema = [];
                try {
                    $class = $registration->driverClass;
                    $instance = app()->make($class, ['config' => []]);
                    if (method_exists($instance, 'configurationSchema')) {
                        $schema = $instance->configurationSchema();
                    }
                } catch (\Throwable) {
                    $schema = [];
                }

                return [
                    'name' => $registration->name,
                    'label' => $registration->label,
                    'configuration_schema' => $schema,
                ];
            })
            ->values();

        $mailConfig = Setting::effectiveMailConfig();
        unset($mailConfig['password']);

        return Inertia::render('settings/mail', [
            'drivers' => $drivers,
            'settings' => [
                'mail_config' => $mailConfig,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $registeredDrivers = array_map(
            fn (NotificationDriverRegistration $r) => $r->name,
            $this->registry->forChannel('mail'),
        );
        $allowedDrivers = array_unique(array_merge($registeredDrivers, ['openkos/smtp', 'openkos/log', 'smtp', 'log', 'sendmail']));

        $validated = $request->validate([
            'mail_config.driver' => ['nullable', 'string', 'in:'.implode(',', $allowedDrivers)],
            'mail_config.host' => ['nullable', 'string', 'max:255'],
            'mail_config.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_config.username' => ['nullable', 'string', 'max:255'],
            'mail_config.password' => ['nullable', 'string', 'max:255'],
            'mail_config.encryption' => ['nullable', 'string', 'in:tls,ssl,null'],
            'mail_config.from_address' => ['nullable', 'email', 'max:255'],
            'mail_config.from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $data = $validated['mail_config'] ?? [];

        $existing = Setting::get('mail_config') ?? [];
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $data = array_merge($existing, $data);

        $this->updateSettings->execute(['mail_config' => $data], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mail settings updated.')]);

        return back();
    }

    public function test(): RedirectResponse
    {
        $health = $this->mailManager->health();

        if ($health->healthy) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Mail configuration is healthy: :msg', ['msg' => $health->message])]);
        } else {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Mail configuration check failed: :msg', ['msg' => $health->message ?? __('Invalid config')])]);
        }

        return back();
    }
}
