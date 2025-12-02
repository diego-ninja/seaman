<?php

declare(strict_types=1);

// ABOUTME: Destroys all Docker services and volumes.
// ABOUTME: Runs docker-compose down -v to remove everything.

namespace Seaman\Command;

use Seaman\Contracts\Decorable;
use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;

#[AsCommand(
    name: 'seaman:destroy',
    description: 'Destroy all services and volumes',
    aliases: ['destroy'],
)]
class DestroyCommand extends AbstractSeamanCommand implements Decorable
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!confirm('This will remove all containers, networks, and volumes. Are you sure?')) {
            Terminal::success('Cancelled');
            return Command::SUCCESS;
        }

        $manager = new DockerManager((string) getcwd());
        $result = $manager->destroy();

        if ($result->isSuccessful()) {
            return Command::SUCCESS;
        }

        Terminal::error('Failed to destroy services');
        Terminal::output()->writeln($result->errorOutput);

        return Command::FAILURE;
    }
}
