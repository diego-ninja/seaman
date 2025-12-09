<?php

declare(strict_types=1);

// ABOUTME: Value object containing the result of importing a docker-compose file.
// ABOUTME: Holds recognized services (seaman-managed) and custom services (passthrough).

namespace Seaman\ValueObject;

final readonly class ImportResult
{
    /**
     * @param array<string, RecognizedService> $recognized Services recognized and manageable by seaman
     * @param CustomServiceCollection $custom Services not recognized, preserved as-is
     */
    public function __construct(
        public array $recognized,
        public CustomServiceCollection $custom,
    ) {}

    public function hasRecognizedServices(): bool
    {
        return count($this->recognized) > 0;
    }

    public function hasCustomServices(): bool
    {
        return !$this->custom->isEmpty();
    }

    /**
     * @return list<string>
     */
    public function recognizedNames(): array
    {
        return array_keys($this->recognized);
    }
}
