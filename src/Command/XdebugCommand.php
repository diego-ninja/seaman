<?php

declare(strict_types=1);

// ABOUTME: Toggles Xdebug on or off without container restart.
// ABOUTME: Executes xdebug-toggle script inside app container.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaman:xdebug',
    description: 'Toggle xdebug on application container',
    aliases: ['xdebug'],
)]
class XdebugCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('mode', InputArgument::REQUIRED, 'Mode: on or off');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = (string) getcwd();

        // Check if seaman.yaml exists
        if (!file_exists($projectRoot . '/seaman.yaml')) {
            $io->error('seaman.yaml not found. Run "seaman init" first.');
            return Command::FAILURE;
        }

        $modeArgument = $input->getArgument('mode');

        if (!is_string($modeArgument)) {
            $io->error('Invalid mode argument.');
            return Command::FAILURE;
        }
        $mode = $modeArgument;

        if (!in_array(strtolower($mode), ['on', 'off'], true)) {
            $io->error("Invalid mode: {$mode}. Use 'on' or 'off'.");
            return Command::FAILURE;
        }

        $manager = new DockerManager((string) getcwd());
        $result = $manager->executeInService('app', ['xdebug-toggle', strtolower($mode)]);

        if ($result->isSuccessful()) {
            $io->success("Xdebug is now {$mode}");
            return Command::SUCCESS;
        }

        $io->error('Failed to toggle Xdebug');
        $io->writeln($result->errorOutput);

        return Command::FAILURE;
    }
}
