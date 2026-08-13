<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\PlatformBindingsServiceProvider;
use App\Providers\SettingsServiceProvider;

return [
    DatabaseServiceProvider::class,
    SettingsServiceProvider::class,
    NotificationServiceProvider::class,
    PlatformBindingsServiceProvider::class,
    AppServiceProvider::class,
    AuthServiceProvider::class,
    FortifyServiceProvider::class,
];
