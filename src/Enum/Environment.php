<?php

declare(strict_types=1);

namespace Seaman\Enum;

enum Environment: string
{
    case Local = 'local';
    case Staging = 'staging';
    case Production = 'production';
    case Testing = 'testing';

    public static function default(): self
    {
        return self::Local;
    }
}
