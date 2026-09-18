<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$order = \App\Models\BookingOrder::find(3);
if ($order) {
    $order->update(['reference' => 'BK-ZFDJAZVIXK']);
    $fulfilled = app(\App\Actions\Bookings\FulfillBookingOrder::class)->execute($order, 'BK-ZFDJAZVIXK', now());
    echo "ORDER_FULFILLED: ID=" . $fulfilled->id . ", STATUS=" . $fulfilled->status . ", LEASE_ID=" . $fulfilled->lease_id . ", INVOICE_ID=" . $fulfilled->invoice_id . PHP_EOL;
} else {
    echo "ORDER_NOT_FOUND" . PHP_EOL;
}
