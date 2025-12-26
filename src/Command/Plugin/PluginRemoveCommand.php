<?php

// ABOUTME: Removes installed Seaman plugins via Composer.
// ABOUTME: Shows interactive selection when no package specified, or removes directly.

declare(strict_types=1);

namespace Seaman\Command\Plugin;

use Seaman\Command\AbstractSeamanCommand;
use Seaman\Plugin\PluginRegistry;
use Seaman\UI\Prompts;
use Seaman\UI\Terminal;
use Seaman\UI\Widget\Spinner\SpinnerFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'plugin:remove',
    description: 'Remove installed plugins',
)]
final class PluginRemoveCommand extends AbstractSeamanCommand
{
    public function __construct(
        private readonly PluginRegistry $registry,
        private readonly string $projectRoot,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'package',
            InputArgument::OPTIONAL,
            'The plugin package name to remove (e.g., seaman/redis)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string|null $package */
        $package = $input->getArgument('package');

        if ($package === null) {
            return $this->interactiveRemove();
        }

        return $this->removePackage($package);
    }

    private function interactiveRemove(): int
    {
        $removablePlugins = $this->getComposerInstalledPlugins();

        if (empty($removablePlugins)) {
            Terminal::info('No Composer-installed plugins to remove');
            return Command::SUCCESS;
        }

        // Build options for multiselect
        $options = [];
        foreach ($removablePlugins as $plugin) {
            $options[$plugin['name']] = sprintf(
                '%s - %s',
                $plugin['name'],
                $plugin['description'],
            );
        }

        $selected = Prompts::multiselect(
            label: 'Select plugins to remove',
            options: $options,
            hint: 'Use space to select, enter to confirm',
        );

        if (empty($selected)) {
            Terminal::info('No plugins selected');
            return Command::SUCCESS;
        }

        // Confirm removal
        $confirmed = Prompts::confirm(
            label: sprintf('Remove %d plugin(s)?', count($selected)),
            default: false,
        );

        if (!$confirmed) {
            Terminal::info('Removal cancelled');
            return Command::SUCCESS;
        }

        // Remove selected plugins
        $failed = [];
        foreach ($selected as $packageName) {
            $result = $this->removePackage($packageName);
            if ($result !== Command::SUCCESS) {
                $failed[] = $packageName;
            }
        }

        if (!empty($failed)) {
            Terminal::error(sprintf(
                'Failed to remove: %s',
                implode(', ', $failed),
            ));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function removePackage(string $package): int
    {
        // Check if composer.json exists in project directory
        $composerJsonPath = $this->projectRoot . '/composer.json';
        if (!file_exists($composerJsonPath)) {
            Terminal::error('No composer.json found. Run this command from your Symfony project directory.');
            return Command::FAILURE;
        }

        // Check if the package is installed via Composer
        if (!$this->isComposerInstalled($package)) {
            Terminal::error(sprintf(
                'Plugin "%s" is not installed via Composer. Only Composer-installed plugins can be removed.',
                $package,
            ));
            return Command::FAILURE;
        }

        $command = ['composer', 'remove', $package];

        $process = new Process($command, $this->projectRoot);
        $process->setTimeout(300); // 5 minutes

        SpinnerFactory::for($process, sprintf('Removing plugin: %s', $package));

        if (!$process->isSuccessful()) {
            Terminal::error(sprintf('Failed to remove plugin: %s', $package));
            return Command::FAILURE;
        }

        Terminal::success(sprintf('Plugin "%s" removed successfully', $package));

        return Command::SUCCESS;
    }

    private function isComposerInstalled(string $package): bool
    {
        foreach ($this->registry->all() as $loaded) {
            if ($loaded->source === 'composer' && $loaded->instance->getName() === $package) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{name: string, description: string}>
     */
    private function getComposerInstalledPlugins(): array
    {
        $plugins = [];

        foreach ($this->registry->all() as $loaded) {
            if ($loaded->source === 'composer') {
                $plugins[] = [
                    'name' => $loaded->instance->getName(),
                    'description' => $loaded->instance->getDescription(),
                ];
            }
        }

        return $plugins;
    }
}
