<?php

declare(strict_types=1);

// ABOUTME: Parses PHP configuration section from YAML data.
// ABOUTME: Handles PHP version and Xdebug settings parsing.

namespace Seaman\Service\ConfigParser;

use Seaman\Enum\PhpVersion;
use Seaman\Exception\InvalidConfigurationException;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;

final readonly class PhpConfigParser
{
    /**
     * @param array<string, mixed> $data
     */
    public function parse(array $data): PhpConfig
    {
        $phpData = $data['php'] ?? [];
        if (!is_array($phpData)) {
            throw new InvalidConfigurationException('Invalid PHP configuration: expected array');
        }

        /** @var array<string, mixed> $phpData */
        $xdebug = $this->parseXdebug($phpData);

        $versionString = $phpData['version'] ?? null;
        $phpVersion = is_string($versionString) ? PhpVersion::tryFrom($versionString) : null;
        $phpVersion = $phpVersion ?? PhpVersion::Php84;

        return new PhpConfig(
            version: $phpVersion,
            xdebug: $xdebug,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function merge(array $data, PhpConfig $base): PhpConfig
    {
        $phpData = $data['php'] ?? [];
        if (!is_array($phpData)) {
            $phpData = [];
        }

        /** @var array<string, mixed> $phpData */
        $xdebug = $this->mergeXdebug($phpData, $base->xdebug);

        $versionString = $phpData['version'] ?? null;
        $phpVersion = is_string($versionString) ? PhpVersion::tryFrom($versionString) : null;
        $phpVersion = $phpVersion ?? $base->version;

        return new PhpConfig(
            version: $phpVersion,
            xdebug: $xdebug,
        );
    }

    /**
     * @param array<string, mixed> $phpData
     */
    private function parseXdebug(array $phpData): XdebugConfig
    {
        $xdebugData = $phpData['xdebug'] ?? [];
        if (!is_array($xdebugData)) {
            throw new InvalidConfigurationException('Invalid xdebug configuration: expected array');
        }

        $enabled = $xdebugData['enabled'] ?? false;
        if (!is_bool($enabled)) {
            throw new InvalidConfigurationException('Xdebug enabled must be a boolean');
        }

        $ideKey = $xdebugData['ide_key'] ?? 'PHPSTORM';
        if (!is_string($ideKey)) {
            throw new InvalidConfigurationException('Xdebug IDE key must be a string');
        }

        $clientHost = $xdebugData['client_host'] ?? 'host.docker.internal';
        if (!is_string($clientHost)) {
            throw new InvalidConfigurationException('Xdebug client host must be a string');
        }

        return new XdebugConfig(
            enabled: $enabled,
            ideKey: $ideKey,
            clientHost: $clientHost,
        );
    }

    /**
     * @param array<string, mixed> $phpData
     */
    private function mergeXdebug(array $phpData, XdebugConfig $base): XdebugConfig
    {
        $xdebugData = $phpData['xdebug'] ?? [];
        if (!is_array($xdebugData)) {
            $xdebugData = [];
        }

        $enabled = $xdebugData['enabled'] ?? $base->enabled;
        if (!is_bool($enabled)) {
            $enabled = $base->enabled;
        }

        $ideKey = $xdebugData['ide_key'] ?? $base->ideKey;
        if (!is_string($ideKey)) {
            $ideKey = $base->ideKey;
        }

        $clientHost = $xdebugData['client_host'] ?? $base->clientHost;
        if (!is_string($clientHost)) {
            $clientHost = $base->clientHost;
        }

        return new XdebugConfig(
            enabled: $enabled,
            ideKey: $ideKey,
            clientHost: $clientHost,
        );
    }
}
