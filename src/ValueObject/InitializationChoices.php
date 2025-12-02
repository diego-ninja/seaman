<?php

declare(strict_types=1);

// ABOUTME: Value object containing user choices during initialization.
// ABOUTME: Used to pass configuration selections between services.

namespace Seaman\ValueObject;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;

final readonly class InitializationChoices
{
    /**
     * @param list<Service> $services
     */
    public function __construct(
        public PhpVersion $phpVersion,
        public Service $database,
        public array $services,
        public XdebugConfig $xdebug,
        public bool $generateDevContainer,
    ) {}
}
