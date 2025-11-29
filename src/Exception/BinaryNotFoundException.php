<?php

declare(strict_types=1);

namespace Ninja\Cosmic\Exception;

class BinaryNotFoundException extends CosmicException
{
    public static function withBinary(string $binary): self
    {
        return new self(sprintf('%s binary not found. Please install it before continue.', $binary));
    }
}
