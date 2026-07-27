<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateSettings;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\MailManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
                $class = $registration->driverClass;
                $instance = new $class([]);

                return [
                    'name' => $registration->name,
                    'label' => $registration->label,
                    'configuration_schema' => method_exists($instance, 'configurationSchema') ? $instance->configurationSchema() : [],
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
        $validated = $request->validate([
            'mail_config.driver' => ['nullable', 'string', 'in:smtp,log,sendmail'],
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
        $config = Setting::effectiveMailConfig();

        if (! filled($config['host'] ?? null)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Configure SMTP host before testing.')]);

            return back();
        }

        try {
            Mail::raw(__('Test email from OpenKOS.'), function ($message) use ($config): void {
                $message->to($config['from_address'] ?? '')
                    ->subject(__('Test Email'));
            });

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Test email sent.')]);
        } catch (\Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Failed to send: :error', ['error' => $e->getMessage()])]);
        }

        return back();
    }
}
