<?php

test('the application health route is available for container checks', function () {
    $this->get('/up')->assertSuccessful();
});

test('trusted proxies are disabled by default', function () {
    expect(config('app.trusted_proxies'))->toBe([]);
});
