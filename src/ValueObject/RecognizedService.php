<?php

declare(strict_types=1);

// ABOUTME: Value object for a service recognized during docker-compose import.
// ABOUTME: Contains detected type information and original compose configuration.

namespace Seaman\ValueObject;

final readonly class RecognizedService
{
    /**
     * @param DetectedService $detected The detected service type and metadata
     * @param array<string, mixed> $config Original docker-compose service configuration
     */
    public function __construct(
        public DetectedService $detected,
        public array $config,
    ) {}
}
