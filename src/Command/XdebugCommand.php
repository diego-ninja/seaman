<?php

declare(strict_types=1);

// ABOUTME: Toggles Xdebug on or off without container restart.
// ABOUTME: Executes xdebug-toggle script inside app container.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaman:xdebug',
    description: 'Toggle xdebug on application container (requires init)',
    aliases: ['xdebug'],
)]
class XdebugCommand extends ModeAwareCommand implements Decorable
{
    public function __construct(
        private readonly DockerManager $dockerManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('mode', InputArgument::REQUIRED, 'Mode: on or off');
    }

    public function supportsMode(\Seaman\Enum\OperatingMode $mode): bool
    {
        return $mode === \Seaman\Enum\OperatingMode::Managed;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mode = $input->getArgument('mode');
        if (!is_string($mode)) {
            Terminal::error(sprintf('Argument "mode" must be a string. Received: %s', get_debug_type($mode)));
            return Command::FAILURE;
        }

        if (!in_array(strtolower($mode), ['on', 'off'], true)) {
            Terminal::error(sprintf('Argument "%s" must be one of "on", "off".', $mode));
            return Command::FAILURE;
        }

        $result = $this->dockerManager->executeInService('app', ['xdebug-toggle', strtolower($mode)]);

        if ($result->isSuccessful()) {
            Terminal::success("Xdebug is now <fg=bright-green>{$mode}</>");
            return Command::SUCCESS;
        }

        Terminal::error('Failed to toggle Xdebug');
        Terminal::output()->writeln($result->errorOutput);

        return Command::FAILURE;
    }
}
