<?php

declare(strict_types=1);

// ABOUTME: Removes services from the configuration.
// ABOUTME: Handles service removal with confirmation.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Service\ConfigManager;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\UI\Prompts;
use Seaman\UI\Terminal;
use Seaman\ValueObject\Configuration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'service:remove',
    description: 'Remove services from configuration (requires init)',
)]
class ServiceRemoveCommand extends AbstractServiceCommand implements Decorable
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
        $enabled = $this->registry->enabled($config);

        if (empty($enabled)) {
            Terminal::success('No services are currently enabled.');
            return Command::SUCCESS;
        }

        $choices = [];
        foreach ($enabled as $service) {
            $choices[$service->getName()] = sprintf(
                '%s - %s',
                $service->getDisplayName(),
                $service->getDescription(),
            );
        }

        /** @var array<int, string> $selected */
        $selected = Prompts::multiselect(
            label: 'Which services would you like to remove?',
            options: $choices,
        );

        if (empty($selected)) {
            Terminal::success('No services selected.');
            return Command::SUCCESS;
        }

        if (!Prompts::confirm(label: 'Are you sure you want to remove these services?', default: false)) {
            Terminal::success('Operation cancelled');
            return Command::SUCCESS;
        }

        $newConfig = $config;

        foreach ($selected as $serviceName) {
            $services = $newConfig->services->remove($serviceName);
            $newConfig = new Configuration(
                projectName: $newConfig->projectName,
                version: $newConfig->version,
                php: $newConfig->php,
                services: $services,
                volumes: $newConfig->volumes,
            );
        }

        $this->configManager->save($newConfig);
        $this->regenerate($newConfig);

        return $this->restartServices();
    }
}
