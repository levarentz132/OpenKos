<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateSettings;
use App\Enums\ReminderType;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReminderController extends Controller
{
    public function __construct(
        private UpdateSettings $updateSettings,
    ) {}

    public function edit(): Response
    {
        $settings = Setting::some([
            'reminder_enabled',
            'reminder_days_before',
            'reminder_overdue_intervals',
            'reminder_message_templates',
            'reminder_channels',
            'late_fee_enabled',
            'late_fee_type',
            'late_fee_amount',
            'late_fee_grace_days',
        ]);

        $legacyTemplate = Setting::get('reminder_message_template');
        $templates = is_array($settings['reminder_message_templates'])
            ? $settings['reminder_message_templates']
            : [];

        $settings['reminder_message_templates'] = collect(ReminderType::cases())
            ->mapWithKeys(fn (ReminderType $type): array => [
                $type->value => $templates[$type->value] ?? $legacyTemplate ?? '',
            ])
            ->all();
        $settings['reminder_channels'] ??= ['log'];

        return Inertia::render('settings/reminders', [
            'settings' => $settings,
            'defaultTemplates' => collect(ReminderType::cases())
                ->mapWithKeys(fn (ReminderType $type): array => [
                    $type->value => __("notifications.rent.{$type->value}"),
                ])
                ->all(),
            'previewInvoiceContext' => __('notifications.rent.invoice_context', [
                'reference' => 'INV-2026-07',
                'period' => '01 Jul 2026 – 31 Jul 2026',
                'date' => '01 Jul 2026',
                'amount' => '1,500,000',
            ]),
            'previewInvoiceLink' => __('notifications.rent.view_invoice').': https://example.test/portal/billing/invoices/1',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reminder_enabled' => ['boolean'],
            'reminder_days_before' => ['required', 'integer', 'min:0', 'max:30'],
            'reminder_overdue_intervals' => ['required', 'string', 'regex:/^\d+(?:\s*,\s*\d+)*$/'],
            'reminder_message_templates' => ['required', 'array:upcoming,due_today,overdue'],
            'reminder_message_templates.upcoming' => ['nullable', 'string', 'max:1000'],
            'reminder_message_templates.due_today' => ['nullable', 'string', 'max:1000'],
            'reminder_message_templates.overdue' => ['nullable', 'string', 'max:1000'],
            'reminder_channels' => ['required', 'array', 'min:1'],
            'reminder_channels.*' => ['string', 'in:log,whatsapp,mail'],
            'late_fee_enabled' => ['nullable', 'boolean'],
            'late_fee_type' => ['required', 'string', 'in:flat,daily_flat,percentage'],
            'late_fee_amount' => ['required', 'integer', 'min:0'],
            'late_fee_grace_days' => ['required', 'integer', 'min:0', 'max:90'],
        ]);

        $validated['reminder_overdue_intervals'] = array_map(
            fn ($v) => (int) trim($v),
            explode(',', $validated['reminder_overdue_intervals']),
        );

        $this->updateSettings->execute($validated, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder settings updated.')]);

        return back();
    }
}
