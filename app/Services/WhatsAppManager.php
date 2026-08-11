<?php

namespace App\Services;

use App\Events\WhatsApp\WhatsAppFailed;
use App\Events\WhatsApp\WhatsAppSent;
use App\Exceptions\InvalidWhatsAppDriverException;
use App\Exceptions\WhatsAppDeliveryException;
use App\Exceptions\WhatsAppDriverNotFoundException;
use App\Models\Setting;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\QueryException;
use OpenKOS\Core\Contracts\WhatsAppDriver;
use OpenKOS\Core\Data\WhatsApp\DriverHealthResult;
use OpenKOS\Core\Data\WhatsApp\WhatsAppContent;
use OpenKOS\Core\Data\WhatsApp\WhatsAppMessage;
use OpenKOS\Platform\Notification\NotificationRegistry;

class WhatsAppManager
{
    public function __construct(
        private NotificationRegistry $registry,
        private Container $container,
    ) {}

    public function driver(?string $name = null): WhatsAppDriver
    {
        return $this->resolveDriver($name)[1];
    }

    public function send(string $phone, string|WhatsAppContent $content): void
    {
        [$driverId, $driver] = $this->resolveDriver();

        $message = is_string($content) ? $content : $content->message;
        $mediaUrl = $content instanceof WhatsAppContent ? $content->mediaUrl : null;
        $attachment = $content instanceof WhatsAppContent ? $content->attachment : null;

        try {
            if ($attachment && ! $driver->supportsAttachments()) {
                throw new \RuntimeException("WhatsApp driver [{$driverId}] does not support document attachments.");
            }

            $driver->send(new WhatsAppMessage($phone, $message, attachment: $attachment));

            event(new WhatsAppSent($driverId, $phone, $mediaUrl));
        } catch (\Throwable $exception) {
            $deliveryException = WhatsAppDeliveryException::from($driverId, $exception);

            event(new WhatsAppFailed($driverId, $phone, $deliveryException));

            throw $deliveryException;
        }
    }

    public function health(): DriverHealthResult
    {
        return $this->driver()->health();
    }

    public function getPairingQrCode(): ?string
    {
        return $this->driver()->getPairingQrCode();
    }

    public function pair(): void
    {
        $this->driver()->pair();
    }

    public function disconnect(): void
    {
        $this->driver()->disconnect();
    }

    /**
     * @return array{0: string, 1: WhatsAppDriver}
     */
    private function resolveDriver(?string $name = null): array
    {
        $name ??= $this->resolveDefaultDriver();
        $normalizedId = $this->normalizeDriverId($name);

        $registration = $this->registry->driver('whatsapp', $normalizedId);

        if (! $registration) {
            throw WhatsAppDriverNotFoundException::for($normalizedId);
        }

        $credentials = $this->resolveCredentials($name, $registration->config);

        $driver = $this->container->make($registration->driverClass, [
            'config' => $credentials,
        ]);

        if (! $driver instanceof WhatsAppDriver) {
            throw InvalidWhatsAppDriverException::for($normalizedId, $registration->driverClass);
        }

        return [$normalizedId, $driver];
    }

    public function normalizeDriverId(string $name): string
    {
        return match ($name) {
            'log', 'openkos/log' => 'openkos/whatsapp-log',
            'fonnte' => 'openkos/fonnte',
            default => $name,
        };
    }

    private function resolveDefaultDriver(): string
    {
        try {
            return Setting::get('whatsapp_driver') ?? config('services.whatsapp.default', 'log');
        } catch (QueryException) {
            return config('services.whatsapp.default', 'log');
        }
    }

    private function resolveCredentials(string $name, array $defaults): array
    {
        $envDefaults = array_filter($defaults, fn ($value) => $value !== null);

        try {
            $dbConfig = Setting::get('whatsapp_config');
            $dbConfig = is_array($dbConfig) ? ($dbConfig[$name] ?? []) : [];
        } catch (QueryException) {
            $dbConfig = [];
        }

        return array_merge($dbConfig, $envDefaults);
    }
}
