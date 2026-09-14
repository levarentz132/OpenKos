<?php

use App\Http\Controllers\Api\AvailableRoomsController;
use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\OtpController;
use App\Http\Controllers\Api\v1\PhoneVerificationController;
use App\Http\Controllers\Api\v1\Tenant\TenantDashboardController;
use App\Http\Controllers\Api\v1\Tenant\TenantInvoiceController;
use App\Http\Controllers\Api\v1\Tenant\TenantLeaseController;
use App\Http\Controllers\Api\v1\Tenant\TenantMaintenanceController;
use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/payment/{gateway}', PaymentWebhookController::class)
    ->where('gateway', '.+')
    ->name('webhooks.payment');

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('available-rooms', [AvailableRoomsController::class, 'index'])->name('available-rooms');
    Route::get('properties/{property:slug}/available-rooms', [AvailableRoomsController::class, 'forProperty'])->name('properties.available-rooms');

    // Authentication & Verification
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');

        // Dual-Channel OTP (WhatsApp or Email) - Supports Bearer Token OR email/phone
        Route::prefix('otp')->name('otp.')->group(function () {
            Route::post('send', [OtpController::class, 'send'])->name('send');
            Route::post('verify', [OtpController::class, 'verify'])->name('verify');
        });

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');

            // Backward-compatible phone aliases
            Route::prefix('phone')->name('phone.')->group(function () {
                Route::post('send-otp', [PhoneVerificationController::class, 'sendOtp'])->name('send-otp');
                Route::post('verify-otp', [PhoneVerificationController::class, 'verifyOtp'])->name('verify-otp');
            });
        });
    });

    // Tenant Portal Data API (Secured with Sanctum)
    Route::middleware('auth:sanctum')->prefix('tenant')->name('tenant.')->group(function () {
        // Dashboard Overview
        Route::get('dashboard', [TenantDashboardController::class, 'index'])->name('dashboard');

        // Leases
        Route::get('leases', [TenantLeaseController::class, 'index'])->name('leases.index');
        Route::get('leases/{lease}', [TenantLeaseController::class, 'show'])->name('leases.show');

        // Invoices & Payments
        Route::get('invoices', [TenantInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [TenantInvoiceController::class, 'show'])->name('invoices.show');
        Route::post('invoices/{invoice}/pay', [TenantInvoiceController::class, 'submitPayment'])->name('invoices.pay');

        // Maintenance Tickets
        Route::get('maintenance-tickets', [TenantMaintenanceController::class, 'index'])->name('maintenance-tickets.index');
        Route::post('maintenance-tickets', [TenantMaintenanceController::class, 'store'])->name('maintenance-tickets.store');
        Route::get('maintenance-tickets/{ticket}', [TenantMaintenanceController::class, 'show'])->name('maintenance-tickets.show');
    });
});


