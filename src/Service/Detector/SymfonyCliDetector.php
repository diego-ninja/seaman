<?php

declare(strict_types=1);

// ABOUTME: Detects if Symfony CLI is installed on the system.
// ABOUTME: Provides installation instructions for different platforms.

namespace Seaman\Service\Detector;

use Symfony\Component\Process\Process;

class SymfonyCliDetector
{
    /**
     * Check if Symfony CLI is installed and available in PATH.
     */
    public function isInstalled(): bool
    {
        $process = new Process(['which', 'symfony']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Get the installed Symfony CLI version.
     *
     * @return string|null Version string or null if not installed
     */
    public function getVersion(): ?string
    {
        if (!$this->isInstalled()) {
            return null;
        }

        $process = new Process(['symfony', 'version', '--no-ansi']);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $output = trim($process->getOutput());

        // Parse version from output like "Symfony CLI version 5.10.3 (c) 2023..."
        if (preg_match('/version\s+([\d.]+)/', $output, $matches)) {
            return $matches[1];
        }

        return $output;
    }

    /**
     * Get the installation command for the current platform.
     */
    public function getInstallCommand(): string
    {
        return 'curl -sS https://get.symfony.com/cli/installer | bash';
    }

    /**
     * Get platform-specific installation instructions.
     *
     * @return list<string>
     */
    public function getInstallInstructions(): array
    {
        $os = PHP_OS_FAMILY;

        $instructions = [
            'Install Symfony CLI using the official installer:',
            '',
            '  ' . $this->getInstallCommand(),
            '',
        ];

        if ($os === 'Darwin') {
            $instructions[] = 'Or using Homebrew:';
            $instructions[] = '';
            $instructions[] = '  brew install symfony-cli/tap/symfony-cli';
            $instructions[] = '';
        }

        $instructions[] = 'For more options, visit: https://symfony.com/download';

        return $instructions;
    }
}
