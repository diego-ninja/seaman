<?php

declare(strict_types=1);

// ABOUTME: Starts Docker services.
// ABOUTME: Executes docker-compose up for all or specific services.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Exception\PortConflictException;
use Seaman\Service\ConfigManager;
use Seaman\Service\DockerManager;
use Seaman\Service\PortChecker;
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
        private readonly PortChecker $portChecker,
        private readonly ConfigManager $configManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Specific service to start');
        $this->setHelp('Works with any docker-compose.yml file. Run "seaman init" for full features.');
    }

    protected function supportsMode(\Seaman\Enum\OperatingMode $mode): bool
    {
        return true; // Works in all modes
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var ?string $service */
        $service = $input->getArgument('service');

        $projectRoot = (string) getcwd();
        $manager = new DockerManager($projectRoot);

        // Check for port conflicts before starting
        try {
            $this->checkPortConflicts($projectRoot);
        } catch (PortConflictException $e) {
            Terminal::error($e->getMessage());
            Terminal::output()->writeln('');
            Terminal::output()->writeln('Suggestions:');
            Terminal::output()->writeln('  • Stop the conflicting process');
            Terminal::output()->writeln('  • Change the port in .seaman/seaman.yaml');
            Terminal::output()->writeln('  • Use a different service');
            return Command::FAILURE;
        }

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

    /**
     * @throws PortConflictException
     */
    private function checkPortConflicts(string $projectRoot): void
    {
        // Only check ports if seaman.yaml exists (managed mode)
        if (!file_exists($projectRoot . '/.seaman/seaman.yaml')) {
            return;
        }

        try {
            $config = $this->configManager->load();
        } catch (\Exception) {
            // If we can't load config, skip port checking
            return;
        }

        // Collect all ports from enabled services
        $portsToCheck = [];
        foreach ($config->services->all() as $serviceConfig) {
            if (!$serviceConfig->enabled) {
                continue;
            }

            if ($serviceConfig->port > 0) {
                $portsToCheck[$serviceConfig->port] = $serviceConfig->name;
            }

            foreach ($serviceConfig->additionalPorts as $port) {
                $portsToCheck[$port] = $serviceConfig->name;
            }
        }

        // Check each port
        foreach ($portsToCheck as $port => $serviceName) {
            if ($port > 0) {
                $this->portChecker->ensurePortAvailable($port, $serviceName);
            }
        }
    }
}
