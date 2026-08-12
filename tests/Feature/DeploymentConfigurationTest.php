<?php

use App\Jobs\DeploymentSmokeJob;
use Illuminate\Support\Facades\Cache;

test('the application health route is available for container checks', function () {
    $this->get('/up')->assertSuccessful();
});

test('trusted proxies are disabled by default', function () {
    expect(config('app.trusted_proxies'))->toBe([]);
});

test('the deployment smoke job writes its cache marker', function () {
    $cacheKey = 'deployment-smoke-test';

    Cache::forget($cacheKey);
    (new DeploymentSmokeJob($cacheKey))->handle();

    expect(Cache::get($cacheKey))->toBeTrue();
});
