<?php

declare(strict_types=1);

// ABOUTME: Provides database service selection functionality for commands.
// ABOUTME: Handles auto-selection, explicit selection, and interactive prompts.

namespace Seaman\Command\Concern;

use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\UI\Terminal;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ServiceConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

use function Laravel\Prompts\select;

trait SelectsDatabaseService
{
    abstract protected function getConfigManager(): ConfigManager;

    /**
     * Loads configuration and selects a validated database service.
     *
     * @return ServiceConfig|int Returns ServiceConfig on success, or Command::FAILURE on error
     */
    protected function loadAndSelectDatabase(InputInterface $input): ServiceConfig|int
    {
        try {
            $config = $this->getConfigManager()->load();
        } catch (\RuntimeException $e) {
            Terminal::error('Failed to load configuration: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $serviceName = $input->getOption('service');
        if (!is_string($serviceName) && $serviceName !== null) {
            Terminal::error('Invalid service option.');
            return Command::FAILURE;
        }

        try {
            $databaseService = $this->selectDatabaseService($config, $serviceName);
        } catch (\RuntimeException $e) {
            Terminal::error($e->getMessage());
            return Command::FAILURE;
        }

        if ($databaseService === null) {
            Terminal::error('No database service found in configuration.');
            Terminal::output()->writeln('Add a database service with: seaman service:add');
            return Command::FAILURE;
        }

        if (!$databaseService->enabled) {
            Terminal::error("Database service '{$databaseService->name}' is not enabled.");
            return Command::FAILURE;
        }

        return $databaseService;
    }

    /**
     * Selects a database service from configuration.
     *
     * @param Configuration $config The configuration containing services
     * @param string|null $serviceName Optional explicit service name to select
     * @return ServiceConfig|null The selected database service, or null if none found
     * @throws \RuntimeException When the specified service is not found
     */
    private function selectDatabaseService(Configuration $config, ?string $serviceName): ?ServiceConfig
    {
        $databases = array_filter(
            $config->services->all(),
            fn(ServiceConfig $s): bool => in_array($s->type->value, Service::databases(), true),
        );

        if ($serviceName !== null) {
            $service = array_find(
                $databases,
                fn(ServiceConfig $s): bool => $s->name === $serviceName,
            );

            if ($service === null) {
                throw new \RuntimeException("Service '{$serviceName}' not found");
            }

            return $service;
        }

        $databasesArray = array_values($databases);

        if (count($databasesArray) === 0) {
            return null;
        }

        if (count($databasesArray) === 1) {
            return $databasesArray[0];
        }

        // Multiple databases - ask user to select
        $choices = [];
        foreach ($databasesArray as $db) {
            $choices[$db->name] = sprintf('%s (%s)', $db->name, $db->type->value);
        }

        $selected = select(
            label: 'Select database service:',
            options: $choices,
        );

        return array_find(
            $databasesArray,
            fn(ServiceConfig $s): bool => $s->name === $selected,
        ) ?? $databasesArray[0];
    }
}
