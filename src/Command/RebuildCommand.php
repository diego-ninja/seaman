<?php

declare(strict_types=1);

// ABOUTME: Rebuilds Docker images from scratch.
// ABOUTME: Regenerates Dockerfile from template and builds without cache.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Plugin\LifecycleEventData;
use Seaman\Plugin\PluginLifecycleDispatcher;
use Seaman\Service\Builder\DockerImageBuilder;
use Seaman\Service\ConfigManager;
use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'seaman:rebuild',
    description: 'Rebuild docker images from scratch (regenerates Dockerfile, no cache)',
    aliases: ['rebuild'],
)]
class RebuildCommand extends ModeAwareCommand implements Decorable
{
    public function __construct(
        private readonly ConfigManager $configManager,
        private readonly DockerManager $dockerManager,
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
        $this->lifecycleDispatcher->dispatch('before:rebuild', new LifecycleEventData(
            event: 'before:rebuild',
            projectRoot: $this->projectRoot,
        ));

        // Load configuration to get PHP version
        $config = $this->configManager->load();

        // Regenerate Dockerfile from template
        $this->regenerateDockerfile($this->projectRoot);

        // Build Docker image without cache
        $builder = new DockerImageBuilder($this->projectRoot, $config->php->version);
        $buildResult = $builder->build(noCache: true);

        if (!$buildResult->isSuccessful()) {
            return Command::FAILURE;
        }

        $restartResult = $this->dockerManager->restart();

        if ($restartResult->isSuccessful()) {
            $this->lifecycleDispatcher->dispatch('after:rebuild', new LifecycleEventData(
                event: 'after:rebuild',
                projectRoot: $this->projectRoot,
            ));

            return Command::SUCCESS;
        }

        Terminal::output()->writeln($restartResult->errorOutput);
        return Command::FAILURE;
    }

    private function regenerateDockerfile(string $projectRoot): void
    {
        $templateDockerfile = __DIR__ . '/../../docker/Dockerfile.template';
        $targetDockerfile = $projectRoot . '/.seaman/Dockerfile';

        if (!file_exists($templateDockerfile)) {
            throw new \RuntimeException('Seaman Dockerfile template not found');
        }

        $seamanDir = $projectRoot . '/.seaman';
        if (!is_dir($seamanDir)) {
            mkdir($seamanDir, 0755, true);
        }

        copy($templateDockerfile, $targetDockerfile);
        Terminal::output()->writeln('  Dockerfile regenerated from template');
    }
}
