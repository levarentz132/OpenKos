<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$properties = App\Models\Property::all(['id', 'name', 'slug', 'address', 'address_url', 'phone', 'kecamatan']);
echo json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
