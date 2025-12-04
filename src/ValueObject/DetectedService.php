<?php

declare(strict_types=1);

// ABOUTME: Value object representing a detected service from docker-compose.
// ABOUTME: Contains service type, version, and detection confidence level.

namespace Seaman\ValueObject;

use Seaman\Enum\Confidence;
use Seaman\Enum\Service;

final readonly class DetectedService
{
    public function __construct(
        public Service $type,
        public string $version = 'latest',
        public Confidence $confidence = Confidence::High,
    ) {}

    public function isHighConfidence(): bool
    {
        return $this->confidence === Confidence::High;
    }

    public function isMediumConfidence(): bool
    {
        return $this->confidence === Confidence::Medium;
    }

    public function isLowConfidence(): bool
    {
        return $this->confidence === Confidence::Low;
    }
}
