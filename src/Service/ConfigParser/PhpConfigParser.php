<?php

declare(strict_types=1);

// ABOUTME: Parses PHP configuration section from YAML data.
// ABOUTME: Handles PHP version and Xdebug settings parsing.

namespace Seaman\Service\ConfigParser;

use Seaman\Enum\PhpVersion;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;

final readonly class PhpConfigParser
{
    use ConfigDataExtractor;

    /**
     * @param array<string, mixed> $data
     */
    public function parse(array $data): PhpConfig
    {
        $phpData = $this->requireArray($data, 'php', 'Invalid PHP configuration: expected array');
        $xdebug = $this->parseXdebug($phpData);

        $versionString = $phpData['version'] ?? null;
        $phpVersion = is_string($versionString) ? PhpVersion::tryFrom($versionString) : null;

        return new PhpConfig(
            version: $phpVersion ?? PhpVersion::Php84,
            xdebug: $xdebug,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function merge(array $data, PhpConfig $base): PhpConfig
    {
        $phpData = $this->getArray($data, 'php');

        /** @var array<string, mixed> $phpData */
        $xdebug = $this->mergeXdebug($phpData, $base->xdebug);

        $versionString = $phpData['version'] ?? null;
        $phpVersion = is_string($versionString) ? PhpVersion::tryFrom($versionString) : null;

        return new PhpConfig(
            version: $phpVersion ?? $base->version,
            xdebug: $xdebug,
        );
    }

    /**
     * @param array<string, mixed> $phpData
     */
    private function parseXdebug(array $phpData): XdebugConfig
    {
        $xdebugData = $this->requireArray($phpData, 'xdebug', 'Invalid xdebug configuration: expected array');

        return new XdebugConfig(
            enabled: $this->getBool($xdebugData, 'enabled', false),
            ideKey: $this->getString($xdebugData, 'ide_key', 'PHPSTORM'),
            clientHost: $this->getString($xdebugData, 'client_host', 'host.docker.internal'),
        );
    }

    /**
     * @param array<string, mixed> $phpData
     */
    private function mergeXdebug(array $phpData, XdebugConfig $base): XdebugConfig
    {
        $xdebugData = $this->getArray($phpData, 'xdebug');

        /** @var array<string, mixed> $xdebugData */
        return new XdebugConfig(
            enabled: $this->getBool($xdebugData, 'enabled', $base->enabled),
            ideKey: $this->getString($xdebugData, 'ide_key', $base->ideKey),
            clientHost: $this->getString($xdebugData, 'client_host', $base->clientHost),
        );
    }
}
