<?php

namespace App\Http\Controllers\Api\v1;

use App\Business\Leases\OccupancyCalculator;
use App\Enums\UnitStatus;
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

class CartController extends Controller
{
    /**
     * View current active cart / pending booking orders.
     */
    public function index(
        Request $request,
        OccupancyCalculator $occupancy,
        ?PaymentGatewayManager $gatewayManager = null,
    ): JsonResponse {
        $gatewayManager = $gatewayManager ?? app(PaymentGatewayManager::class);
        $cartToken = $request->header('X-Cart-Token') ?? $request->query('cart_token');
        $phone = $request->query('phone');
        $user = $request->user('sanctum') ?? $request->user();

        if (! $cartToken && ! $phone && ! $user) {
            return response()->json([
                'cart' => [
                    'items' => [],
                    'total' => 0,
                    'count' => 0,
                ],
            ]);
        }

        $status = $request->query('status');
        $query = BookingOrder::query()
            ->with(['unit.property']);

        if ($status === 'pending') {
            $query->pending();
        } elseif ($status === 'all') {
            // Include all statuses without filtering
        } elseif ($status) {
            $query->where('status', $status);
        } else {
            // Default: Active user cart ONLY shows pending (unpaid) booking orders.
            // When an order is paid, it has successfully transitioned to an active Lease and Invoice,
            // so the active cart becomes empty (0 items).
            $query->pending();
        }

        if ($user) {
            $userPhone = $user->phone ? preg_replace('/[^\d]/', '', $user->phone) : null;
            $userEmail = $user->email ?? null;
            $tenantId = null;
            if ($user instanceof \App\Models\Tenant) {
                $tenantId = $user->id;
            } elseif (isset($user->tenant_id) && $user->tenant_id) {
                $tenantId = $user->tenant_id;
            }

            $query->where(function ($q) use ($userPhone, $userEmail, $tenantId, $cartToken) {
                $hasCondition = false;
                if ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                    $hasCondition = true;
                }
                if ($userPhone) {
                    if ($hasCondition) {
                        $q->orWhere('guest_phone', $userPhone);
                    } else {
                        $q->where('guest_phone', $userPhone);
                        $hasCondition = true;
                    }
                }
                if ($userEmail) {
                    if ($hasCondition) {
                        $q->orWhere('guest_email', $userEmail);
                    } else {
                        $q->where('guest_email', $userEmail);
                        $hasCondition = true;
                    }
                }
                if ($cartToken) {
                    if ($hasCondition) {
                        $q->orWhere('cart_token', $cartToken);
                    } else {
                        $q->where('cart_token', $cartToken);
                    }
                }
            });
        } elseif ($phone) {
            $normalizedPhone = preg_replace('/[^\d]/', '', $phone);
            $query->where('guest_phone', $normalizedPhone);
        } elseif ($cartToken) {
            $query->where('cart_token', $cartToken);
        }

        $items = $query->latest('id')->get();

        // Auto-reconciliation: Query DOKU API directly for pending items with active reference
        $doku = $gatewayManager->find('doku');
        if ($doku) {
            foreach ($items as $item) {
                if ($item->status === BookingOrder::STATUS_PENDING && $item->reference) {
                    try {
                        $lookupReq = new \OpenKOS\Core\Data\Payment\PaymentStatusLookupRequest(
                            providerReference: $item->reference,
                            reference: $item->reference
                        );
                        $lookupRes = $doku->lookupPaymentStatus($lookupReq);
                        if ($lookupRes->status === \OpenKOS\Core\Enums\PaymentStatus::Settled) {
                            $fulfiller = app(\App\Actions\Bookings\FulfillBookingOrder::class);
                            $fulfiller->execute(
                                $item,
                                providerReference: $item->reference,
                                occurredAt: $lookupRes->occurredAt ?? now()
                            );
                            $item->refresh();
                        }
                    } catch (\Throwable $e) {
                        // Skip if inquiry is unsupported or fails
                    }
                }
            }
        }
        $total = $items->where('status', BookingOrder::STATUS_PENDING)->sum('amount');

