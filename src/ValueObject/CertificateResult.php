<?php

declare(strict_types=1);

// ABOUTME: Result of certificate generation operation.
// ABOUTME: Contains certificate type, file paths, and trust status.

namespace Seaman\ValueObject;

final readonly class CertificateResult
{
    public function __construct(
        public string $type,
        public string $certPath,
        public string $keyPath,
        public bool $trusted,
    ) {}
}
