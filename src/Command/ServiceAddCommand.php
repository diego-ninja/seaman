<?php

declare(strict_types=1);

// ABOUTME: Interactively adds services to the configuration.
// ABOUTME: Handles service selection and configuration updates.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\UI\Terminal;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ServiceConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\multiselect;

#[AsCommand(
    name: 'service:add',
    description: 'Interactively add services to configuration (requires init)',
)]
class ServiceAddCommand extends AbstractServiceCommand implements Decorable
{
    public function __construct(
        private readonly ConfigManager $configManager,
        private readonly ServiceRegistry $registry,
    ) {
        parent::__construct();
    }

    protected function supportsMode(\Seaman\Enum\OperatingMode $mode): bool
    {
        return $mode === \Seaman\Enum\OperatingMode::Managed;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->configManager->load();
        $available = $this->registry->disabled($config);

        if (empty($available)) {
            Terminal::success('All services are already enabled.');
            return Command::SUCCESS;
        }

        $choices = [];
        foreach ($available as $service) {
            $choices[$service->getName()] = sprintf(
                '%s - %s',
                $service->getDisplayName(),
                $service->getDescription(),
            );
        }

        /** @var array<int, string> $selected */
        $selected = multiselect(
            label: 'Which service would you like to add?',
            options: $choices,
        );

        $newConfig = $config;

        foreach ($selected as $serviceName) {
            $serviceEnum = Service::tryFrom($serviceName);
            if ($serviceEnum === null) {
                Terminal::error("Unknown service: {$serviceName}");
                continue;
            }

            $service = $this->registry->get($serviceEnum);
            $defaultConfig = $service->getDefaultConfig();
            $serviceConfig = new ServiceConfig(
                name: $defaultConfig->name,
                enabled: true,
                type: $defaultConfig->type,
                version: $defaultConfig->version,
                port: $defaultConfig->port,
                additionalPorts: $defaultConfig->additionalPorts,
                environmentVariables: $defaultConfig->environmentVariables,
            );
            $services = $newConfig->services->add($serviceName, $serviceConfig);
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