        return response()->json([
            'cart' => [
                'cart_token' => $cartToken,
                'count' => $items->count(),
                'total' => (float) $total,
                'items' => $items->map(function (BookingOrder $item) use ($occupancy) {
                    $unit = $item->unit;
                    $isAvailable = $unit
                        && $unit->status === UnitStatus::Available
                        && $occupancy->canAccommodate($unit, 1);

                    $conflictMessage = null;
                    if (! $isAvailable && ! $item->isPaid()) {
                        $conflictMessage = 'Kamar ini sudah terisi atau tidak tersedia lagi karena telah dibayar oleh pengguna lain.';
                    }

                    return [
                        'id' => $item->id,
                        'reference' => $item->reference,
                        'property_name' => $unit?->property?->name,
                        'unit_name' => $unit?->name,
                        'guest_name' => $item->guest_name,
                        'guest_phone' => $item->guest_phone,
                        'start_date' => $item->start_date->toDateString(),
                        'end_date' => $item->end_date?->toDateString(),
                        'duration_months' => $item->duration_months,
                        'amount' => (float) $item->amount,
                        'deposit_amount' => (float) ($item->deposit_amount ?? $unit?->property?->deposit_amount ?? 500000),
                        'rent_amount' => (float) ($item->rent_amount ?? (($item->amount) - ($item->deposit_amount ?? $unit?->property?->deposit_amount ?? 500000))),
                        'currency' => $item->currency,
                        'status' => $item->status,
                        'is_paid' => $item->isPaid(),
                        'lease_id' => $item->lease_id,
                        'invoice_id' => $item->invoice_id,
                        'paid_at' => $item->paid_at?->toIso8601String(),
                        'is_available' => $item->isPaid() ? true : $isAvailable,
                        'conflict_message' => $conflictMessage,
                        'checkout_url' => $item->doku_checkout_url,
                        'expires_at' => $item->expires_at?->toIso8601String(),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Add booking order to cart (delegates to BookingOrderController store).
     */
    public function store(
        Request $request,
        BookingOrderController $orderController,
        PaymentGatewayManager $gatewayManager,
    ): JsonResponse {
        return $orderController->store($request, $gatewayManager);
    }

    /**
     * Remove an item from cart.
     */
    public function destroy(Request $request, BookingOrder $bookingOrder): JsonResponse
    {
        if ($bookingOrder->isPaid()) {
            return response()->json([
                'message' => 'Cannot remove paid booking order.',
            ], 422);
        }

        $bookingOrder->update(['status' => BookingOrder::STATUS_CANCELLED]);

        return response()->json([
            'message' => 'Pesanan kamar berhasil dibatalkan.',
        ]);
    }

    /**
     * Generate or refresh checkout URL for a cart item.
     * Prevents checkout if another user has already paid and occupied the unit!
     */
    public function checkout(
        Request $request,
        BookingOrder $bookingOrder,
        PaymentGatewayManager $gatewayManager,
        OccupancyCalculator $occupancy,
    ): JsonResponse {
        if ($bookingOrder->isPaid()) {
            return response()->json([
                'code' => 'ORDER_ALREADY_PAID',
                'message' => 'Pesanan ini sudah berhasil dibayar dan kontrak sewa #' . ($bookingOrder->lease_id ?? 'Aktif') . ' telah aktif.',
                'order' => [
                    'id' => $bookingOrder->id,
                    'reference' => $bookingOrder->reference,
                    'status' => $bookingOrder->status,
                    'is_paid' => true,
                    'lease_id' => $bookingOrder->lease_id,
                    'invoice_id' => $bookingOrder->invoice_id,
                ],
            ], 200);
        }

        if ($bookingOrder->isCancelled()) {
            return response()->json([
                'code' => 'ORDER_CANCELLED',
                'message' => 'Pesanan ini sudah dibatalkan. Silakan lakukan pemesanan ulang.',
            ], 422);
        }

        $unit = Unit::with('property')->find($bookingOrder->unit_id);

        // Conflict check: Has another user already occupied/paid for this unit?
        if (! $unit || $unit->status !== UnitStatus::Available || ! $occupancy->canAccommodate($unit, 1)) {
            $bookingOrder->update([
                'status' => BookingOrder::STATUS_CANCELLED,
                'notes' => ($bookingOrder->notes ? $bookingOrder->notes . ' | ' : '') . 'Dibatalkan: Kamar telah dibayar atau terisi oleh pengguna lain.',
            ]);

            return response()->json([
                'code' => 'ROOM_ALREADY_PAID',
                'message' => 'Maaf, kamar ini baru saja disewa dan dibayar oleh pengguna lain. Silakan pilih kamar lain yang masih tersedia.',
            ], 422);
        }

        // If checkout session is still active and valid, reuse it to avoid duplicate DOKU invoices
        if ($bookingOrder->doku_checkout_url && $bookingOrder->expires_at && $bookingOrder->expires_at->isFuture()) {
            return response()->json([
                'message' => 'Menggunakan sesi pembayaran aktif.',
                'checkout_url' => $bookingOrder->doku_checkout_url,
                'order' => [
                    'id' => $bookingOrder->id,
                    'reference' => $bookingOrder->reference,
                    'amount' => (float) $bookingOrder->amount,
                    'status' => $bookingOrder->status,
                ],
            ]);
        }

        $doku = $gatewayManager->find('doku');
        if (! $doku) {
            return response()->json([
                'message' => 'Payment gateway is currently unavailable.',
            ], 503);
        }

        $frontendUrl = env('FRONTEND_URL')
            ?? ($request->header('Origin') ? rtrim((string) $request->header('Origin'), '/') : null)
            ?? config('services.doku.callback_url')
            ?? 'http://localhost:5173';

        // Generate a fresh unique reference to prevent DOKU's "INVOICE ALREADY USED" error
        $newReference = 'BK-' . strtoupper(Str::random(10));
        $bookingOrder->update([
            'reference' => $newReference,
            'doku_checkout_url' => null,
        ]);

        try {
            $callbackUrl = $request->input('callback_url')
                ?? (rtrim($frontendUrl, '/') . '/?status=finish&order_id=' . $bookingOrder->id . '&reference=' . $bookingOrder->reference);

            $paymentRequest = new PaymentRequest(
                reference: $bookingOrder->reference,
                amount: new Money((int) $bookingOrder->amount, 'IDR'),
                description: "Booking {$unit?->name} - {$unit?->property?->name}",
                metadata: [
                    'booking_order_id' => $bookingOrder->id,
                    'unit_id' => $bookingOrder->unit_id,
                    'guest_name' => $bookingOrder->guest_name,
                    'guest_phone' => $bookingOrder->guest_phone,
                    'callback_url' => $callbackUrl,
                ],
            );

            $result = $doku->createPayment($paymentRequest);
            $checkoutUrl = $result->instructions->url;
            $bookingOrder->update([
                'doku_checkout_url' => $checkoutUrl,
                'expires_at' => now()->addMinutes(60),
            ]);

            return response()->json([
                'message' => 'Checkout URL generated successfully.',
                'checkout_url' => $checkoutUrl,
                'order' => [
                    'id' => $bookingOrder->id,
                    'reference' => $bookingOrder->reference,
                    'amount' => (float) $bookingOrder->amount,
                    'status' => $bookingOrder->status,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to initialize DOKU Checkout: ' . $e->getMessage(),
            ], 502);
        }
    }
}
