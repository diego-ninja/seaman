<?php

// ABOUTME: Value object for Symfony detection results.
// ABOUTME: Contains detection status and matched indicators count.

declare(strict_types=1);

namespace Seaman\ValueObject;

final readonly class SymfonyDetectionResult
{
    public function __construct(
        public bool $isSymfonyProject,
        public int $matchedIndicators,
    ) {}
}
