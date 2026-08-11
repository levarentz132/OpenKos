<?php

namespace App\Services\Platform;

use Composer\InstalledVersions;
use InvalidArgumentException;
use OpenKOS\Core\Contracts\PluginDiscovery;
use OpenKOS\Platform\Plugin\Plugin;

final class ComposerPluginDiscovery implements PluginDiscovery
{
    /**
     * @return array<int, class-string<Plugin>>
     */
    public function discover(): array
    {
        $disabledPackages = config('platform.discovery.disabled_packages', []);
        $packages = InstalledVersions::getInstalledPackages();

        sort($packages, SORT_STRING);

        $plugins = [];

        foreach ($packages as $package) {
            if (in_array($package, $disabledPackages, true)) {
                continue;
            }

            $installPath = InstalledVersions::getInstallPath($package);

            if (! is_string($installPath)) {
                continue;
            }

            $composerFile = $installPath.'/composer.json';

            if (! is_file($composerFile)) {
                continue;
            }

            $metadata = $this->readMetadata($package, $composerFile);
            $openkos = $metadata['extra']['openkos'] ?? null;

            if (! is_array($openkos) || ! array_key_exists('plugin', $openkos)) {
                continue;
            }

            $plugin = $openkos['plugin'];

            if (! is_string($plugin) || trim($plugin) === '') {
                throw new InvalidArgumentException(
                    "Package [{$package}] must declare a non-empty string in extra.openkos.plugin.",
                );
            }

            $plugins[] = trim($plugin);
        }

        return array_values(array_unique($plugins));
    }

    /**
     * @return array<string, mixed>
     */
    private function readMetadata(string $package, string $composerFile): array
    {
        try {
            $metadata = json_decode(
                file_get_contents($composerFile),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException(
                "Could not read Composer metadata for package [{$package}].",
                previous: $exception,
            );
        }

        if (! is_array($metadata)) {
            throw new InvalidArgumentException(
                "Composer metadata for package [{$package}] must be an object.",
            );
        }

        return $metadata;
    }
}
