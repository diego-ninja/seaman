<?php

// ABOUTME: Base class for commands that require mode checking.
// ABOUTME: Prevents unsupported commands from running in wrong modes.

declare(strict_types=1);

namespace Seaman\Command;

use Seaman\Contract\ModeAwareInterface;
use Seaman\Enum\OperatingMode;
use Seaman\Exception\UnsupportedModeException;
use Seaman\Service\ModeDetector;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ModeAwareCommand extends AbstractSeamanCommand implements ModeAwareInterface
{
    private ?ModeDetector $modeDetector = null;

    public function setModeDetector(ModeDetector $modeDetector): void
    {
        $this->modeDetector = $modeDetector;
    }

    /**
     * Determines if this command supports the given operating mode.
     *
     * Override this method in subclasses to specify mode requirements.
     * Default: supports all modes
     */
    public function supportsMode(OperatingMode $mode): bool
    {
        return true;
    }

    /**
     * Executes before the command runs to check mode support.
     *
     * @throws UnsupportedModeException
     */
    final protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        if ($this->modeDetector === null) {
            // If no mode detector is set, skip mode checking
            // This can happen in tests or when command is run directly
            return;
        }

        $currentMode = $this->modeDetector->detect();

        if (!$this->supportsMode($currentMode)) {
            $this->showUpgradeMessage($currentMode);
            throw UnsupportedModeException::forCommand($this->getName() ?? 'unknown', $currentMode);
        }
    }

    /**
     * Shows a helpful message when a command is not supported in current mode.
     */
    protected function showUpgradeMessage(OperatingMode $mode): void
    {
        Terminal::output()->writeln(sprintf(
            '<fg=yellow>⚠</> <comment>This command is not available in %s mode.</comment>',
            $mode->label(),
        ));

        if ($mode === OperatingMode::Unmanaged) {
            Terminal::output()->writeln('');
            Terminal::output()->writeln('Run <info>seaman init</info> to unlock full features:');
            Terminal::output()->writeln('  • Service management (add/remove services)');
            Terminal::output()->writeln('  • Xdebug control');
            Terminal::output()->writeln('  • DevContainer generation');
            Terminal::output()->writeln('  • Advanced database tools');
            Terminal::output()->writeln('  • Traefik reverse proxy with HTTPS');
            Terminal::output()->writeln('  • Automatic service routing');
            Terminal::output()->writeln('');
            Terminal::output()->writeln('Or use <info>seaman init --import</info> to import your existing docker-compose.yaml');
        } elseif ($mode === OperatingMode::Uninitialized) {
            Terminal::output()->writeln('');
            Terminal::output()->writeln('Run <info>seaman init</info> to initialize your project.');
        }
    }
}
