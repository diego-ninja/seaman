<?php

// ABOUTME: Bootstraps new Symfony projects using Symfony CLI.
// ABOUTME: Supports multiple project types with appropriate configurations.

declare(strict_types=1);

namespace Seaman\Service;

use Seaman\Enum\ProjectType;
use Seaman\Service\Detector\SymfonyCliDetector;
use Seaman\UI\Prompts;
use Seaman\UI\Terminal;
use Seaman\UI\Widget\Spinner\SpinnerFactory;
use Symfony\Component\Process\Process;

final readonly class SymfonyProjectBootstrapper
{
    public function __construct(
        private SymfonyCliDetector $cliDetector = new SymfonyCliDetector(),
    ) {}

    /**
     * Check if Symfony CLI is available for bootstrapping.
     */
    public function isCliAvailable(): bool
    {
        return $this->cliDetector->isInstalled();
    }

    /**
     * Ensure Symfony CLI is installed, offering to install if missing.
     *
     * @return bool True if CLI is available (installed or just installed), false if user declined
     */
    public function ensureCliInstalled(): bool
    {
        if ($this->cliDetector->isInstalled()) {
            return true;
        }

        Terminal::output()->writeln('');
        Terminal::output()->writeln('  <fg=yellow>⚠ Symfony CLI is required to create new projects</>');
        Terminal::output()->writeln('');

        $shouldInstall = Prompts::confirm(
            label: 'Symfony CLI not found. Would you like to install it now?',
            default: true,
        );

        if (!$shouldInstall) {
            Terminal::output()->writeln('');
            Terminal::output()->writeln('  <fg=cyan>Manual installation instructions:</>');
            foreach ($this->cliDetector->getInstallInstructions() as $instruction) {
                Terminal::output()->writeln('  ' . $instruction);
            }
            Terminal::output()->writeln('');
            return false;
        }

        return $this->installCli();
    }

    /**
     * Install Symfony CLI.
     */
    private function installCli(): bool
    {
        $installCommand = $this->cliDetector->getInstallCommand();

        $process = Process::fromShellCommandline($installCommand);
        $process->setTimeout(120);

        SpinnerFactory::for($process, 'Installing Symfony CLI');

        if (!$process->isSuccessful()) {
            Terminal::error('Failed to install Symfony CLI');
            Terminal::output()->writeln('');
            Terminal::output()->writeln('  <fg=gray>' . $process->getErrorOutput() . '</>');
            Terminal::output()->writeln('');
            Terminal::output()->writeln('  <fg=cyan>Try installing manually:</>');
            foreach ($this->cliDetector->getInstallInstructions() as $instruction) {
                Terminal::output()->writeln('  ' . $instruction);
            }
            return false;
        }

        // Add to PATH for the current session if installed to ~/.symfony5/bin
        $homeDir = getenv('HOME') ?: '';
        $symfonyBinPath = $homeDir . '/.symfony5/bin';
        if (is_dir($symfonyBinPath)) {
            $currentPath = getenv('PATH') ?: '';
            putenv("PATH={$symfonyBinPath}:{$currentPath}");
        }

        // Verify installation
        if (!$this->cliDetector->isInstalled()) {
            Terminal::output()->writeln('');
            Terminal::output()->writeln('  <fg=yellow>Symfony CLI was installed but may not be in your PATH.</>');
            Terminal::output()->writeln('  <fg=yellow>Please restart your terminal or add it to your PATH:</>');
            Terminal::output()->writeln('');
            Terminal::output()->writeln("  <fg=cyan>export PATH=\"\$HOME/.symfony5/bin:\$PATH\"</>");
            Terminal::output()->writeln('');
            return false;
        }

        $version = $this->cliDetector->getVersion();
        Terminal::success('Symfony CLI installed successfully' . ($version ? " (v{$version})" : ''));

        return true;
    }

    /**
     * Get bootstrap command for single-command project types.
     *
     * @param ProjectType $type Project type
     * @param string $name Project name
     * @param string $targetDirectory Target directory
     * @return string Command to execute
     */
    public function getBootstrapCommand(ProjectType $type, string $name, string $targetDirectory): string
    {
        return match ($type) {
            ProjectType::WebApplication => sprintf(
                'cd %s && symfony new %s --webapp',
                escapeshellarg($targetDirectory),
                escapeshellarg($name),
            ),
            ProjectType::Microservice, ProjectType::Skeleton => sprintf(
                'cd %s && symfony new %s --webapp=false',
                escapeshellarg($targetDirectory),
                escapeshellarg($name),
            ),
            ProjectType::ApiPlatform => throw new \InvalidArgumentException(
                'API Platform requires multiple commands. Use getBootstrapCommands() instead.',
            ),
            ProjectType::Existing => throw new \InvalidArgumentException(
                'Cannot bootstrap an existing project.',
            ),
        };
    }

    /**
     * Get bootstrap commands for multi-command project types.
     *
     * @param ProjectType $type Project type
     * @param string $name Project name
     * @param string $targetDirectory Target directory
     * @return list<string> Commands to execute in sequence
     */
    public function getBootstrapCommands(ProjectType $type, string $name, string $targetDirectory): array
    {
        if ($type !== ProjectType::ApiPlatform) {
            return [$this->getBootstrapCommand($type, $name, $targetDirectory)];
        }

        return [
            sprintf(
                'cd %s && symfony new %s --webapp',
                escapeshellarg($targetDirectory),
                escapeshellarg($name),
            ),
            sprintf(
                'cd %s/%s && composer require api',
                escapeshellarg($targetDirectory),
                escapeshellarg($name),
            ),
        ];
    }

    /**
     * Execute bootstrap commands.
     *
     * @param ProjectType $type Project type
     * @param string $name Project name
     * @param string $targetDirectory Target directory
     * @return bool Success status
     * @throws \Exception
     */
    public function bootstrap(ProjectType $type, string $name, string $targetDirectory): bool
    {
        $commands = $this->getBootstrapCommands($type, $name, $targetDirectory);

        foreach ($commands as $command) {
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(300); // 5 minutes

            SpinnerFactory::for($process, sprintf('Bootstrapping new Symfony %s project', $type->value));

            if (!$process->isSuccessful()) {
                return false;
            }
        }

        return true;
    }
}
