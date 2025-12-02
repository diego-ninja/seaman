<?php

declare(strict_types=1);

namespace Seaman\Command;

use Seaman\Service\DockerComposeGenerator;
use Seaman\Service\DockerManager;
use Seaman\Service\TemplateRenderer;
use Seaman\UI\Terminal;
use Seaman\ValueObject\Configuration;
use Symfony\Component\Console\Command\Command;

use function Laravel\Prompts\confirm;

abstract class AbstractServiceCommand extends AbstractSeamanCommand
{
    protected function regenerate(Configuration $config): void
    {
        // Regenerate docker-compose.yml
        $projectRoot = (string) getcwd();
        $templateDir = __DIR__ . '/../Template';
        $renderer = new TemplateRenderer($templateDir);
        $composeGenerator = new DockerComposeGenerator($renderer);
        $composeYaml = $composeGenerator->generate($config);
        file_put_contents($projectRoot . '/docker-compose.yml', $composeYaml);

        Terminal::success('Services added successfully.');
    }

    protected function restartServices(): int
    {
        $projectRoot = (string) getcwd();

        if (confirm(label: 'Restart seaman stack with new services?')) {
            try {
                $manager = new DockerManager($projectRoot);

                // Stop and remove containers without deleting volumes
                $downResult = $manager->down();
                if (!$downResult->isSuccessful()) {
                    Terminal::error('Failed to stop services');
                    Terminal::output()->writeln($downResult->errorOutput);
                    return Command::FAILURE;
                }

                // Start services with new configuration
                $startResult = $manager->start();
                if (!$startResult->isSuccessful()) {
                    Terminal::error('Failed to start services');
                    Terminal::output()->writeln($startResult->errorOutput);
                    return Command::FAILURE;
                }

                Terminal::success('Stack restarted successfully.');
            } catch (\Exception $e) {
                Terminal::error('Error restarting stack: ' . $e->getMessage());
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
