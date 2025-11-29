<?php

namespace Seaman\Environment\Enum;

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

    public static function current(): self
    {

    }
}
