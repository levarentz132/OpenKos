<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ApplyGatewayPaymentResult;
use App\Results\Payment\ApplyGatewayPaymentResult as ApplyGatewayPaymentResultData;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenKOS\Core\Data\Payment\PaymentWebhookRequest;
use OpenKOS\Core\Exceptions\PaymentWebhookPayloadException;
use OpenKOS\Core\Exceptions\PaymentWebhookVerificationException;

class PaymentWebhookController extends Controller
{
    public function __construct(private PaymentGatewayManager $gateways) {}

    public function __invoke(
        Request $request,
        string $gateway,
        ApplyGatewayPaymentResult $apply,
    ): JsonResponse {
        $handler = $this->gateways->find($gateway);

        if ($handler === null) {
            return response()->json(['message' => 'Webhook target not found.'], 404);
        }

        try {
            $result = $handler->handleCallback(new PaymentWebhookRequest(
                rawBody: $request->getContent(),
                headers: $request->headers->all(),
            ));
        } catch (PaymentWebhookVerificationException) {
            return response()->json(['message' => 'Webhook verification failed.'], 401);
        } catch (PaymentWebhookPayloadException) {
            Log::warning('Authenticated payment webhook payload was invalid.', [
                'gateway' => $gateway,
            ]);

            return response()->json(['status' => 'invalid_payload'], 202);
        }

        // Check if webhook belongs to a pre-lease BookingOrder (Cart Order)
        $bookingOrder = \App\Models\BookingOrder::where('reference', $result->reference)
            ->orWhere('reference', $result->providerReference)
            ->first();

        if ($bookingOrder && $result->status === \OpenKOS\Core\Enums\PaymentStatus::Settled) {
            $fulfiller = app(\App\Actions\Bookings\FulfillBookingOrder::class);
            $fulfiller->execute(
                $bookingOrder,
                providerReference: $result->providerReference,
                occurredAt: $result->occurredAt,
            );

            \Illuminate\Support\Facades\Log::info('Payment webhook processed for booking order.', [
                'gateway' => $gateway,
                'booking_order_id' => $bookingOrder->id,
                'reference' => $bookingOrder->reference,
            ]);

            return response()->json(['status' => 'processed'], 200);
        }

        $processed = $apply->execute($gateway, $result);

        Log::info('Payment webhook processed.', [
            'gateway' => $gateway,
            'provider_reference' => $result->providerReference,
            'openkos_reference' => $result->reference,
            'event_reference' => $result->eventReference,
            'result' => $processed->status,
        ]);

        $status = in_array($processed->status, [
            ApplyGatewayPaymentResultData::PROCESSED,
            ApplyGatewayPaymentResultData::DUPLICATE,
        ], true) ? 200 : 202;

        return response()->json(['status' => $processed->status], $status);
    }
}
