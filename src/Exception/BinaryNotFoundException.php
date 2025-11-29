<?php

declare(strict_types=1);

namespace Seaman\Exception;

class BinaryNotFoundException extends SeamanException
{
    public static function withBinary(string $binary): self
    {
        return new self(sprintf('%s binary not found. Please install it before continue.', $binary));
    }
}
