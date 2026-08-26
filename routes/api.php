<?php

use App\Http\Controllers\Api\N8nRoomSyncController;
use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/payment/{gateway}', PaymentWebhookController::class)
    ->where('gateway', '.+')
    ->name('webhooks.payment');

Route::prefix('v1')->group(function () {
    Route::get('units/vacant', [N8nRoomSyncController::class, 'getVacantRooms'])->name('api.v1.units.vacant');
    Route::post('sync/units/empty-rooms', [N8nRoomSyncController::class, 'syncEmptyRooms'])->name('api.v1.sync.empty-rooms');
    Route::post('n8n/push-vacant', [N8nRoomSyncController::class, 'triggerPushToN8n'])->name('api.v1.n8n.push-vacant');
});

