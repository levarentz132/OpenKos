<?php

use App\Models\Setting;
use App\Models\User;
use Spatie\Permission\Models\Role;

describe('ReminderController with Late Fees', function () {
    it('saves late fee settings into database when settings form is saved', function () {
        Role::create(['name' => 'owner']);
        $user = User::factory()->create();
        $user->assignRole('owner');

        $response = $this->actingAs($user)->patch(route('settings.reminders.update'), [
            'reminder_enabled' => true,
            'reminder_days_before' => 3,
            'reminder_overdue_intervals' => '1, 3, 7',
            'reminder_message_templates' => [
                'upcoming' => 'Upcoming reminder template',
                'due_today' => 'Due today reminder template',
                'overdue' => 'Overdue reminder template',
            ],
            'reminder_channels' => ['log'],
            'late_fee_enabled' => true,
            'late_fee_type' => 'daily_flat',
            'late_fee_amount' => 25000,
            'late_fee_grace_days' => 5,
        ]);

        $response->assertRedirect();

        expect(Setting::get('late_fee_enabled'))->toBeTrue();
        expect(Setting::get('late_fee_type'))->toBe('daily_flat');
        expect(Setting::get('late_fee_amount'))->toBe(25000);
        expect(Setting::get('late_fee_grace_days'))->toBe(5);

        // Verify database persistence in settings table
        $dbRecord = \App\Models\Setting::where('key', 'late_fee_amount')->first();
        expect($dbRecord)->not->toBeNull();
        expect((int) $dbRecord->value)->toBe(25000);
    });
});
