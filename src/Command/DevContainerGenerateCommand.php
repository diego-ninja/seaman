<?php

declare(strict_types=1);

// ABOUTME: Generates DevContainer configuration for VS Code.
// ABOUTME: Can be run standalone or called from InitCommand.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Exception\SeamanException;
use Seaman\Service\ConfigManager;
use Seaman\Service\ConfigurationValidator;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\DevContainerGenerator;
use Seaman\Service\TemplateRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

#[AsCommand(
    name: 'devcontainer:generate',
    description: 'Generate DevContainer configuration for VS Code (requires init)',
)]
class DevContainerGenerateCommand extends ModeAwareCommand implements Decorable
{
    public function __construct(
        private readonly ServiceRegistry $registry,
        private readonly ?DevContainerGenerator $generator = null,
    ) {
        parent::__construct();
    }

    public function supportsMode(\Seaman\Enum\OperatingMode $mode): bool
    {
        return $mode === \Seaman\Enum\OperatingMode::Managed;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = (string) getcwd();

        // Validate prerequisites
        if (!file_exists($projectRoot . '/.seaman/seaman.yaml')) {
            throw new SeamanException('seaman.yaml not found. Run \'seaman init\' first.');
        }

        if (!file_exists($projectRoot . '/docker-compose.yml')) {
            throw new SeamanException('docker-compose.yml not found. Run \'seaman init\' first.');
        }

        // Check if devcontainer already exists
        if (file_exists($projectRoot . '/.devcontainer/devcontainer.json')) {
            if (!confirm(
                label: 'DevContainer configuration already exists. Overwrite?',
                default: false,
            )) {
                info('DevContainer generation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Generate devcontainer files
        $generator = $this->generator ?? $this->createGenerator($projectRoot);
        $generator->generate($projectRoot);

        info('');
        info('✓ DevContainer configuration created in .devcontainer/');
        info('');
        info('Next steps:');
        info('  1. Open this project in VS Code');
        info('  2. Click "Reopen in Container" when prompted');
        info('  3. Wait for container to build and extensions to install');
        info('  4. Start coding!');
        info('');

        return Command::SUCCESS;
    }

    private function createGenerator(string $projectRoot): DevContainerGenerator
    {
        $templateDir = dirname(__DIR__) . '/Template';
        $renderer = new TemplateRenderer($templateDir);
        $configManager = new ConfigManager($projectRoot, $this->registry, new ConfigurationValidator());

        return new DevContainerGenerator($renderer, $configManager);
    }
}
