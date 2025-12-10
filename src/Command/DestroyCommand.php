<?php

declare(strict_types=1);

// ABOUTME: Destroys all Docker services and volumes.
// ABOUTME: Runs docker-compose down -v to remove everything.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Service\ConfigManager;
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

        $result = $this->dockerManager->destroy();

        if (!$result->isSuccessful()) {
            Terminal::error('Failed to destroy services');
            return Command::FAILURE;
        }

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
        } catch (\Exception $e) {
            Prompts::info('No DNS configuration found to clean up.');
            return;
        }

        // Try to detect and remove DNS configuration files
        $configPaths = $this->getDnsConfigurationPaths($config->projectName);
        $removedAny = false;

        foreach ($configPaths as $configPath) {
            if (file_exists($configPath)) {
                Terminal::output()->writeln("  Removing: {$configPath}");
                $rmCmd = "sudo rm {$configPath}";
                exec($rmCmd, $output, $exitCode);

                if ($exitCode === 0) {
                    $removedAny = true;
                } else {
                    Terminal::error("Failed to remove {$configPath}");
                }
            }
        }

        if ($removedAny) {
            // Restart DNS services if we removed anything
            $this->restartDnsServices();
            Terminal::success('DNS configuration cleaned up successfully!');
        } else {
            Prompts::info('No DNS configuration files found.');
        }
    }

    /**
     * @return list<string>
     */
    private function getDnsConfigurationPaths(string $projectName): array
    {
        $paths = [];

        // dnsmasq paths
        if (PHP_OS_FAMILY === 'Linux') {
            $paths[] = "/etc/dnsmasq.d/seaman-{$projectName}.conf";
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $paths[] = "/usr/local/etc/dnsmasq.d/seaman-{$projectName}.conf";
        }

        // systemd-resolved path
        $paths[] = "/etc/systemd/resolved.conf.d/seaman-{$projectName}.conf";

        return $paths;
    }

    private function restartDnsServices(): void
    {
        // Try to restart dnsmasq
        exec('which dnsmasq', $output, $exitCode);
        if ($exitCode === 0) {
            Terminal::output()->writeln('  Restarting dnsmasq...');
            $restartCmd = PHP_OS_FAMILY === 'Darwin'
                ? 'sudo brew services restart dnsmasq'
                : 'sudo systemctl restart dnsmasq';
            exec($restartCmd);
            return;
        }

        // Try to restart systemd-resolved
        exec('systemctl is-active systemd-resolved', $output, $exitCode);
        if ($exitCode === 0) {
            Terminal::output()->writeln('  Restarting systemd-resolved...');
            exec('sudo systemctl restart systemd-resolved');
        }
    }
}
