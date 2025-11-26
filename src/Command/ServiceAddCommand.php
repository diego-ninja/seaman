<?php

declare(strict_types=1);

// ABOUTME: Interactively adds services to the configuration.
// ABOUTME: Handles service selection and configuration updates.

namespace Seaman\Command;

use Seaman\Service\ConfigManager;
use Seaman\Service\Container\ServiceRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'service:add',
    description: 'Interactively add services to configuration',
)]
class ServiceAddCommand extends Command
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
        $available = $this->registry->available($config);

        if (empty($available)) {
            $io->info('All services are already enabled.');
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

        $selected = $io->choice(
            'Which service would you like to add? (separate multiple with comma)',
            $choices,
            null,
        );

        if (!is_string($selected)) {
            $io->error('Invalid selection');
            return Command::FAILURE;
        }

        $selected = array_map('trim', explode(',', $selected));

        foreach ($selected as $serviceName) {
            $service = $this->registry->get($serviceName);
            $defaultConfig = $service->getDefaultConfig();

            $serviceConfig = new \Seaman\ValueObject\ServiceConfig(
                name: $defaultConfig->name,
                enabled: true,
                type: $defaultConfig->type,
                version: $defaultConfig->version,
                port: $defaultConfig->port,
                additionalPorts: $defaultConfig->additionalPorts,
                environmentVariables: $defaultConfig->environmentVariables,
            );

            $services = $config->services->add($serviceName, $serviceConfig);
            $config = new \Seaman\ValueObject\Configuration(
                version: $config->version,
                php: $config->php,
                services: $services,
                volumes: $config->volumes,
            );
        }

        $this->configManager->save($config);

        $io->success('Services added successfully.');

        if ($io->confirm('Start new services now?', false)) {
            $io->note('Service starting not yet implemented.');
        }

        return Command::SUCCESS;
    }
}
