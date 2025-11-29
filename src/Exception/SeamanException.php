<?php

declare(strict_types=1);

namespace Seaman\Exception;

use Exception;
use Throwable;

/** @phpstan-consistent-constructor */
class SeamanException extends Exception
{
    public static function fromException(Throwable $ex): self
    {
        return new self(
            message: $ex->getMessage(),
            code: $ex->getCode(),
            previous: $ex
        );
    }

}
