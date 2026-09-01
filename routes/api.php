<?php

use App\Http\Controllers\Api\AvailableRoomsController;
use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/payment/{gateway}', PaymentWebhookController::class)
    ->where('gateway', '.+')
    ->name('webhooks.payment');

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('available-rooms', [AvailableRoomsController::class, 'index'])->name('available-rooms');
    Route::get('properties/{property:slug}/available-rooms', [AvailableRoomsController::class, 'forProperty'])->name('properties.available-rooms');
});


