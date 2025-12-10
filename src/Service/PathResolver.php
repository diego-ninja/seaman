<?php

declare(strict_types=1);

// ABOUTME: Resolves paths to Seaman resources.
// ABOUTME: Works correctly both when running from source and as a phar.

namespace Seaman\Service;

use Phar;

final class PathResolver
{
    /**
     * Get the path to Seaman's installation directory.
     * Works both when running from source and as a phar.
     *
     * @param string|null $path Optional path to append
     * @return string
     */
    public static function seamanPath(?string $path = null): string
    {
        if (self::isPhar()) {
            $base = Phar::running();
        } else {
            // When running from source, __DIR__ is src/Service, go up to root
            $base = dirname(__DIR__, 2);
        }

        return $path !== null ? $base . '/' . ltrim($path, '/') : $base;
    }

    /**
     * Check if running as a phar.
     */
    private static function isPhar(): bool
    {
        return Phar::running() !== '';
    }
}
