<?php

declare(strict_types=1);

// ABOUTME: Lists all available services and their status.
// ABOUTME: Shows which services are enabled or available in a table format.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Service\ConfigManager;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\UI\Prompts;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'service:list',
    description: 'Lists all available services and their status (requires init)',
)]
class ServiceListCommand extends ModeAwareCommand implements Decorable
{
    public function __construct(
        private readonly ConfigManager $configManager,
        private readonly ServiceRegistry $registry,
    ) {
        parent::__construct();
    }

    public function supportsMode(\Seaman\Enum\OperatingMode $mode): bool
    {
        return $mode === \Seaman\Enum\OperatingMode::Managed;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->configManager->load();
        $allServices = $this->registry->all();
        $enabledServices = $config->services->enabled();
        $enabledNames = array_keys($enabledServices);

        $rows = [];
        foreach ($allServices as $name => $service) {
            $isEnabled = in_array($name, $enabledNames, true);
            $status = $isEnabled ? '<fg=bright-green>enabled</>' : '<fg=bright-red>disabled</>';

            $ports = $service->getRequiredPorts();
            $portsDisplay = implode(', ', $ports);

            $rows[] = [
                $name,
                $service->getDisplayName(),
                $status,
                $portsDisplay,
            ];
        }

        Prompts::table(
            ['Name', 'Display Name', 'Status', 'Port(s)'],
            $rows,
        );

        return Command::SUCCESS;
    }
}
