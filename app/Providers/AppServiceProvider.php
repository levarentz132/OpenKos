<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthEvents();
        $this->configureRateLimiting();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAuthEvents(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            $event->user->forceFill(['last_login_at' => now()])->save();
        });
    }

    protected function configureRateLimiting(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('api-public', function (\Illuminate\Http\Request $request) {
            $configuredSecret = config('services.openkos_api.secret', env('OPENKOS_API_SECRET', env('API_SECRET')));
            $providedSecret = $request->header('X-API-Key')
                ?? $request->header('X-OpenKos-Secret')
                ?? $request->query('api_key')
                ?? $request->bearerToken();

            // Internal callers like n8n or authorized microservices bypass rate limits
            if ($configuredSecret && $providedSecret && hash_equals((string) $configuredSecret, (string) $providedSecret)) {
                return \Illuminate\Cache\RateLimiting\Limit::none();
            }

            // Public visitors / anonymous bots limited to 60 req/min per IP
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->ip());
        });
    }
}
