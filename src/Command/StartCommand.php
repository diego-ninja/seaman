<?php

declare(strict_types=1);

// ABOUTME: Starts Docker services.
// ABOUTME: Executes docker-compose up for all or specific services.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Exception\PortAllocationException;
use Seaman\Service\ConfigManager;
use Seaman\Service\DockerManager;
use Seaman\Service\PortAllocator;
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
class StartCommand extends ModeAwareCommand implements Decorable
{
    public function __construct(
        private readonly PortAllocator $portAllocator,
        private readonly ConfigManager $configManager,
        private readonly DockerManager $dockerManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Specific service to start');
        $this->setHelp('Works with any docker-compose.yml file. Run "seaman init" for full features.');
    }

    public function supportsMode(\Seaman\Enum\OperatingMode $mode): bool
    {
        return true; // Works in all modes
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var ?string $service */
        $service = $input->getArgument('service');

        // Allocate ports (prompts user if conflicts found)
        try {
            $this->allocatePorts();
        } catch (PortAllocationException $e) {
            Terminal::error($e->getMessage());
            return Command::FAILURE;
        }

        try {
            $result = $this->dockerManager->start($service);
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

    /**
     * Allocate ports and regenerate .env if alternatives are used.
     *
     * @throws PortAllocationException
     */
    private function allocatePorts(): void
    {
        // Only allocate ports if seaman.yaml exists (managed mode)
        $projectRoot = (string) getcwd();
        if (!file_exists($projectRoot . '/.seaman/seaman.yaml')) {
            return;
        }

        // Skip port allocation if stack is already running
        if ($this->isStackRunning()) {
            return;
        }

        try {
            $config = $this->configManager->load();
        } catch (\Exception) {
            // If we can't load config, skip port allocation
            return;
        }

        $allocation = $this->portAllocator->allocate($config);

        // If any port was assigned differently, regenerate .env
        if ($allocation->hasAlternatives()) {
            $this->configManager->generateEnvWithAllocation($config, $allocation);
        }
    }

    /**
     * Check if any containers from this stack are already running.
     */
    private function isStackRunning(): bool
    {
        try {
            $statuses = $this->dockerManager->status();
            return count($statuses) > 0;
        } catch (\Exception) {
            return false;
        }
    }
}
