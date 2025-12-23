<?php

declare(strict_types=1);

// ABOUTME: Destroys all Docker services and volumes.
// ABOUTME: Runs docker-compose down -v to remove everything.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Plugin\LifecycleEventData;
use Seaman\Plugin\PluginLifecycleDispatcher;
use Seaman\Service\ConfigManager;
use Seaman\Service\DnsManager;
use Seaman\Service\DockerManager;
use Seaman\UI\Prompts;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'seaman:destroy',
    description: 'Destroy all services and volumes',
    aliases: ['destroy'],
)]
class DestroyCommand extends ModeAwareCommand implements Decorable
{
    public function __construct(
        private readonly ConfigManager $configManager,
        private readonly DockerManager $dockerManager,
        private readonly DnsManager $dnsHelper,
        private readonly PluginLifecycleDispatcher $lifecycleDispatcher,
        private readonly string $projectRoot,
    ) {
        parent::__construct();
    }

    public function supportsMode(\Seaman\Enum\OperatingMode $mode): bool
    {
        return true; // Works in all modes
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!Prompts::confirm('This will remove all containers, networks, and volumes. Are you sure?')) {
            Terminal::success('Operation cancelled');
            return Command::SUCCESS;
        }

        $this->lifecycleDispatcher->dispatch('before:destroy', new LifecycleEventData(
            event: 'before:destroy',
            projectRoot: $this->projectRoot,
        ));

        $result = $this->dockerManager->destroy();

        if (!$result->isSuccessful()) {
            Terminal::error('Failed to destroy services');
            return Command::FAILURE;
        }

        $this->lifecycleDispatcher->dispatch('after:destroy', new LifecycleEventData(
            event: 'after:destroy',
            projectRoot: $this->projectRoot,
        ));

        // Offer DNS cleanup
        Terminal::output()->writeln('');
        if (Prompts::confirm('Clean up DNS configuration?', true)) {
            $this->cleanupDns();
        }

        return Command::SUCCESS;
    }

    private function cleanupDns(): void
    {
        try {
            $config = $this->configManager->load();
        } catch (\Exception) {
            Prompts::info('No DNS configuration found to clean up');
            return;
        }

        $proxy = $config->proxy();
        if (!$proxy->enabled || $proxy->dnsProvider === null) {
            Prompts::info('No DNS configuration found to clean up');
            return;
        }

        Terminal::output()->writeln('  Cleaning DNS configuration...');

        $result = $this->dnsHelper->executeDnsCleanup($config->projectName, $proxy->dnsProvider);

        foreach ($result['messages'] as $message) {
            if ($result['success']) {
                Terminal::output()->writeln("    {$message}");
            } else {
                Terminal::error($message);
            }
        }
    }
}
