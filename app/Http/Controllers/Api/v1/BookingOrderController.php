<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Models\BookingOrder;
use App\Models\Unit;
use App\Services\Payments\PaymentGatewayManager;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenKOS\Core\Data\Payment\Money;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use Throwable;

class BookingOrderController extends Controller
{
    /**
     * Create a pre-lease booking order stored in cart:
     * 1. Validates room availability and customer details.
     * 2. Stores booking order in database with status 'pending'.
     *    (Lease and Invoice are NOT created yet; Unit remains available).
     * 3. Generates DOKU Checkout link for the booking order.
     * 4. When payment is confirmed via DOKU webhook, the Lease is automatically created!
     */
    public function store(
        Request $request,
        PaymentGatewayManager $gatewayManager,
    ): JsonResponse {
        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'start_date' => ['required', 'date'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'notes' => ['nullable', 'string', 'max:500'],
            'cart_token' => ['nullable', 'string', 'max:100'],
        ]);

        $unit = Unit::with(['property', 'rates'])->findOrFail($validated['unit_id']);

        if (in_array($unit->status, [UnitStatus::Maintenance, UnitStatus::Unavailable, UnitStatus::Occupied], true)
            || ! app(\App\Business\Leases\OccupancyCalculator::class)->canAccommodate($unit, 1)) {
            return response()->json([
                'message' => 'This room is currently under maintenance or unavailable for booking.',
            ], 422);
        }

        $phone = $this->normalizePhoneNumber($validated['phone']);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $durationMonths = (int) ($validated['duration_months'] ?? 1);
        $endDate = $startDate->copy()->addMonthsNoOverflow($durationMonths)->format('Y-m-d');

        $monthlyRent = $unit->rates()->where('billing_unit', 'month')->where('billing_interval', 1)->value('amount')
            ?? $unit->activeRates()->first()?->amount
            ?? 0;

        $depositAmount = (float) (($unit->property?->deposit_amount && $unit->property->deposit_amount > 0) ? $unit->property->deposit_amount : 500000);
        $rentAmount = (float) $monthlyRent;
        $amount = (float) ($rentAmount + $depositAmount);

        $cartToken = $request->header('X-Cart-Token')
            ?? $validated['cart_token']
            ?? (string) Str::uuid();

        $reference = 'BK-' . strtoupper(Str::random(10));

        $user = $request->user('sanctum') ?? $request->user();
        $tenantId = null;
        if ($user) {
            if ($user instanceof \App\Models\Tenant) {
                $tenantId = $user->id;
            } elseif (isset($user->tenant_id) && $user->tenant_id) {
                $tenantId = $user->tenant_id;
            } elseif (isset($user->id)) {
                $tenantId = \App\Models\Tenant::where('user_id', $user->id)->orWhere('phone', $phone)->value('id');
            }
        }
        if (! $tenantId) {
            $tenantId = \App\Models\Tenant::where('phone', $phone)->value('id');
        }

        // Create the pre-lease booking order (Cart Item) with Rent + Security Deposit
        $bookingOrder = BookingOrder::create([
            'cart_token' => $cartToken,
            'reference' => $reference,
            'unit_id' => $unit->id,
            'tenant_id' => $tenantId,
            'guest_name' => $validated['name'],
            'guest_phone' => $phone,
            'guest_email' => $validated['email'] ?? null,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate,
            'duration_months' => $durationMonths,
            'amount' => $amount,
            'deposit_amount' => $depositAmount,
            'rent_amount' => $rentAmount,
            'currency' => 'IDR',
            'status' => BookingOrder::STATUS_PENDING,
            'notes' => $validated['notes'] ?? null,
            'expires_at' => now()->addMinutes(60),
        ]);

        // Generate DOKU Checkout Session Link
        $checkoutUrl = null;
        try {
            $doku = $gatewayManager->find('doku');
            if ($doku) {
                $frontendUrl = env('FRONTEND_URL')
                    ?? ($request->header('Origin') ? rtrim((string) $request->header('Origin'), '/') : null)
                    ?? config('services.doku.callback_url')
                    ?? 'http://localhost:5173';

                $callbackUrl = $request->input('callback_url')
                    ?? (rtrim($frontendUrl, '/') . '/?status=finish&order_id=' . $bookingOrder->id . '&reference=' . $bookingOrder->reference);

                $paymentRequest = new PaymentRequest(
                    reference: $bookingOrder->reference,
                    amount: new Money((int) $bookingOrder->amount, 'IDR'),
                    description: "Booking {$unit->name} - {$unit->property?->name}",
                    metadata: [
                        'booking_order_id' => $bookingOrder->id,
                        'unit_id' => $unit->id,
                        'guest_name' => $bookingOrder->guest_name,
                        'guest_phone' => $bookingOrder->guest_phone,
                        'callback_url' => $callbackUrl,
                    ],
                );

                $result = $doku->createPayment($paymentRequest);
                $checkoutUrl = $result->instructions->url;
                $bookingOrder->update(['doku_checkout_url' => $checkoutUrl]);
            }
        } catch (Throwable $e) {
            Log::warning('DOKU payment session creation for booking order failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Booking order created and saved in cart. Please complete payment to confirm your lease.',
            'order' => [
                'id' => $bookingOrder->id,
                'reference' => $bookingOrder->reference,
                'cart_token' => $cartToken,
                'status' => $bookingOrder->status,
                'property' => [
                    'id' => $unit->property?->id,
                    'name' => $unit->property?->name,
                    'address' => $unit->property?->address,
                ],
                'unit' => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                ],
                'guest' => [
                    'name' => $bookingOrder->guest_name,
                    'phone' => $bookingOrder->guest_phone,
                    'email' => $bookingOrder->guest_email,
                ],
                'period' => [
                    'start_date' => $bookingOrder->start_date->toDateString(),
                    'end_date' => $bookingOrder->end_date?->toDateString(),
                    'duration_months' => $bookingOrder->duration_months,
                ],
                'amount' => (float) $bookingOrder->amount,
                'currency' => $bookingOrder->currency,
                'checkout_url' => $checkoutUrl,
                'expires_at' => $bookingOrder->expires_at?->toIso8601String(),
                'lease_created' => false,
            ],
        ], 201);
    }

    private function normalizePhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^\d]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '62' . substr($phone, 1);
        }

        if (! str_starts_with($phone, '62') && strlen($phone) >= 9) {
            return '62' . $phone;
        }

        return $phone;
    }
}
