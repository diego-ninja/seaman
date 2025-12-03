<?php

declare(strict_types=1);

// ABOUTME: Restarts Docker services.
// ABOUTME: Stops and starts services in sequence.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'seaman:restart',
    description: 'Restart seaman stack services',
    aliases: ['restart'],
)]
class RestartCommand extends AbstractSeamanCommand implements Decorable
{
    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Specific service to restart');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var ?string $service */
        $service = $input->getArgument('service');
        $manager = new DockerManager((string) getcwd());
        $result = $manager->restart($service);

        if ($result->isSuccessful()) {
            return Command::SUCCESS;
        }

        Terminal::output()->writeln($result->errorOutput);
        return Command::FAILURE;
    }
}
