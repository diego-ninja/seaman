<?php

declare(strict_types=1);

// ABOUTME: Starts Docker services.
// ABOUTME: Executes docker-compose up for all or specific services.

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
    name: 'seaman:start',
    description: 'Start seaman stack services',
    aliases: ['start'],
)]
class StartCommand extends AbstractSeamanCommand implements Decorable
{
    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Specific service to start');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var ?string $service */
        $service = $input->getArgument('service');

        $projectRoot = (string) getcwd();
        $manager = new DockerManager($projectRoot);

        try {
            $result = $manager->start($service);
        } catch (\Exception $e) {
            Terminal::output()->writeln($e->getMessage());
            return Command::FAILURE;
        }

        if ($result->isSuccessful()) {
            return Command::SUCCESS;
        }

        Terminal::output()->writeln($result->errorOutput);
        return Command::FAILURE;
    }
}
