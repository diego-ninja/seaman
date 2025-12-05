<?php

declare(strict_types=1);

// ABOUTME: Command to disable Traefik reverse proxy.
// ABOUTME: Regenerates docker-compose with direct port access.

namespace Seaman\Command;

use Seaman\Enum\OperatingMode;
use Seaman\Service\ConfigManager;
use Seaman\Service\ConfigurationValidator;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\DockerComposeGenerator;
use Seaman\Service\TemplateRenderer;
use Seaman\Service\TraefikLabelGenerator;
use Seaman\UI\Terminal;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ProxyConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'seaman:proxy:disable',
    description: 'Disable Traefik reverse proxy',
    aliases: ['proxy:disable'],
)]
class ProxyDisableCommand extends ModeAwareCommand
{
    public function __construct(
        private readonly ServiceRegistry $registry,
    ) {
        parent::__construct();
    }

    protected function supportsMode(OperatingMode $mode): bool
    {
        return $mode === OperatingMode::Managed;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = (string) getcwd();

        // Load current configuration
        $validator = new ConfigurationValidator();
        $configManager = new ConfigManager($projectRoot, $this->registry, $validator);

        try {
            $config = $configManager->load();
        } catch (\RuntimeException $e) {
            Terminal::error('Failed to load configuration: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Check if already disabled
        if (!$config->proxy()->enabled) {
            Terminal::output()->writeln('  <fg=yellow>ℹ</> Proxy is already disabled.');
            return Command::SUCCESS;
        }

        // Create new configuration with proxy disabled
        $newConfig = new Configuration(
            projectName: $config->projectName,
            version: $config->version,
            php: $config->php,
            services: $config->services,
            volumes: $config->volumes,
            projectType: $config->projectType,
            proxy: ProxyConfig::disabled(),
            customServices: $config->customServices,
        );

        // Regenerate docker-compose.yml
        $templateDir = __DIR__ . '/../Template';
        $renderer = new TemplateRenderer($templateDir);
        $labelGenerator = new TraefikLabelGenerator();
        $composeGenerator = new DockerComposeGenerator($renderer, $labelGenerator);
        $composeYaml = $composeGenerator->generate($newConfig);
        file_put_contents($projectRoot . '/docker-compose.yml', $composeYaml);

        // Save updated configuration
        $configManager->save($newConfig);

        Terminal::success('Proxy disabled successfully.');
        Terminal::output()->writeln('');
        Terminal::output()->writeln("  Run 'seaman restart' to apply changes.");
        Terminal::output()->writeln('');
        Terminal::output()->writeln('  Your services will be accessible at:');
        Terminal::output()->writeln('  • http://localhost:80 (app)');

        return Command::SUCCESS;
    }
}
