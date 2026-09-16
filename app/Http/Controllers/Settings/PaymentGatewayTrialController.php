<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Bookings\FulfillBookingOrder;
use App\Http\Controllers\Controller;
use App\Models\BookingOrder;
use App\Models\Unit;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenKOS\Core\Data\Payment\Money;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use Throwable;

class PaymentGatewayTrialController extends Controller
{
    public function __construct(
        private PaymentGatewayManager $gatewayManager,
        private FulfillBookingOrder $fulfiller,
    ) {}

    /**
     * Create a live DOKU Sandbox trial checkout session.
     */
    public function trialSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:1000', 'max:50000000'],
            'reference' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $doku = $this->gatewayManager->find('doku');
        if (! $doku) {
            return response()->json([
                'success' => false,
                'message' => 'DOKU Payment Gateway is not registered or unavailable.',
            ], 503);
        }

        $amount = (int) ($validated['amount'] ?? 10000);
        $reference = $validated['reference'] ?? ('TRIAL-' . strtoupper(Str::random(8)));
        $description = $validated['description'] ?? 'DOKU Sandbox Trial Payment (OpenKos Testing)';

        try {
            $paymentRequest = new PaymentRequest(
                reference: $reference,
                amount: new Money($amount, 'IDR'),
                description: $description,
                metadata: [
                    'type' => 'trial',
                    'callback_url' => url('/portal/billing?status=trial_finish&reference=' . $reference),
                ],
            );

            $result = $doku->createPayment($paymentRequest);

            return response()->json([
                'success' => true,
                'message' => 'DOKU Sandbox trial checkout session created successfully.',
                'checkout_url' => $result->instructions->url,
                'reference' => $reference,
                'amount' => $amount,
                'currency' => 'IDR',
                'environment' => 'sandbox',
                'expires_at' => $result->expiresAt?->format(\DateTimeInterface::ATOM),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize DOKU Sandbox session: ' . $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Simulate a DOKU webhook SUCCESS payment for testing.
     * Settles the booking order, generates lease, occupies unit, and marks invoice as paid.
     */
    public function simulateWebhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['nullable', 'string'],
            'booking_order_id' => ['nullable', 'integer', 'exists:booking_orders,id'],
        ]);

        $order = null;
        if (! empty($validated['booking_order_id'])) {
            $order = BookingOrder::find($validated['booking_order_id']);
        } elseif (! empty($validated['reference'])) {
            $order = BookingOrder::where('reference', $validated['reference'])->first();
        } else {
            // Find latest pending order
            $order = BookingOrder::pending()->latest('id')->first();
        }

        // If no pending order exists, create a quick sample booking order on the first available unit
        if (! $order) {
            $unit = Unit::where('status', \App\Enums\UnitStatus::Available)->first();
            if (! $unit) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pending order found and no available unit to create a test booking.',
                ], 422);
            }

            $order = BookingOrder::create([
                'cart_token' => (string) Str::uuid(),
                'reference' => 'BK-' . strtoupper(Str::random(10)),
                'unit_id' => $unit->id,
                'guest_name' => 'Trial User Sandbox',
                'guest_phone' => '62899999' . rand(1000, 9999),
                'guest_email' => 'trial.sandbox@example.com',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
                'duration_months' => 1,
                'amount' => 1000000,
                'currency' => 'IDR',
                'status' => BookingOrder::STATUS_PENDING,
                'notes' => 'Sandbox Trial Simulated Booking',
            ]);
        }

        if ($order->isPaid()) {
            return response()->json([
                'success' => true,
                'message' => 'This booking order is already fulfilled and paid.',
                'order' => $order,
            ]);
        }

        $providerReference = 'DOKU-SIM-' . uniqid();
        $fulfilled = $this->fulfiller->execute($order, $providerReference, now());

        return response()->json([
            'success' => true,
            'status' => $fulfilled->status,
            'message' => $fulfilled->status === BookingOrder::STATUS_PAID
                ? 'Simulation SUCCESS! Lease created, unit marked occupied, payment recorded, and invoice paid.'
                : 'Simulation completed with status: ' . $fulfilled->status,
            'order' => [
                'id' => $fulfilled->id,
                'reference' => $fulfilled->reference,
                'status' => $fulfilled->status,
                'lease_id' => $fulfilled->lease_id,
                'invoice_id' => $fulfilled->invoice_id,
                'unit_id' => $fulfilled->unit_id,
                'paid_at' => $fulfilled->paid_at?->toIso8601String(),
            ],
        ]);
    }
}
