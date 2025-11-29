<?php

declare(strict_types=1);

namespace Seaman\Enum;

enum PhpVersion: string
{
    case Php83 = '8.3';
    case Php84 = '8.4';
    case Php85 = '8.5';
    case Unsupported = 'unsupported';

    public static function isSupported(self $phpVersion): bool
    {
        return in_array($phpVersion, PhpVersion::supported(), true);
    }

    /**
     * @return list<self>
     */
    public static function supported(): array
    {
        return [
            self::Php83,
            self::Php84,
            self::Php85,
        ];
    }

}
