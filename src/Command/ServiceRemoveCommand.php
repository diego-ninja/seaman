<?php

declare(strict_types=1);

// ABOUTME: Removes services from the configuration.
// ABOUTME: Handles service removal with confirmation.

namespace Seaman\Command;

use Seaman\Service\ConfigManager;
use Seaman\Service\Container\ServiceRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'service:remove',
    description: 'Remove services from configuration',
)]
class ServiceRemoveCommand extends Command
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
        $enabled = $this->registry->enabled($config);

        if (empty($enabled)) {
            $io->info('No services are currently enabled.');
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

        $selected = $io->choice(
            'Which service would you like to remove?',
            $choices,
            null,
        );

        if (!is_string($selected)) {
            $io->error('Invalid selection');
            return Command::FAILURE;
        }

        $selected = array_map('trim', explode(',', $selected));

        if (!$io->confirm('Are you sure you want to remove these services?', false)) {
            $io->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        foreach ($selected as $serviceName) {
            $services = $config->services->remove($serviceName);
            $config = new \Seaman\ValueObject\Configuration(
                version: $config->version,
                php: $config->php,
                services: $services,
                volumes: $config->volumes,
            );
        }

        $this->configManager->save($config);

        $io->success('Services removed successfully.');

        if ($io->confirm('Stop removed services now?', false)) {
            $io->note('Service stopping not yet implemented.');
        }

        return Command::SUCCESS;
    }
}
