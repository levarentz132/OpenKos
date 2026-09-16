<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\BookingOrder;
use App\Models\Unit;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenKOS\Core\Data\Payment\Money;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use Throwable;

class CartController extends Controller
{
    /**
     * View current active cart / pending booking orders.
     */
    public function index(Request $request): JsonResponse
    {
        $cartToken = $request->header('X-Cart-Token') ?? $request->query('cart_token');
        $phone = $request->query('phone');

        if (! $cartToken && ! $phone && ! $request->user()) {
            return response()->json([
                'cart' => [
                    'items' => [],
                    'total' => 0,
                    'count' => 0,
                ],
            ]);
        }

        $query = BookingOrder::query()
            ->with(['unit.property'])
            ->pending();

        if ($cartToken) {
            $query->where('cart_token', $cartToken);
        } elseif ($phone) {
            $query->where('guest_phone', preg_replace('/[^\d]/', '', $phone));
        } elseif ($user = $request->user()) {
            $query->where(function ($q) use ($user) {
                if ($user->phone) {
                    $q->where('guest_phone', $user->phone);
                }
                if ($user->email) {
                    $q->orWhere('guest_email', $user->email);
                }
            });
        }

        $items = $query->latest('id')->get();
        $total = $items->sum('amount');

        return response()->json([
            'cart' => [
                'cart_token' => $cartToken,
                'count' => $items->count(),
                'total' => (float) $total,
                'items' => $items->map(fn (BookingOrder $item) => [
                    'id' => $item->id,
                    'reference' => $item->reference,
                    'property_name' => $item->unit?->property?->name,
                    'unit_name' => $item->unit?->name,
                    'guest_name' => $item->guest_name,
                    'guest_phone' => $item->guest_phone,
                    'start_date' => $item->start_date->toDateString(),
                    'end_date' => $item->end_date?->toDateString(),
                    'duration_months' => $item->duration_months,
                    'amount' => (float) $item->amount,
                    'currency' => $item->currency,
                    'status' => $item->status,
                    'checkout_url' => $item->doku_checkout_url,
                    'expires_at' => $item->expires_at?->toIso8601String(),
                ]),
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
            'message' => 'Booking item removed from cart.',
        ]);
    }

    /**
     * Generate or refresh checkout URL for a cart item.
     */
    public function checkout(
        Request $request,
        BookingOrder $bookingOrder,
        PaymentGatewayManager $gatewayManager,
    ): JsonResponse {
        if ($bookingOrder->isPaid()) {
            return response()->json([
                'message' => 'This booking order has already been paid and leased.',
            ], 422);
        }

        $unit = Unit::with('property')->find($bookingOrder->unit_id);

        $doku = $gatewayManager->find('doku');
        if (! $doku) {
            return response()->json([
                'message' => 'Payment gateway is currently unavailable.',
            ], 503);
        }

        try {
            $paymentRequest = new PaymentRequest(
                reference: $bookingOrder->reference,
                amount: new Money((int) $bookingOrder->amount, 'IDR'),
                description: "Booking {$unit?->name} - {$unit?->property?->name}",
                metadata: [
                    'booking_order_id' => $bookingOrder->id,
                    'unit_id' => $bookingOrder->unit_id,
                    'guest_name' => $bookingOrder->guest_name,
                    'guest_phone' => $bookingOrder->guest_phone,
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
