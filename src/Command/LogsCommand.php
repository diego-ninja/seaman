<?php

declare(strict_types=1);

// ABOUTME: Shows logs from Docker services.
// ABOUTME: Supports follow, tail, and since options.

namespace Seaman\Command;

use Seaman\Contracts\Decorable;
use Seaman\Service\DockerManager;
use Seaman\ValueObject\LogOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaman:logs',
    description: 'View service logs',
    aliases: ['logs'],
)]
class LogsCommand extends AbstractSeamanCommand implements Decorable
{
    protected function configure(): void
    {
        $this
            ->addArgument('service', InputArgument::REQUIRED, 'Service name')
            ->addOption('follow', 'f', InputOption::VALUE_NONE, 'Follow log output')
            ->addOption('tail', 't', InputOption::VALUE_REQUIRED, 'Number of lines to show from the end')
            ->addOption('since', 's', InputOption::VALUE_REQUIRED, 'Show logs since timestamp or relative');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = (string) getcwd();

        /** @var string $service */
        $service = $input->getArgument('service');
        /** @var ?string $tail */
        $tail = $input->getOption('tail');
        /** @var ?string $since */
        $since = $input->getOption('since');

        $options = new LogOptions(
            follow: (bool) $input->getOption('follow'),
            tail: $tail ? (int) $tail : null,
            since: $since,
        );

        $manager = new DockerManager($projectRoot);

        try {
            $result = $manager->logs($service, $options);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->write($result->output);

        return $result->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}
