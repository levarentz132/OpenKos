<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$orders = \App\Models\BookingOrder::latest('id')->take(10)->get();
echo "=== RECENT BOOKING ORDERS ===\n";
foreach ($orders as $o) {
    echo "ID: {$o->id} | Ref: {$o->reference} | Status: {$o->status} | Name: {$o->guest_name} | LeaseID: {$o->lease_id} | PaidAt: {$o->paid_at} | CreatedAt: {$o->created_at}\n";
    if ($o->notes) echo "   Notes: {$o->notes}\n";
}

$leases = \App\Models\Lease::latest('id')->take(5)->get();
echo "\n=== RECENT LEASES ===\n";
foreach ($leases as $l) {
    echo "ID: {$l->id} | Unit: {$l->unit_id} | Status: {$l->status->value} | CreatedAt: {$l->created_at}\n";
}

$attempts = \App\Models\PaymentAttempt::latest('id')->take(5)->get();
echo "\n=== RECENT PAYMENT ATTEMPTS ===\n";
foreach ($attempts as $a) {
    echo "ID: {$a->id} | Inv: {$a->invoice_id} | Ref: {$a->reference} | Status: " . ($a->status?->value ?? $a->status) . " | CreatedAt: {$a->created_at}\n";
}
 