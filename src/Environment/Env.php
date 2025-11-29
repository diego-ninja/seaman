<?php

declare(strict_types=1);

namespace Ninja\Cosmic\Environment;

use Dotenv\Repository\Adapter\PutenvAdapter;
use Dotenv\Repository\RepositoryBuilder;
use Dotenv\Repository\RepositoryInterface;
use Ninja\Cosmic\Exception\BinaryNotFoundException;
use Ninja\Cosmic\Terminal\Terminal;
use Phar;
use PhpOption\Option;

use function Cosmic\is_phar;
use function Cosmic\mask;
use function Cosmic\value;
use function Cosmic\git_version;

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
        $base_path = is_phar() ? Phar::running() : self::get("BASE_PATH", getcwd());
        return $dir ? sprintf("%s/%s", $base_path, $dir) : $base_path;
    }

    /**
     * Get the build path, optionally with a subdirectory appended.
     *
     * @param string|null $dir Subdirectory (optional)
     */
    public static function buildPath(?string $dir = null): ?string
    {
        $default    = self::basePath("builds");
        $build_path = is_phar() ? $default : self::get("BUILD_PATH", $default);
        return $dir ? sprintf("%s/%s", $build_path, $dir) : $build_path;
    }

    /**
     * Get the help path for commands, optionally with a subdirectory appended.
     *
     * @param string|null $dir Subdirectory (optional)
     */
    public static function helpPath(?string $dir = null): ?string
    {
        $default   = self::basePath("docs/commands");
        $help_path = is_phar() ? $default : self::get("COMMAND_HELP_PATH", $default);
        return $dir ? sprintf("%s/%s", $help_path, $dir) : $help_path;
    }

    /**
     * Check if the application is in debug mode.
     */
    public static function isDebug(): bool
    {
        if (Terminal::input()?->hasOption("debug")) {
            return Terminal::input()->getOption("debug") === true ?
                true :
                self::get("APP_DEBUG", false);
        }

        return self::get("APP_DEBUG", false);
    }

    /**
     * Get the current environment.
     */
    public static function env(): string
    {
        if (Terminal::input()?->hasOption("env")) {
            return Terminal::input()->getOption("env");
        }

        return self::get("APP_ENV", ENV_LOCAL);
    }

    /**
     * Get the application version.
     *
     * @throws BinaryNotFoundException
     */
    public static function appVersion(): string
    {
        return git_version(self::basePath()) ?? self::get("APP_VERSION", "unreleased");
    }

    /**
     * Get the application name.
     */
    public static function appName(): string
    {
        return self::get("APP_NAME", "cosmic");
    }

    /**
     * Get the shell executable, optionally with an icon.
     *
     * @param string|null $icon Shell icon (optional)
     */
    public static function shell(?string $icon = null): string
    {
        if ($icon) {
            return sprintf("%s %s", $icon, basename((string)self::get("SHELL")));
        }

        return basename((string)self::get("SHELL"));
    }

    /**
     * Dump the environment variables along with some additional information.
     * @return array<string, array<string, string>>
     * @throws BinaryNotFoundException
     */
    public static function dump(): array
    {
        $ret = [];
        foreach (array_keys($_ENV) as $key) {
            $var["key"]   = $key;
            $var["value"] = self::get($key);
            $ret[$key]    = $var;
        }

        $ret["APP_NAME"]["value"] = self::appName();
        $ret["APP_KEY"]["value"]  = mask(self::get("APP_KEY", ""));

        $ret["BASE_PATH"]["key"]   = "BASE_PATH";
        $ret["BASE_PATH"]["value"] = self::basePath();

        $ret["BUILD_PATH"]["key"]   = "BUILD_PATH";
        $ret["BUILD_PATH"]["value"] = self::buildPath();

        $ret["COMMAND_HELP_PATH"]["key"]   = "COMMAND_HELP_PATH";
        $ret["COMMAND_HELP_PATH"]["value"] = self::helpPath();

        $ret["APP_VERSION"]["key"]   = "APP_VERSION";
        $ret["APP_VERSION"]["value"] = self::appVersion();

        if (self::get("SUDO_PASSWORD", "") !== "") {
            $ret["SUDO_PASSWORD"]["key"]   = "SUDO_PASSWORD";
            $ret["SUDO_PASSWORD"]["value"] = mask(self::get("SUDO_PASSWORD", ""), 10);
        }

        sort($ret);

        return $ret;
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
