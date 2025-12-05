<?php

declare(strict_types=1);

// ABOUTME: Initializes project Docker environment and configuration.
// ABOUTME: Generates Docker, Traefik, and DevContainer configurations.

namespace Seaman\Service;

use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\Process\CertificateManager;
use Seaman\Service\Process\RealCommandExecutor;
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
        $labelGenerator = new TraefikLabelGenerator();
        $composeGenerator = new DockerComposeGenerator($renderer, $labelGenerator);
        $composeYaml = $composeGenerator->generate($config);
        file_put_contents($projectRoot . '/docker-compose.yml', $composeYaml);

        // Initialize Traefik configuration and certificates only if proxy enabled
        if ($config->proxy()->enabled) {
            $this->initializeTraefik($config, $projectRoot);
        }

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

    /**
     * Initialize Traefik configuration and SSL certificates.
     */
    private function initializeTraefik(Configuration $config, string $projectRoot): void
    {
        $seamanDir = $projectRoot . '/.seaman';

        // Create Traefik directories
        $traefikDir = $seamanDir . '/traefik';
        $dynamicDir = $traefikDir . '/dynamic';
        $certsDir = $seamanDir . '/certs';

        foreach ([$traefikDir, $dynamicDir, $certsDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Copy Traefik static configuration
        $traefikTemplate = __DIR__ . '/../../resources/templates/traefik/traefik.yml';
        if (file_exists($traefikTemplate)) {
            copy($traefikTemplate, $traefikDir . '/traefik.yml');
        }

        // Copy Traefik dynamic certificate configuration
        $certsTemplate = __DIR__ . '/../../resources/templates/traefik/dynamic/certs.yml';
        if (file_exists($certsTemplate)) {
            copy($certsTemplate, $dynamicDir . '/certs.yml');
        }

        // Generate SSL certificates
        $executor = new RealCommandExecutor();
        $certManager = new CertificateManager($executor);

        // Change to project directory to generate certificates in the right location
        $originalDir = getcwd();
        if ($originalDir === false) {
            throw new \RuntimeException('Could not get current working directory');
        }

        chdir($projectRoot);

        try {
            $result = $certManager->generateCertificates($config->projectName);

            if ($result->trusted) {
                Terminal::success('Generated trusted SSL certificates with mkcert');
            } else {
                Terminal::output()->writeln('  Generated self-signed SSL certificates');
                Terminal::output()->writeln('  Install mkcert for browser-trusted certificates');
            }
        } finally {
            chdir($originalDir);
        }
    }
}
