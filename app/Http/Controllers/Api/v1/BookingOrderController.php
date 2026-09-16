<?php

namespace App\Http\Controllers\Api\v1;

use App\Actions\Leases\CreateLease;
use App\Actions\Payments\StartGatewayPayment;
use App\Data\Lease\CreateLeaseData;
use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BookingOrderController extends Controller
{
    /**
     * Create a room booking order:
     * 1. Finds or creates the Tenant and User profile.
     * 2. Creates the Lease on the Unit (which occupies the unit).
     * 3. Automatically generates the first Invoice.
     * 4. Generates an online payment checkout session (e.g. DOKU Checkout).
     * 5. Returns the lease, invoice, and DOKU checkout URL.
     */
    public function store(
        Request $request,
        CreateLease $createLease,
        StartGatewayPayment $startGatewayPayment,
    ): JsonResponse {
        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'start_date' => ['required', 'date'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $unit = Unit::with(['property', 'rates'])->findOrFail($validated['unit_id']);

        if (in_array($unit->status, [UnitStatus::Maintenance, UnitStatus::Unavailable], true)) {
            return response()->json([
                'message' => 'This room is currently under maintenance or unavailable for booking.',
            ], 422);
        }

        $phone = $this->normalizePhoneNumber($validated['phone']);

        try {
            $orderData = DB::transaction(function () use ($validated, $unit, $phone, $createLease) {
                // 1. Find or create User & Tenant
                $tenant = Tenant::where('phone', $phone)
                    ->when(! empty($validated['email']), fn ($q) => $q->orWhere('email', $validated['email']))
                    ->first();

                if (! $tenant) {
                    $user = User::where('phone', $phone)
                        ->when(! empty($validated['email']), fn ($q) => $q->orWhere('email', $validated['email']))
                        ->first();

                    if (! $user) {
                        $user = User::create([
                            'name' => $validated['name'],
                            'phone' => $phone,
                            'email' => $validated['email'] ?? null,
                            'password' => Hash::make(Str::random(16)),
                        ]);
                    }

                    $tenant = Tenant::create([
                        'user_id' => $user->id,
                        'name' => $validated['name'],
                        'phone' => $phone,
                        'email' => $validated['email'] ?? null,
                        'password' => Hash::make(Str::random(16)),
                        'is_active' => true,
                    ]);
                }

                // Check if tenant already has an active lease
                $hasActiveLease = $tenant->leases()
                    ->where('status', LeaseStatus::Active->value)
                    ->exists();

                if ($hasActiveLease) {
                    abort(422, 'This tenant already has an active lease. Please contact management.');
                }

                // 2. Calculate lease dates & rent amount
                $startDate = Carbon::parse($validated['start_date'])->startOfDay();
                $durationMonths = (int) ($validated['duration_months'] ?? 1);
                $endDate = $startDate->copy()->addMonthsNoOverflow($durationMonths)->format('Y-m-d');

                $rentAmount = $unit->rates()->where('billing_unit', 'month')->where('billing_interval', 1)->value('amount')
                    ?? $unit->activeRates()->first()?->amount
                    ?? 0;

                $leaseData = new CreateLeaseData(
                    tenantIds: [$tenant->id],
                    startDate: $startDate->format('Y-m-d'),
                    endDate: $endDate,
                    rentAmount: $rentAmount,
                    billingInterval: 1,
                    billingUnit: 'month',
                    billingStrategy: 'advance',
                    unitRateId: null,
                    depositAmount: 0,
                    depositPaidAt: null,
                    depositRefundAmount: null,
                    depositRefundedAt: null,
                    rentDueDay: (int) $startDate->format('j'),
                    notes: $validated['notes'] ?? 'Online booking order',
                );

                // 3. Execute CreateLease (occupies unit and generates invoice)
                $lease = $createLease->execute($unit, $leaseData);

                // 4. Retrieve newly generated pending invoice
                $invoice = $lease->invoices()
                    ->where('status', InvoiceStatus::Pending->value)
                    ->latest('id')
                    ->first();

                return [
                    'tenant' => $tenant,
                    'lease' => $lease,
                    'invoice' => $invoice,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate,
                    'duration_months' => $durationMonths,
                ];
            });
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Failed to create booking order.',
            ], $e->getCode() >= 400 && $e->getCode() < 500 ? $e->getCode() : 422);
        }

        $tenant = $orderData['tenant'];
        $lease = $orderData['lease'];
        $invoice = $orderData['invoice'];

        // 5. Generate DOKU Checkout Session Link
        $checkoutUrl = null;
        $attemptData = null;

        if ($invoice) {
            try {
                $paymentResult = $startGatewayPayment->executeViaSignedLink($invoice);
                $checkoutUrl = $paymentResult->instructions->url;
                $attemptData = [
                    'id' => $paymentResult->attempt->id,
                    'reference' => $paymentResult->attempt->reference,
                    'amount' => (float) $paymentResult->attempt->amount,
                    'status' => $paymentResult->attempt->status->value,
                    'expires_at' => $paymentResult->attempt->expires_at?->toIso8601String(),
                ];
            } catch (Throwable $e) {
                Log::warning('Booking created but checkout session link generation failed: ' . $e->getMessage());
            }
        }

        // 6. Generate Sanctum token for tenant
        $token = $tenant->createToken('booking-session')->plainTextToken;

        return response()->json([
            'message' => 'Order created successfully. Lease and invoice have been generated.',
            'order' => [
                'lease_id' => $lease->id,
                'lease_reference' => $lease->reference,
                'property' => [
                    'id' => $unit->property?->id,
                    'name' => $unit->property?->name,
                    'address' => $unit->property?->address,
                ],
                'unit' => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                ],
                'period' => [
                    'start_date' => $orderData['start_date'],
                    'end_date' => $orderData['end_date'],
                    'duration_months' => $orderData['duration_months'],
                ],
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'phone' => $tenant->phone,
                    'email' => $tenant->email,
                ],
                'invoice' => $invoice ? [
                    'id' => $invoice->id,
                    'reference' => $invoice->reference,
                    'status' => $invoice->status->value,
                    'total' => (float) $invoice->total,
                    'due_date' => $invoice->due_date?->toDateString(),
                ] : null,
                'checkout_url' => $checkoutUrl,
                'payment_attempt' => $attemptData,
                'token' => $token,
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
