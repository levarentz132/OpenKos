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

        $storedMailConfig = Setting::get('mail_config') ?? [];
        $storedMailConfig = is_array($storedMailConfig) ? $storedMailConfig : [];
        $mailConfig = Setting::effectiveMailConfig();
        $credentialStatus = [];
        unset($mailConfig['password']);

        foreach ($storedMailConfig['drivers'] ?? [] as $driver => $driverConfig) {
            if (! is_array($driverConfig)) {
                continue;
            }

            $credentialStatus[$driver] = collect(['key', 'api_key', 'password', 'token', 'secret'])
                ->contains(fn (string $field) => filled($driverConfig[$field] ?? null));

            foreach (['key', 'api_key', 'password', 'token', 'secret'] as $field) {
                unset($mailConfig['drivers'][$driver][$field]);
            }
        }

        foreach ($mailConfig['drivers'] ?? [] as $driver => $driverConfig) {
            if (! is_array($driverConfig)) {
                continue;
            }

            foreach (['key', 'api_key', 'password', 'token', 'secret'] as $field) {
                unset($mailConfig['drivers'][$driver][$field]);
            }
        }

        return Inertia::render('settings/mail', [
            'drivers' => $drivers,
            'settings' => [
                'mail_config' => $mailConfig,
                'credential_status' => $credentialStatus,
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
            'mail_config.drivers' => ['nullable', 'array'],
        ]);

        $submitted = $validated['mail_config'] ?? [];
        $existing = Setting::get('mail_config') ?? [];

        if (blank($submitted['password'] ?? null)) {
            unset($submitted['password']);
        }

        $data = array_merge($existing, $submitted);

        if (isset($submitted['drivers']) && is_array($submitted['drivers'])) {
            $drivers = is_array($existing['drivers'] ?? null) ? $existing['drivers'] : [];

            foreach ($submitted['drivers'] as $driver => $driverConfig) {
                if (! is_array($driverConfig)) {
                    continue;
                }

                foreach (['key', 'api_key', 'password', 'token', 'secret'] as $field) {
                    if (blank($driverConfig[$field] ?? null)) {
                        unset($driverConfig[$field]);
                    }
                }

                $drivers[$driver] = array_merge(
                    is_array($drivers[$driver] ?? null) ? $drivers[$driver] : [],
                    $driverConfig,
                );
            }

            $data['drivers'] = $drivers;
        }

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
