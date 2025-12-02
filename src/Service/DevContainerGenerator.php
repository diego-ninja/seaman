<?php

declare(strict_types=1);

// ABOUTME: Generates DevContainer configuration files for VS Code.
// ABOUTME: Builds dynamic extension list based on enabled services.

namespace Seaman\Service;

use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;
use Seaman\ValueObject\Configuration;

class DevContainerGenerator
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly ConfigManager $configManager,
    ) {}

    /**
     * @return list<string>
     */
    public function buildExtensions(Configuration $config): array
    {
        $extensions = [
            'bmewburn.vscode-intelephense-client',
            'xdebug.php-debug',
            'junstyle.php-cs-fixer',
            'swordev.phpstan',
        ];

        $services = $config->services;

        // Database extensions
        if ($this->hasAnyDatabase($services->all())) {
            $extensions[] = 'cweijan.vscode-database-client2';
        }

        if ($services->has('mongodb')) {
            $extensions[] = 'mongodb.mongodb-vscode';
        }

        // Cache/queue extensions
        if ($services->has('redis')) {
            $extensions[] = 'cisco.redis-xplorer';
        }

        // Search extensions
        if ($services->has('elasticsearch')) {
            $extensions[] = 'ria.elastic';
        }

        // API Platform
        if ($config->projectType === ProjectType::ApiPlatform) {
            $extensions[] = '42crunch.vscode-openapi';
        }

        return $extensions;
    }

    /**
     * @param array<string, \Seaman\ValueObject\ServiceConfig> $services
     */
    private function hasAnyDatabase(array $services): bool
    {
        $databases = ['postgresql', 'mysql', 'mariadb'];

        foreach ($databases as $db) {
            if (isset($services[$db]) && $services[$db]->enabled) {
                return true;
            }
        }

        return false;
    }

    public function generate(string $projectRoot): void
    {
        $config = $this->configManager->load();

        $devcontainerDir = $projectRoot . '/.devcontainer';
        if (!is_dir($devcontainerDir)) {
            mkdir($devcontainerDir, 0755, true);
        }

        $extensions = $this->buildExtensions($config);
        $projectName = basename($projectRoot);

        // Generate devcontainer.json
        $devcontainerJson = $this->renderer->render('devcontainer/devcontainer.json.twig', [
            'project_name' => $projectName,
            'php_version' => $config->php->version->value,
            'xdebug' => $config->php->xdebug,
            'extensions' => $extensions,
        ]);

        file_put_contents($devcontainerDir . '/devcontainer.json', $devcontainerJson);

        // Generate README.md
        $readme = $this->renderer->render('devcontainer/README.md.twig', [
            'project_name' => $projectName,
            'php_version' => $config->php->version->value,
            'extensions' => $extensions,
        ]);

        file_put_contents($devcontainerDir . '/README.md', $readme);
    }

    public function shouldOverwrite(string $projectRoot): bool
    {
        $devcontainerPath = $projectRoot . '/.devcontainer/devcontainer.json';

        if (!file_exists($devcontainerPath)) {
            return true;
        }

        // Backup existing file
        copy($devcontainerPath, $devcontainerPath . '.backup');

        return true;
    }
}
