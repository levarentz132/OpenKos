<?php

namespace App\Composer;

use App\Models\PaymentAttempt;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use OpenKOS\Core\Enums\PaymentStatus;
use RuntimeException;
use Throwable;

final class PaymentGatewayRemovalGuard
{
    public static function beforePackageUninstall(object $event): void
    {
        $package = $event->getOperation()->getPackage();
        $packageName = $package->getName();
        $extra = $package->getExtra();
        $gatewayKeys = $extra['openkos']['capabilities']['payment_gateways'] ?? [];

        if (! is_array($gatewayKeys)) {
            return;
        }

        $gatewayKeys = array_values(array_filter(
            $gatewayKeys,
            static fn (mixed $key): bool => is_string($key) && trim($key) !== '',
        ));

        if ($gatewayKeys === []) {
            return;
        }

        if (! Container::getInstance()->bound('db')) {
            $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
            $app->make(Kernel::class)->bootstrap();
        }

        self::assertNoActiveAttempts($gatewayKeys, $packageName);
    }

    /** @param array<int, string> $gatewayKeys */
    private static function assertNoActiveAttempts(array $gatewayKeys, string $packageName): void
    {
        try {
            $activeAttempts = PaymentAttempt::query()
                ->whereIn('gateway_key', $gatewayKeys)
                ->where('status', PaymentStatus::Pending->value)
                ->where(function ($query): void {
                    $query
                        ->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->count();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                "Cannot verify active payment attempts. Uninstalling {$packageName} was aborted.",
                previous: $exception,
            );
        }

        if ($activeAttempts > 0) {
            throw new RuntimeException(
                "Cannot uninstall {$packageName} while {$activeAttempts} active payment attempt(s) remain.",
            );
        }
    }
}
