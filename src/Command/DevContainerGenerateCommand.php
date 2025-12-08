<?php

declare(strict_types=1);

// ABOUTME: Generates DevContainer configuration for VS Code.
// ABOUTME: Can be run standalone or called from InitCommand.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Exception\SeamanException;
use Seaman\Service\ConfigManager;
use Seaman\Service\Generator\DevContainerGenerator;
use Seaman\Service\TemplateRenderer;
use Seaman\UI\Prompts;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'devcontainer:generate',
    description: 'Generate DevContainer configuration for VS Code',
)]
class DevContainerGenerateCommand extends ModeAwareCommand implements Decorable
{
    public function __construct(
        private readonly ConfigManager $configManager,
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
            if (!Prompts::confirm(
                label: 'DevContainer configuration already exists. Overwrite?',
                default: false,
            )) {
                Prompts::info('DevContainer generation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Generate devcontainer files
        $generator = $this->generator ?? $this->createGenerator();
        $generator->generate($projectRoot);

        Prompts::info('');
        Prompts::info('✓ DevContainer configuration created in .devcontainer/');
        Prompts::info('');
        Prompts::info('Next steps:');
        Prompts::info('  1. Open this project in VS Code');
        Prompts::info('  2. Click "Reopen in Container" when prompted');
        Prompts::info('  3. Wait for container to build and extensions to install');
        Prompts::info('  4. Start coding!');
        Prompts::info('');

        return Command::SUCCESS;
    }

    private function createGenerator(): DevContainerGenerator
    {
        $templateDir = dirname(__DIR__) . '/Template';
        $renderer = new TemplateRenderer($templateDir);

        return new DevContainerGenerator($renderer, $this->configManager);
    }
}
