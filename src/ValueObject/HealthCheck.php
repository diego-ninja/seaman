<?php

declare(strict_types=1);

// ABOUTME: Docker health check configuration.
// ABOUTME: Defines container health check parameters.

namespace Seaman\ValueObject;

readonly class HealthCheck
{
    /**
     * @param list<string> $test
     */
    public function __construct(
        public array $test,
        public string $interval,
        public string $timeout,
        public int $retries,
    ) {}
}
