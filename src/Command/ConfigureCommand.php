<?php

declare(strict_types=1);

// ABOUTME: Interactive command for configuring services.
// ABOUTME: Renders form from plugin's ConfigSchema and saves to seaman.yaml.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Enum\OperatingMode;
use Seaman\Plugin\Config\BooleanField;
use Seaman\Plugin\Config\IntegerField;
use Seaman\Service\ComposeRegenerator;
use Seaman\Service\ConfigManager;
use Seaman\Service\ConfigurationService;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\DockerManager;
use Seaman\UI\Prompts;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'service:configure',
    description: 'Interactively configure a service',
    aliases: ['configure'],
)]
final class ConfigureCommand extends ModeAwareCommand implements Decorable
{
    public function __construct(
        private readonly ConfigManager $configManager,
        private readonly ServiceRegistry $registry,
        private readonly ConfigurationService $configService,
        private readonly ComposeRegenerator $regenerator,
    ) {
        parent::__construct();
    }

    public function supportsMode(OperatingMode $mode): bool
    {
        return $mode === OperatingMode::Managed;
    }

    protected function configure(): void
    {
        $this->addArgument(
            'service',
            InputArgument::REQUIRED,
            'The service to configure',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->configManager->load();

        /** @var string $serviceName */
        $serviceName = $input->getArgument('service');

        if (!$this->registry->has($serviceName)) {
            Terminal::error("Service '{$serviceName}' not found in registry");
            return Command::FAILURE;
        }

        $enabledServices = array_keys($config->services->enabled());
        if (!in_array($serviceName, $enabledServices, true)) {
            Terminal::error("Service '{$serviceName}' is not enabled. Enable it first with 'seaman service:add'");
            return Command::FAILURE;
        }

        $service = $this->registry->getByName($serviceName);
        if ($service === null) {
            Terminal::error("Could not load service '{$serviceName}'");
            return Command::FAILURE;
        }

        $schema = $service->getConfigSchema();
        if ($schema === null) {
            Terminal::error("Service '{$serviceName}' does not support configuration");
            return Command::FAILURE;
        }

        $rawConfig = $this->loadRawConfig();
        if ($rawConfig === null) {
            Terminal::error('Failed to load seaman.yaml');
            return Command::FAILURE;
        }

        $currentServiceConfig = $this->configService->extractServiceConfig($serviceName, $rawConfig);

        /** @var array<string, mixed> */
        $newConfig = [];
        foreach ($schema->getFields() as $name => $field) {
            $promptConfig = $this->configService->buildPromptConfig($field, $currentServiceConfig);

            $value = match ($promptConfig['type']) {
                'password' => Prompts::password(
                    label: is_string($promptConfig['label']) ? $promptConfig['label'] : '',
                    hint: is_string($promptConfig['hint'] ?? '') ? (string) ($promptConfig['hint'] ?? '') : '',
                ),
                'select' => Prompts::select(
                    label: is_string($promptConfig['label']) ? $promptConfig['label'] : '',
                    /** @phpstan-ignore argument.type */
                    options: is_array($promptConfig['options']) ? $promptConfig['options'] : [],
                    default: isset($promptConfig['default']) && is_string($promptConfig['default']) ? $promptConfig['default'] : null,
                    hint: isset($promptConfig['hint']) && is_string($promptConfig['hint']) ? $promptConfig['hint'] : '',
                ),
                'confirm' => Prompts::confirm(
                    label: is_string($promptConfig['label']) ? $promptConfig['label'] : '',
                    default: is_bool($promptConfig['default'] ?? false) && ($promptConfig['default'] ?? false),
                    hint: is_string($promptConfig['hint'] ?? '') ? (string) ($promptConfig['hint'] ?? '') : '',
                ),
                default => Prompts::text(
                    label: is_string($promptConfig['label']) ? $promptConfig['label'] : '',
                    default: is_string($promptConfig['default'] ?? '') ? (string) ($promptConfig['default'] ?? '') : '',
                    hint: is_string($promptConfig['hint'] ?? '') ? (string) ($promptConfig['hint'] ?? '') : '',
                ),
            };

            if ($field instanceof IntegerField) {
                $newConfig[$name] = (int) $value;
            } elseif ($field instanceof BooleanField) {
                $newConfig[$name] = (bool) $value;
            } else {
                $newConfig[$name] = $value;
            }
        }

        $updatedRawConfig = $this->configService->mergeConfig($rawConfig, $serviceName, $newConfig);
        $this->saveRawConfig($updatedRawConfig);

        Terminal::success("Configuration saved for '{$serviceName}'");

        $restartChoice = Prompts::select(
            label: 'What would you like to do?',
            options: [
                'none' => 'Nothing - I\'ll restart manually',
                'service' => "Restart only {$serviceName}",
                'stack' => 'Restart entire stack',
            ],
            default: 'none',
        );

        if ($restartChoice === 'service') {
            return $this->restartSingleService($serviceName);
        }

        if ($restartChoice === 'stack') {
            $result = $this->regenerator->restartIfConfirmed();
            return $result->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadRawConfig(): ?array
    {
        $configPath = getcwd() . '/.seaman/seaman.yaml';
        if (!file_exists($configPath)) {
            return null;
        }

        $content = file_get_contents($configPath);
        if ($content === false) {
            return null;
        }

        $parsed = Yaml::parse($content);
        if (!is_array($parsed)) {
            return null;
        }

        /** @var array<string, mixed> */
        return $parsed;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function saveRawConfig(array $config): void
    {
        $configPath = getcwd() . '/.seaman/seaman.yaml';
        $yaml = Yaml::dump($config, 4, 2);
        file_put_contents($configPath, $yaml);
    }

    private function restartSingleService(string $serviceName): int
    {
        $projectRoot = (string) getcwd();
        $manager = new DockerManager($projectRoot);

        Terminal::info("Restarting {$serviceName}...");

        $result = $manager->restart($serviceName);
        if (!$result->isSuccessful()) {
            Terminal::error("Failed to restart {$serviceName}");
            Terminal::output()->writeln($result->errorOutput);
            return Command::FAILURE;
        }

        Terminal::success("{$serviceName} restarted successfully");
        return Command::SUCCESS;
    }
}
