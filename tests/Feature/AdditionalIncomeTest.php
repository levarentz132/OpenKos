<?php

use App\Models\AdditionalIncome;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

test('additional income index requires authentication', function () {
    $this->get('/additional-incomes')->assertRedirect('login');
});

test('owner can create additional income entry', function () {
    $user = User::factory()->owner()->create();

    $response = $this->actingAs($user)->post('/additional-incomes', [
        'title' => 'Laundry Services August',
        'category' => 'laundry',
        'amount' => 750000,
        'income_date' => '2026-08-15',
        'notes' => 'Weekly collection from automatic laundry machines',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('additional_incomes', [
        'title' => 'Laundry Services August',
        'category' => 'laundry',
        'amount' => 750000.00,
        'recorded_by' => $user->id,
    ]);
});

test('owner can update additional income entry', function () {
    $user = User::factory()->owner()->create();

    $income = AdditionalIncome::create([
        'title' => 'Initial Service Fee',
        'category' => 'services',
        'amount' => 200000,
        'income_date' => '2026-08-10',
        'recorded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->put("/additional-incomes/{$income->id}", [
        'title' => 'Updated Service Fee',
        'category' => 'services',
        'amount' => 250000,
        'income_date' => '2026-08-10',
        'notes' => 'Updated price',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('additional_incomes', [
        'id' => $income->id,
        'title' => 'Updated Service Fee',
        'amount' => 250000.00,
    ]);
});

test('owner can delete additional income entry', function () {
    $user = User::factory()->owner()->create();

    $income = AdditionalIncome::create([
        'title' => 'Vending Machine',
        'category' => 'vending',
        'amount' => 150000,
        'income_date' => '2026-08-01',
    ]);

    $response = $this->actingAs($user)->delete("/additional-incomes/{$income->id}");

    $response->assertRedirect();

    $this->assertDatabaseMissing('additional_incomes', [
        'id' => $income->id,
    ]);
});

test('report includes additional non-property income in monthly totals', function () {
    $user = User::factory()->owner()->create();

    AdditionalIncome::create([
        'title' => 'General Parking Fee',
        'category' => 'parking',
        'amount' => 500000,
        'income_date' => '2026-08-05',
    ]);

    $response = $this->actingAs($user)->get('/reports?start_date=2026-08-01&end_date=2026-08-31');

    $response->assertOk()
        ->assertInertia(function ($page) {
            $months = $page->toArray()['props']['income_report']['months'];
            $august = collect($months)->firstWhere('month_key', '2026-08');

            expect($august)->not->toBeNull()
                ->and($august['additional_income_total'])->toEqual(500000)
                ->and($august['additional_incomes'])->toHaveCount(1)
                ->and($august['additional_incomes'][0]['title'])->toEqual('General Parking Fee');
        });
});
