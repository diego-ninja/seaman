<?php

declare(strict_types=1);

// ABOUTME: Result of DNS configuration operation.
// ABOUTME: Contains configuration type, paths, and instructions for setup.

namespace Seaman\ValueObject;

final readonly class DnsConfigurationResult
{
    /**
     * @param list<string> $instructions Manual setup instructions
     */
    public function __construct(
        public string $type,
        public bool $automatic,
        public bool $requiresSudo,
        public ?string $configPath,
        public ?string $configContent,
        public array $instructions,
        public ?string $restartCommand = null,
    ) {}
}
