<?php

declare(strict_types=1);

// ABOUTME: Environment variable loader and accessor.
// ABOUTME: Wraps dotenv with support for PHAR execution and type casting.

namespace Seaman\Environment;

use Dotenv\Repository\Adapter\PutenvAdapter;
use Dotenv\Repository\RepositoryBuilder;
use Dotenv\Repository\RepositoryInterface;
use Seaman\Enum\Environment;
use Phar;
use PhpOption\Option;

use function is_phar;
use function value;

/**
 * Class Env
 *
 * Provides utility methods for interacting with environment variables.
 */
class Env
{
    /**
     * Whether to use putenv for setting environment variables.
     */
    protected static bool $putenv = true;

    /**
     * The Dotenv repository instance.
     */
    protected static ?RepositoryInterface $repository = null;

    /**
     * Enable the use of putenv for setting environment variables.
     */
    public static function enablePutenv(): void
    {
        static::$putenv     = true;
        static::$repository = null;
    }

    /**
     * Disable the use of putenv for setting environment variables.
     */
    public static function disablePutenv(): void
    {
        static::$putenv     = false;
        static::$repository = null;
    }

    /**
     * Get the Dotenv repository instance.
     */
    public static function getRepository(): RepositoryInterface
    {
        if (!static::$repository instanceof RepositoryInterface) {
            $builder = RepositoryBuilder::createWithDefaultAdapters();

            if (static::$putenv) {
                $builder = $builder->addAdapter(PutenvAdapter::class);
            }

            static::$repository = $builder->immutable()->make();
        }

        return static::$repository;
    }

    /**
     * Get the base path, optionally with a subdirectory appended.
     *
     * @param string|null $dir Subdirectory (optional)
     */
    public static function basePath(?string $dir = null): string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new \RuntimeException('Unable to determine current working directory');
        }

        if (is_phar()) {
            $basePath = Phar::running();
        } else {
            $basePathValue = self::get("BASE_PATH", $cwd);
            $basePath = is_string($basePathValue) ? $basePathValue : $cwd;
        }

        return $dir ? sprintf("%s/%s", $basePath, $dir) : $basePath;
    }

    /**
     * Get the build path, optionally with a subdirectory appended.
     *
     * @param string|null $dir Subdirectory (optional)
     */
    public static function buildPath(?string $dir = null): string
    {
        $default = self::basePath("build");

        if (is_phar()) {
            $buildPath = $default;
        } else {
            $buildPathValue = self::get("BUILD_PATH", $default);
            $buildPath = is_string($buildPathValue) ? $buildPathValue : $default;
        }

        return $dir ? sprintf("%s/%s", $buildPath, $dir) : $buildPath;
    }

    /**
     * Get the current environment.
     */
    public static function current(): Environment
    {
        $env = self::get('APP_ENV');
        $envString = is_string($env) ? $env : '';
        return Environment::tryFrom($envString) ?? Environment::default();
    }

    /**
     * Get the value for a given key from the environment, with a default value if not set.
     *
     * @param string $key     The key to retrieve
     * @param mixed  $default Default value if the key is not set
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        /** @psalm-suppress UndefinedFunction */
        return Option::fromValue(static::getRepository()->get($key))
            ->map(function ($value) {
                if ($value === null) {
                    return null;
                }

                switch (strtolower($value)) {
                    case 'true':
                    case '(true)':
                        return true;
                    case 'false':
                    case '(false)':
                        return false;
                    case 'empty':
                    case '(empty)':
                        return '';
                    case 'null':
                    case '(null)':
                        return null;
                }

                if (preg_match('/\A([\'"])(.*)\1\z/', $value, $matches)) {
                    return $matches[2];
                }

                return $value;
            })
            ->getOrCall(fn(): mixed => value($default));
    }
}
