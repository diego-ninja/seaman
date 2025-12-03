<?php

use Seaman\Exception\BinaryNotFoundException;
use Seaman\UI\Widget\Box\Box;
use Symfony\Component\Process\Process;

if (!function_exists('Seaman\is_seaman')) {
    /**
     * Check if the current directory is a Cosmic project.
     *
     * @return bool
     */
    function is_cosmic(): bool
    {
        $check = sprintf("%s/vendor/seaman/seaman", getcwd());
        return file_exists($check);
    }
}

if (!function_exists('Seaman\is_phar')) {
    /**
     * Check if the application is running as a Phar.
     *
     * @return bool
     */
    function is_phar(): bool
    {
        if (is_cosmic()) {
            return false;
        }

        return Phar::running() !== '';
    }
}

if (!function_exists('Seaman\value')) {
    /**
     * Return the default value of the given value.
     *
     * @param mixed $value
     * @param mixed ...$args
     * @return mixed
     */
    function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('Seaman\is_git')) {
    /**
     * Check if the current directory is a Git repository.
     * If path is provided, check if the path is a Git repository.
     *
     * @param string|null $path
     * @return bool
     * @throws BinaryNotFoundException
     */
    function is_git(?string $path = null): bool
    {
        $command = sprintf("%s rev-parse --is-inside-work-tree", find_binary("git"));
        $process = Process::fromShellCommandline($command);
        if (!is_null($path)) {
            $process->setWorkingDirectory($path);
        }
        $process->run();

        return $process->isSuccessful();
    }
}

if (!function_exists('Seaman\find_binary')) {
    /**
     * Find a binary in the system.
     *
     * @param string $binary
     * @return string
     * @throws BinaryNotFoundException
     */
    function find_binary(string $binary): string
    {
        $command = sprintf("which %s", $binary);
        $process = Process::fromShellCommandline($command);
        $process->run();
        if ($process->isSuccessful()) {
            $binary_path = trim($process->getOutput());
            // Exclude Windows binaries (found in /mnt on WSL)
            if (!str_starts_with($binary_path, "/mnt")) {
                return $binary_path;
            }
        }

        throw BinaryNotFoundException::withBinary($binary);
    }
}

if (!function_exists("Seaman\git_version")) {
    /**
     * Get the current Git version for a given path
     *
     * @param string $path
     * @return string|null
     * @throws BinaryNotFoundException
     */
    function git_version(string $path): ?string
    {
        $command = sprintf("cd %s && %s describe --tags --abbrev=0", $path, find_binary("git"));
        $process = Process::fromShellCommandline($command);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput());
    }
}

if (!function_exists('Seaman\box')) {
    /**
     * Display text in a bordered box.
     *
     * @param string $title The title displayed in the top border
     * @param string $message The main content of the box
     * @param string $footer Optional footer content
     * @param string $color Border color (gray, cyan, yellow, red, green)
     * @param string $info Optional info text in the bottom border
     * @return void
     */
    function box(
        string $title,
        string $message,
        string $footer = '',
        string $color = 'gray',
        string $info = '',
    ): void {
        new Box($title, $message, $footer, $color, $info)->display();
    }
}
if (!function_exists('Seaman\base_path')) {
    function base_path(?string $dir = null): string
    {
        $base_path = is_phar() ? Phar::running() : (string) getcwd();
        return $dir ? sprintf("%s/%s", $base_path, $dir) : $base_path;
    }
}


