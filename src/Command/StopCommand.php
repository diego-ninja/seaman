<?php

declare(strict_types=1);

// ABOUTME: Stops Docker services.
// ABOUTME: Executes docker-compose stop for all or specific services.

namespace Seaman\Command;

use Seaman\Contracts\Decorable;
use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'seaman:stop',
    description: 'Stop seaman stack services',
    aliases: ['stop'],
)]
class StopCommand extends AbstractSeamanCommand implements Decorable
{
    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Specific service to stop');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var ?string $service */
        $service = $input->getArgument('service');
        $manager = new DockerManager((string) getcwd());
        $result = $manager->stop($service);

        if ($result->isSuccessful()) {
            return Command::SUCCESS;
        }

        Terminal::output()->writeln($result->errorOutput);
        return Command::FAILURE;
    }
}
