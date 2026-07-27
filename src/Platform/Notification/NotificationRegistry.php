<?php

namespace OpenKOS\Platform\Notification;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

class NotificationRegistry implements Arrayable
{
    /** @var array<string, array<string, NotificationDriverRegistration>> */
    private array $drivers = [];

    /** @var array<string, NotificationDriverRegistration> */
    private array $flatDrivers = [];

    public function registerDriver(NotificationDriverRegistration $driver): static
    {
        $existing = $this->drivers[$driver->channel][$driver->name] ?? null;

        if ($existing && $existing->driverClass !== $driver->driverClass) {
            throw new InvalidArgumentException("Conflicting notification driver [{$driver->name}] already registered for channel [{$driver->channel}].");
        }

        $this->drivers[$driver->channel][$driver->name] = $driver;
        $this->flatDrivers[$driver->name] = $driver;

        return $this;
    }

    public function driver(string $channel, string $name): ?NotificationDriverRegistration
    {
        return $this->drivers[$channel][$name] ?? null;
    }

    /**
     * @return array<string, NotificationDriverRegistration>
     */
    public function drivers(): array
    {
        return $this->flatDrivers;
    }

    /**
     * @return array<int, NotificationDriverRegistration>
     */
    public function forChannel(string $channel): array
    {
        return array_values($this->drivers[$channel] ?? []);
    }

    public function get(string $name): ?NotificationDriverRegistration
    {
        return $this->flatDrivers[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->flatDrivers[$name]);
    }

    public function toArray(): array
    {
        return array_values(array_map(
            fn (NotificationDriverRegistration $d) => $d->toArray(),
            $this->flatDrivers,
        ));
    }
}
