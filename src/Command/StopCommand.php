<?php

declare(strict_types=1);

// ABOUTME: Stops Docker services.
// ABOUTME: Executes docker-compose stop for all or specific services.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Plugin\LifecycleEventData;
use Seaman\Plugin\PluginLifecycleDispatcher;
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
class StopCommand extends ModeAwareCommand implements Decorable
{
    public function __construct(
        private readonly DockerManager $dockerManager,
        private readonly PluginLifecycleDispatcher $lifecycleDispatcher,
        private readonly string $projectRoot,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Specific service to stop');
    }

    public function supportsMode(\Seaman\Enum\OperatingMode $mode): bool
    {
        return true; // Works in all modes
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var ?string $service */
        $service = $input->getArgument('service');

        $this->lifecycleDispatcher->dispatch('before:stop', new LifecycleEventData(
            event: 'before:stop',
            projectRoot: $this->projectRoot,
            service: $service,
        ));

        $result = $this->dockerManager->stop($service);

        if ($result->isSuccessful()) {
            $this->lifecycleDispatcher->dispatch('after:stop', new LifecycleEventData(
                event: 'after:stop',
                projectRoot: $this->projectRoot,
                service: $service,
            ));

            return Command::SUCCESS;
        }

        Terminal::output()->writeln($result->errorOutput);
        return Command::FAILURE;
    }
}
