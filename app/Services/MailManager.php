<?php

namespace App\Services;

use App\Contracts\MailDriver;
use App\Data\Mail\DriverHealthResult;
use App\Data\Mail\MailMessage;
use App\Data\Mail\MailSendResult;
use App\Events\Mail\MailFailed;
use App\Events\Mail\MailSent;
use App\Exceptions\InvalidMailDriverException;
use App\Exceptions\MailDeliveryException;
use App\Exceptions\MailDriverNotFoundException;
use App\Models\Setting;
use Illuminate\Contracts\Container\Container;
use OpenKOS\Platform\Notification\NotificationRegistry;

class MailManager
{
    public function __construct(
        private NotificationRegistry $registry,
        private Container $container,
    ) {}

    public function driver(?string $name = null): MailDriver
    {
        return $this->resolveDriver($name)[1];
    }

    public function send(MailMessage $message): MailSendResult
    {
        [$driverId, $driver] = $this->resolveDriver();

        try {
            $result = $driver->send($message);

            event(new MailSent($driverId, $message->subject, $message, $result->externalId));

            return $result;
        } catch (\Throwable $exception) {
            $deliveryException = MailDeliveryException::from($driverId, $exception);

            event(new MailFailed($driverId, $message->subject, $message, $deliveryException));

            throw $deliveryException;
        }
    }

    public function health(): DriverHealthResult
    {
        return $this->driver()->health();
    }

    /**
     * @return array{0: string, 1: MailDriver}
     */
    private function resolveDriver(?string $name = null): array
    {
        $effectiveConfig = Setting::effectiveMailConfig();
        $name ??= $effectiveConfig['driver'] ?? 'openkos/log';

        $normalizedId = $this->normalizeDriverId($name);

        $registration = $this->registry->driver('mail', $normalizedId);

        if (! $registration) {
            throw MailDriverNotFoundException::for($normalizedId);
        }

        $driverConfig = $effectiveConfig['drivers'][$normalizedId] ?? [];

        if (! isset($driverConfig['from']) && isset($effectiveConfig['from'])) {
            $driverConfig['from'] = $effectiveConfig['from'];
        }

        $driver = $this->container->make($registration->driverClass, [
            'config' => $driverConfig,
        ]);

        if (! $driver instanceof MailDriver) {
            throw InvalidMailDriverException::for($normalizedId, $registration->driverClass);
        }

        return [$normalizedId, $driver];
    }

    private function normalizeDriverId(string $name): string
    {
        return match ($name) {
            'smtp' => 'openkos/smtp',
            'log' => 'openkos/log',
            default => $name,
        };
    }
}
