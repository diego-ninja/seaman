<?php

declare(strict_types=1);

// ABOUTME: Lists all available services and their status.
// ABOUTME: Shows which services are enabled or available in a table format.

namespace Seaman\Command;

use Seaman\Contracts\Decorable;
use Seaman\Service\ConfigManager;
use Seaman\Service\Container\ServiceRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Laravel\Prompts\table;

#[AsCommand(
    name: 'service:list',
    description: 'Lists all available services and their status',
)]
class ServiceListCommand extends AbstractSeamanCommand implements Decorable
{
    public function __construct(
        private readonly ConfigManager $configManager,
        private readonly ServiceRegistry $registry,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

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

        table(
            ['Name', 'Display Name', 'Status', 'Port(s)'],
            $rows,
        );

        return Command::SUCCESS;
    }
}
