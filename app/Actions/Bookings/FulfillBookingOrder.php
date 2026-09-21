<?php

namespace App\Actions\Bookings;

use App\Actions\Invoices\AllocatePayment;
use App\Actions\Leases\CreateLease;
use App\Business\Leases\OccupancyCalculator;
use App\Data\Lease\CreateLeaseData;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus as ApplicationPaymentStatus;
use App\Enums\UnitStatus;
use App\Events\Payment\PaymentRecorded;
use App\Models\BookingOrder;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenKOS\Core\Enums\PaymentStatus;

class FulfillBookingOrder
{
    public function __construct(
        private CreateLease $createLease,
        private AllocatePayment $allocatePayment,
        private OccupancyCalculator $occupancy,
    ) {}

    /**
     * Fulfill a paid BookingOrder:
     * 1. Checks unit availability & capacity (guards against double-booking race condition).
     * 2. Resolves/creates Tenant & User.
     * 3. Executes CreateLease (occupies Unit, creates Lease, generates Invoice).
     * 4. Creates PaymentAttempt & confirmed Payment, settling the Invoice.
     * 5. Updates BookingOrder with lease_id, invoice_id, and status = 'paid'.
     * 6. Automatically cancels any other competing pending orders for this unit.
     */
    public function execute(
        BookingOrder $bookingOrder,
        ?string $providerReference = null,
        ?DateTimeInterface $occurredAt = null,
    ): BookingOrder {
        if ($bookingOrder->isPaid()) {
            return $bookingOrder;
        }

        return DB::transaction(function () use ($bookingOrder, $providerReference, $occurredAt) {
            $lockedOrder = BookingOrder::lockForUpdate()->findOrFail($bookingOrder->id);

            if ($lockedOrder->isPaid()) {
                return $lockedOrder;
            }

            $unit = Unit::lockForUpdate()->findOrFail($lockedOrder->unit_id);

            // Double Booking / Race Condition Guard:
            // Check if unit is still available and can accommodate the new lease
            $canAccommodate = in_array($unit->status, [UnitStatus::Available, UnitStatus::Occupied], true)
                && $this->occupancy->canAccommodate($unit, 1);

            if (! $canAccommodate || in_array($unit->status, [UnitStatus::Maintenance, UnitStatus::Unavailable], true)) {
                // Overbooking race condition detected!
                // Another user completed payment and occupied the unit just moments ago.
                $conflictNote = "OVERBOOKING DETECTED: Pembayaran berhasil via DOKU ({$providerReference}), namun kamar {$unit->name} telah terisi penuh oleh penyewa lain. Memerlukan tindakan admin (relokasi kamar / refund).";

                $lockedOrder->update([
                    'status' => BookingOrder::STATUS_PAYMENT_CONFLICT,
                    'paid_at' => $occurredAt ?? now(),
                    'notes' => ($lockedOrder->notes ? $lockedOrder->notes . ' | ' : '') . $conflictNote,
                ]);

                Log::critical('DOUBLE BOOKING PAYMENT CONFLICT DETECTED!', [
                    'booking_order_id' => $lockedOrder->id,
                    'reference' => $lockedOrder->reference,
                    'unit_id' => $unit->id,
                    'unit_name' => $unit->name,
                    'amount' => $lockedOrder->amount,
                    'guest_name' => $lockedOrder->guest_name,
                    'guest_phone' => $lockedOrder->guest_phone,
                    'provider_reference' => $providerReference,
                ]);

                return $lockedOrder->fresh();
            }

            // 1. Resolve or create Tenant and User
            $phone = $lockedOrder->guest_phone;
            $email = $lockedOrder->guest_email;

            $tenant = Tenant::where('phone', $phone)
                ->when(! empty($email), fn ($q) => $q->orWhere('email', $email))
                ->first();

            if (! $tenant) {
                $user = User::where('phone', $phone)
                    ->when(! empty($email), fn ($q) => $q->orWhere('email', $email))
                    ->first();

                $userEmail = ! empty($email) ? $email : ($phone . '@openkos.local');

                if (! $user) {
                    $user = User::create([
                        'name' => $lockedOrder->guest_name,
                        'phone' => $phone,
                        'email' => $userEmail,
                        'password' => Hash::make(Str::random(16)),
                    ]);
                }

                $tenant = Tenant::create([
                    'user_id' => $user->id,
                    'name' => $lockedOrder->guest_name,
                    'phone' => $phone,
                    'email' => $userEmail,
                    'password' => Hash::make(Str::random(16)),
                    'is_active' => true,
                ]);
            }

            // 2. Prepare lease data (Monthly Rent + Security Deposit)
            $startDate = $lockedOrder->start_date;
            $endDate = $lockedOrder->end_date?->toDateString();
            $settledDateObj = $occurredAt ? (is_string($occurredAt) ? new \DateTimeImmutable($occurredAt) : $occurredAt) : now();
            $settledDateStr = $settledDateObj instanceof \DateTimeInterface ? $settledDateObj->format('Y-m-d H:i:s') : (string) $settledDateObj;

            $depositAmount = (float) ($lockedOrder->deposit_amount ?? $unit->property?->deposit_amount ?? 500000);
            $rentAmount = (float) ($lockedOrder->rent_amount ?? (($lockedOrder->amount - $depositAmount) / max(1, $lockedOrder->duration_months)));
            if ($rentAmount <= 0) {
                $rentAmount = (float) $lockedOrder->amount;
                $depositAmount = 0;
            }

            $leaseData = new CreateLeaseData(
                tenantIds: [$tenant->id],
                startDate: $startDate->toDateString(),
                endDate: $endDate,
                rentAmount: $rentAmount,
                billingInterval: 1,
                billingUnit: 'month',
                billingStrategy: 'advance',
                unitRateId: null,
                depositAmount: $depositAmount,
                depositPaidAt: $depositAmount > 0 ? $settledDateStr : null,
                depositRefundAmount: null,
                depositRefundedAt: null,
                rentDueDay: (int) $startDate->format('j'),
                notes: $lockedOrder->notes ?? "Online booking {$lockedOrder->reference}",
            );

            // 3. Create Lease (occupies Unit and calls GenerateInvoices)
            $lease = $this->createLease->execute($unit, $leaseData);

            // 4. Retrieve generated initial Invoice (earliest pending billing period)
            $invoice = $lease->invoices()
                ->where('status', InvoiceStatus::Pending->value)
                ->orderBy('due_date', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            if ($invoice) {
                // Create PaymentAttempt record
                $attempt = $invoice->paymentAttempts()->create([
                    'gateway_key' => 'doku',
                    'reference' => $lockedOrder->reference,
                    'provider_reference' => $providerReference ?? $lockedOrder->reference,
                    'amount' => $lockedOrder->amount,
                    'currency' => $lockedOrder->currency,
                    'status' => PaymentStatus::Settled,
                    'initiated_at' => $lockedOrder->created_at ?? now(),
                    'settled_at' => $settledDateObj,
                    'metadata' => [
                        'booking_order_id' => $lockedOrder->id,
                        'booking_reference' => $lockedOrder->reference,
                    ],
                ]);

                // Create confirmed Payment record
                $payment = $invoice->payments()->create([
                    'amount' => $lockedOrder->amount,
                    'payment_date' => $settledDateObj->format('Y-m-d'),
                    'payment_method' => PaymentMethod::Gateway->value,
                    'reference_number' => $lockedOrder->reference,
                    'status' => ApplicationPaymentStatus::Confirmed,
                    'verified_at' => now(),
                ]);

                $attempt->update(['payment_id' => $payment->id]);

                // Allocate payment to invoice (marks invoice as Paid)
                $this->allocatePayment->execute($payment);

                PaymentRecorded::dispatch($payment);
            }

            // 5. Mark BookingOrder as paid
            $lockedOrder->update([
                'status' => BookingOrder::STATUS_PAID,
                'tenant_id' => $tenant->id,
                'lease_id' => $lease->id,
                'invoice_id' => $invoice?->id,
                'paid_at' => $occurredAt ?? now(),
            ]);

            // 6. Automatically cancel any competing pending booking orders for this unit
            $competingOrders = BookingOrder::where('unit_id', $unit->id)
                ->where('id', '!=', $lockedOrder->id)
                ->where('status', BookingOrder::STATUS_PENDING)
                ->get();

            foreach ($competingOrders as $competingOrder) {
                $competingOrder->update([
                    'status' => BookingOrder::STATUS_CANCELLED,
                    'notes' => ($competingOrder->notes ? $competingOrder->notes . ' | ' : '')
                        . "Dibatalkan otomatis: Kamar telah dibayar oleh pengguna lain (Order {$lockedOrder->reference}).",
                ]);
            }

            Log::info('Booking order fulfilled with active lease and confirmed payment. Competing orders cancelled.', [
                'booking_order_id' => $lockedOrder->id,
                'reference' => $lockedOrder->reference,
                'lease_id' => $lease->id,
                'invoice_id' => $invoice?->id,
                'tenant_id' => $tenant->id,
                'cancelled_competing_orders_count' => $competingOrders->count(),
            ]);

            return $lockedOrder->fresh();
        });
    }
}
