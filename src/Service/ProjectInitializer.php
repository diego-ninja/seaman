<?php

declare(strict_types=1);

// ABOUTME: Initializes project Docker environment and configuration.
// ABOUTME: Generates all necessary files for Docker and DevContainer setup.

namespace Seaman\Service;

use Seaman\Service\Container\ServiceRegistry;
use Seaman\UI\Terminal;
use Seaman\ValueObject\Configuration;

class ProjectInitializer
{
    public function __construct(
        private readonly ServiceRegistry $registry,
    ) {}

    /**
     * Initialize Docker environment for the project.
     */
    public function initializeDockerEnvironment(Configuration $config, string $projectRoot): void
    {
        $seamanDir = $projectRoot . '/.seaman';
        if (!is_dir($seamanDir)) {
            mkdir($seamanDir, 0755, true);
        }

        $templateDir = __DIR__ . '/../Template';
        $renderer = new TemplateRenderer($templateDir);

        // Generate docker-compose.yml (in project root)
        $composeGenerator = new DockerComposeGenerator($renderer);
        $composeYaml = $composeGenerator->generate($config);
        file_put_contents($projectRoot . '/docker-compose.yml', $composeYaml);

        // Save configuration
        $validator = new ConfigurationValidator();
        $configManager = new ConfigManager($projectRoot, $this->registry, $validator);
        $configManager->save($config);

        // Generate xdebug-toggle script (needed by Dockerfile build and runtime)
        $xdebugScript = $renderer->render('scripts/xdebug-toggle.sh.twig', [
            'xdebug' => $config->php->xdebug,
        ]);

        // Create in project root for Docker build
        $rootScriptDir = $projectRoot . '/scripts';
        if (!is_dir($rootScriptDir)) {
            mkdir($rootScriptDir, 0755, true);
        }
        file_put_contents($rootScriptDir . '/xdebug-toggle.sh', $xdebugScript);
        chmod($rootScriptDir . '/xdebug-toggle.sh', 0755);

        // Also create in .seaman for volume mount reference
        $seamanScriptDir = $seamanDir . '/scripts';
        if (!is_dir($seamanScriptDir)) {
            mkdir($seamanScriptDir, 0755, true);
        }
        file_put_contents($seamanScriptDir . '/xdebug-toggle.sh', $xdebugScript);
        chmod($seamanScriptDir . '/xdebug-toggle.sh', 0755);

        // Copy Dockerfile template to .seaman/
        $templateDockerfile = __DIR__ . '/../../docker/Dockerfile.template';
        if (!file_exists($templateDockerfile)) {
            Terminal::error('Seaman Dockerfile template not found.');
            throw new \RuntimeException('Template Dockerfile missing');
        }
        copy($templateDockerfile, $seamanDir . '/Dockerfile');

        // Build Docker image
        $builder = new DockerImageBuilder($projectRoot, $config->php->version);
        $result = $builder->build();

        if (!$result->isSuccessful()) {
            Terminal::error('Failed to build Docker image');
            throw new \RuntimeException('Docker build failed');
        }
    }

    /**
     * Generate DevContainer configuration files.
     */
    public function generateDevContainer(string $projectRoot): void
    {
        $templateDir = __DIR__ . '/../Template';
        $renderer = new TemplateRenderer($templateDir);
        $validator = new ConfigurationValidator();
        $configManager = new ConfigManager($projectRoot, $this->registry, $validator);
        $generator = new DevContainerGenerator($renderer, $configManager);

        $generator->generate($projectRoot);

        Terminal::success('DevContainer configuration created');
    }
}
