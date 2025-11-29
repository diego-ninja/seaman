<?php

namespace Seaman\Enum;

enum PhpVersion: string
{
    case Php82 = '8.2';
    case Php83 = '8.3';
    case Php84 = '8.4';
    case Php85 = '8.5';
    case Unsupported = 'unsupported';

    public static function isSupported(self $phpVersion): bool
    {
        return $phpVersion === self::Php84;
    }

    /**
     * @return list<self>
     */
    public static function supported(): array
    {
        return [
            self::Php84,
            self::Php85,
        ];
    }

}
