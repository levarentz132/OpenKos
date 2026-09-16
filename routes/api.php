<?php

use App\Http\Controllers\Api\AvailableRoomsController;
use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\BookingOrderController;
use App\Http\Controllers\Api\v1\CartController;
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

    // Room Booking Orders & Cart
    Route::post('orders', [BookingOrderController::class, 'store'])->name('orders.store');
    Route::post('bookings', [BookingOrderController::class, 'store'])->name('bookings.store');

    Route::prefix('cart')->name('cart.')->group(function () {
        Route::get('/', [CartController::class, 'index'])->name('index');
        Route::post('/', [CartController::class, 'store'])->name('store');
        Route::delete('{bookingOrder}', [CartController::class, 'destroy'])->name('destroy');
        Route::post('{bookingOrder}/checkout', [CartController::class, 'checkout'])->name('checkout');
    });

    // Developer & Testing Sandbox Endpoints
    Route::prefix('sandbox')->name('sandbox.')->group(function () {
        Route::post('trial', [\App\Http\Controllers\Settings\PaymentGatewayTrialController::class, 'trialSession'])->name('trial');
        Route::post('simulate-payment', [\App\Http\Controllers\Settings\PaymentGatewayTrialController::class, 'simulateWebhook'])->name('simulate-payment');
    });

    // Authentication & Verification
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('check-status', [AuthController::class, 'checkStatus'])->name('check-status');

        // Email Verification Link
        Route::get('verify-email', [AuthController::class, 'verifyEmail'])->name('verify-email');
        Route::post('email/resend', [AuthController::class, 'resendVerificationEmail'])->name('email.resend');

        // Password Reset (Forgot Password) via WhatsApp or Email OTP
        Route::prefix('password')->name('password.')->group(function () {
            Route::post('forgot', [AuthController::class, 'forgotPassword'])->name('forgot');
            Route::post('reset', [AuthController::class, 'resetPassword'])->name('reset');
        });
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');

        // Dual-Channel OTP (WhatsApp or Email) - Supports Bearer Token OR email/phone
        Route::prefix('otp')->name('otp.')->group(function () {
            Route::post('send', [OtpController::class, 'send'])->name('send');
            Route::post('verify', [OtpController::class, 'verify'])->name('verify');
        });

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::delete('me', [AuthController::class, 'destroy'])->name('delete-account');
            Route::delete('users/{user}', [AuthController::class, 'deleteUser'])->name('users.destroy');
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
        Route::post('invoices/{invoice}/checkout', [TenantInvoiceController::class, 'checkout'])->name('invoices.checkout');

        // Maintenance Tickets
        Route::get('maintenance-tickets', [TenantMaintenanceController::class, 'index'])->name('maintenance-tickets.index');
        Route::post('maintenance-tickets', [TenantMaintenanceController::class, 'store'])->name('maintenance-tickets.store');
        Route::get('maintenance-tickets/{ticket}', [TenantMaintenanceController::class, 'show'])->name('maintenance-tickets.show');
    });
});


