<?php

use App\Actions\Invoices\ApplyLateFees;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Unit;

function createOverdueInvoice(int $daysOverdue = 5, float $rentAmount = 1000000): Invoice
{
    $property = Property::factory()->create();
    $unit = Unit::factory()->create(['property_id' => $property->id]);
    $tenant = Tenant::factory()->create();
    $lease = Lease::factory()->create([
        'unit_id' => $unit->id,
        'primary_tenant_id' => $tenant->id,
    ]);

    $invoice = Invoice::create([
        'lease_id' => $lease->id,
        'period_start' => now()->subDays($daysOverdue + 30)->toDateString(),
        'period_end' => now()->subDays($daysOverdue + 1)->toDateString(),
        'due_date' => now()->subDays($daysOverdue)->toDateString(),
        'status' => InvoiceStatus::Pending,
        'total' => $rentAmount,
        'amount_paid' => 0,
    ]);

    InvoiceLineItem::create([
        'invoice_id' => $invoice->id,
        'type' => 'rent',
        'description' => 'Rent Payment',
        'amount' => $rentAmount,
    ]);

    return $invoice;
}

describe('ApplyLateFees action', function () {
    beforeEach(function () {
        Setting::set('late_fee_enabled', true);
        Setting::set('late_fee_type', 'flat');
        Setting::set('late_fee_amount', 50000);
        Setting::set('late_fee_grace_days', 3);
    });

    it('does not apply late fee if feature is disabled', function () {
        Setting::set('late_fee_enabled', false);
        $invoice = createOverdueInvoice(5);

        $action = new ApplyLateFees;
        $count = $action->execute();

        expect($count)->toBe(0);
        expect($invoice->fresh()->lineItems()->where('type', 'late_fee')->count())->toBe(0);
    });

    it('applies flat late fee when overdue past grace period', function () {
        $invoice = createOverdueInvoice(5, 1000000);

        $action = new ApplyLateFees;
        $count = $action->execute();

        expect($count)->toBe(1);

        $freshInvoice = $invoice->fresh();
        $lateFeeItem = $freshInvoice->lineItems()->where('type', 'late_fee')->first();

        expect($lateFeeItem)->not->toBeNull();
        expect((float) $lateFeeItem->amount)->toBe(50000.0);
        expect((float) $freshInvoice->total)->toBe(1050000.0);
    });

    it('is idempotent for flat late fee', function () {
        $invoice = createOverdueInvoice(5, 1000000);

        $action = new ApplyLateFees;
        $action->execute();
        $action->execute(); // second run

        $freshInvoice = $invoice->fresh();
        expect($freshInvoice->lineItems()->where('type', 'late_fee')->count())->toBe(1);
        expect((float) $freshInvoice->total)->toBe(1050000.0);
    });

    it('applies percentage late fee correctly', function () {
        Setting::set('late_fee_type', 'percentage');
        Setting::set('late_fee_amount', 5); // 5%

        $invoice = createOverdueInvoice(5, 2000000);

        $action = new ApplyLateFees;
        $action->execute();

        $freshInvoice = $invoice->fresh();
        $lateFeeItem = $freshInvoice->lineItems()->where('type', 'late_fee')->first();

        expect($lateFeeItem)->not->toBeNull();
        expect((float) $lateFeeItem->amount)->toBe(100000.0); // 5% of 2,000,000
        expect((float) $freshInvoice->total)->toBe(2100000.0);
    });

    it('does not apply late fee if within grace period', function () {
        $invoice = createOverdueInvoice(2); // 2 days overdue < 3 grace days

        $action = new ApplyLateFees;
        $count = $action->execute();

        expect($count)->toBe(0);
        expect($invoice->fresh()->lineItems()->where('type', 'late_fee')->count())->toBe(0);
    });

    it('applies daily flat fee for every overdue day past grace period', function () {
        Setting::set('late_fee_type', 'daily_flat');
        Setting::set('late_fee_amount', 20000);
        Setting::set('late_fee_grace_days', 3);

        // 6 days overdue -> 3 days past grace period (6 - 3 = 3 days)
        $invoice = createOverdueInvoice(6, 1000000);

        $action = new ApplyLateFees;
        $count = $action->execute();

        expect($count)->toBe(3); // 3 daily fee items created
        $freshInvoice = $invoice->fresh();
        expect($freshInvoice->lineItems()->where('type', 'late_fee')->count())->toBe(3);
        expect((float) $freshInvoice->total)->toBe(1060000.0); // 1,000,000 + (3 * 20,000)
    });
});
