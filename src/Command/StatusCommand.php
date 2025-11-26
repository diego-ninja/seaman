<?php

declare(strict_types=1);

// ABOUTME: Shows status of all Docker services.
// ABOUTME: Displays service name, state, and ports in a table.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaman:status',
    description: 'Show status of all services',
    aliases: ['status'],
)]
class StatusCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = (string) getcwd();

        // Check if seaman.yaml exists
        if (!file_exists($projectRoot . '/seaman.yaml')) {
            $io->error('seaman.yaml not found. Run "seaman init" first.');
            return Command::FAILURE;
        }

        $manager = new DockerManager((string) getcwd());

        $services = $manager->status();

        if (empty($services)) {
            $io->warning('No services are running or defined.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($services as $service) {
            $ports = array_map(function ($p) {
                if (!is_array($p)) {
                    return '';
                }
                $url = isset($p['URL']) && is_string($p['URL']) ? $p['URL'] : '';
                $publishedPort = isset($p['PublishedPort']) && is_int($p['PublishedPort']) ? $p['PublishedPort'] : 0;
                $targetPort = isset($p['TargetPort']) && is_int($p['TargetPort']) ? $p['TargetPort'] : 0;
                $protocol = isset($p['Protocol']) && is_string($p['Protocol']) ? $p['Protocol'] : '';

                return sprintf(
                    '%s:%d->%d/%s',
                    $url,
                    $publishedPort,
                    $targetPort,
                    $protocol,
                );
            }, $service['Publishers'] ?? []);

            $serviceName = isset($service['Service']) && is_string($service['Service']) ? $service['Service'] : '';
            $state = isset($service['State']) && is_string($service['State']) ? $service['State'] : '';

            $rows[] = [
                $serviceName,
                $this->formatStatus($state),
                implode(', ', $ports),
            ];
        }

        $io->table(['Service', 'Status', 'Ports'], $rows);

        return Command::SUCCESS;
    }

    private function formatStatus(string $status): string
    {
        return match (strtolower($status)) {
            'running' => '<fg=green>● Running</>',
            'exited' => '<fg=red>● Exited</>',
            'restarting' => '<fg=yellow>● Restarting</>',
            default => "<fg=gray>● " . ucfirst($status) . "</>",
        };
    }
}
