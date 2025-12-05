<?php

declare(strict_types=1);

// ABOUTME: Value object representing a detected DNS provider.
// ABOUTME: Contains provider type, config path, and sudo requirement.

namespace Seaman\ValueObject;

use Seaman\Enum\DnsProvider;

final readonly class DetectedDnsProvider
{
    public function __construct(
        public DnsProvider $provider,
        public string $configPath,
        public bool $requiresSudo,
    ) {}
}
