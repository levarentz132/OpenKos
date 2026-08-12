<?php

use App\Jobs\DeploymentSmokeJob;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Cache;

test('the application health route is available for container checks', function () {
    $this->get('/up')->assertSuccessful();
});

test('trusted proxy middleware is registered', function () {
    expect(app(Kernel::class)->getGlobalMiddleware())
        ->toContain(TrustProxies::class);
});

test('the deployment smoke job writes its cache marker', function () {
    $cacheKey = 'deployment-smoke-test';

    Cache::forget($cacheKey);
    (new DeploymentSmokeJob($cacheKey))->handle();

    expect(Cache::get($cacheKey))->toBeTrue();
});
