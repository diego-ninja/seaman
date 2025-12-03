<?php

declare(strict_types=1);

// ABOUTME: Shows status of all Docker services.
// ABOUTME: Displays service name, state, and ports in a table.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Enum\Service;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\table;

#[AsCommand(
    name: 'seaman:status',
    description: 'Show status of all services',
    aliases: ['status'],
)]
class StatusCommand extends AbstractSeamanCommand implements Decorable
{
    public function __construct(private readonly ServiceRegistry $serviceRegistry)
    {
        parent::__construct();
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $manager = new DockerManager((string) getcwd());
        $services = $manager->status();

        if (empty($services)) {
            Terminal::output()->writeln('  No services are running or defined.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($services as $service) {
            $ports = array_unique(array_map(function (array $port) {
                $publishedPort = isset($port['PublishedPort']) && is_int($port['PublishedPort']) ? $port['PublishedPort'] : 0;
                $protocol = isset($port['Protocol']) && is_string($port['Protocol']) ? $port['Protocol'] : '';

                return sprintf(
                    '%d/%s',
                    $publishedPort,
                    $protocol,
                );
            }, $service['Publishers'] ?? []));

            $rows[] = [
                sprintf('%s %s', Service::from($service['Service'])->icon(), $service['Name']),
                $service['Image'],
                $this->formatStatus($service['State'], $service['Health'] ?? 'unknown'),
                $service["RunningFor"],
                $this->formatPorts($ports),
                $this->formatContainerLink($service['ID']),
            ];
        }

        table(['Name', 'Image', 'Status', 'Since', 'Ports','Container'], $rows);

        return Command::SUCCESS;
    }

    private function formatStatus(string $status, string $health): string
    {
        return match (strtolower($status)) {
            'running' => $health !== ''
                ? sprintf('<fg=green>⚙</> running (<fg=green>%s</>)', $health)
                : '<fg=yellow>⚙</> running (<fg=yellow>unknown</>)',
            'exited' => '<fg=red>⚙</> exited',
            'restarting' => '<fg=yellow>⚙</> restarting',
            default => "<fg=gray>⚙</> " . $status,
        };
    }

    private function formatContainerLink(string $containerId): string
    {
        return sprintf("<href=http://localhost:8080/container/%s>%s</>", $containerId, $containerId);
    }

    /**
     * @param array<non-falsy-string> $ports
     * @return string
     */
    private function formatPorts(array $ports): string
    {
        $ports = array_filter($ports, function ($port) {
            return !in_array($port, ['0/tcp', '0/udp'], true);
        });
        return implode(', ', $ports);
    }
}
