<?php

// ABOUTME: Installs Seaman plugins from Packagist.
// ABOUTME: Shows interactive selection when no package specified, or installs directly.

declare(strict_types=1);

namespace Seaman\Command\Plugin;

use Seaman\Command\AbstractSeamanCommand;
use Seaman\Exception\PackagistException;
use Seaman\Plugin\PluginRegistry;
use Seaman\Service\PackagistClient;
use Seaman\UI\Prompts;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'plugin:install',
    description: 'Install plugins from Packagist',
)]
final class PluginInstallCommand extends AbstractSeamanCommand
{
    public function __construct(
        private readonly PackagistClient $packagist,
        private readonly PluginRegistry $registry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'package',
            InputArgument::OPTIONAL,
            'The plugin package name (e.g., vendor/seaman-plugin-name)',
        );

        $this->addOption(
            'dev',
            null,
            InputOption::VALUE_NONE,
            'Install as a development dependency',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string|null $package */
        $package = $input->getArgument('package');
        $isDev = (bool) $input->getOption('dev');

        if ($package === null) {
            return $this->interactiveInstall($isDev);
        }

        return $this->installPackage($package, $isDev);
    }

    private function interactiveInstall(bool $isDev): int
    {
        try {
            $availablePlugins = $this->packagist->searchPlugins();
        } catch (PackagistException $e) {
            Terminal::error(sprintf('Could not fetch plugins from Packagist: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        if (empty($availablePlugins)) {
            Terminal::info('No plugins available on Packagist');
            return Command::SUCCESS;
        }

        // Get installed plugin names
        $installedNames = $this->getInstalledPluginNames();

        // Filter out already installed plugins
        $installablePlugins = array_filter(
            $availablePlugins,
            fn(array $plugin): bool => !in_array($plugin['name'], $installedNames, true),
        );

        if (empty($installablePlugins)) {
            Terminal::info('All available plugins are already installed');
            return Command::SUCCESS;
        }

        // Build options for multiselect
        $options = [];
        foreach ($installablePlugins as $plugin) {
            $downloads = $this->formatNumber($plugin['downloads']);
            $description = $this->truncate($plugin['description'], 40);
            $options[$plugin['name']] = sprintf(
                '%s - %s (%s downloads)',
                $plugin['name'],
                $description,
                $downloads,
            );
        }

        $selected = Prompts::multiselect(
            label: 'Select plugins to install',
            options: $options,
            hint: 'Use space to select, enter to confirm',
        );

        if (empty($selected)) {
            Terminal::info('No plugins selected');
            return Command::SUCCESS;
        }

        // Install selected plugins
        $failed = [];
        foreach ($selected as $packageName) {
            $result = $this->installPackage($packageName, $isDev);
            if ($result !== Command::SUCCESS) {
                $failed[] = $packageName;
            }
        }

        if (!empty($failed)) {
            Terminal::error(sprintf(
                'Failed to install: %s',
                implode(', ', $failed),
            ));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function installPackage(string $package, bool $isDev): int
    {
        // Validate that the package is a seaman-plugin
        if (!$this->isValidPlugin($package)) {
            Terminal::error(sprintf(
                'Package "%s" is not a valid seaman-plugin or does not exist on Packagist',
                $package,
            ));
            return Command::FAILURE;
        }

        Terminal::info(sprintf('Installing plugin: %s', $package));

        $command = ['composer', 'require', $package];
        if ($isDev) {
            $command[] = '--dev';
        }

        $process = new Process($command);
        $process->setTimeout(300); // 5 minutes
        $process->setTty(Process::isTtySupported());

        $exitCode = $process->run(function (string $type, string $buffer): void {
            Terminal::output()->write($buffer);
        });

        if ($exitCode !== 0) {
            Terminal::error(sprintf('Failed to install plugin: %s', $package));
            return Command::FAILURE;
        }

        Terminal::success(sprintf('Plugin "%s" installed successfully', $package));

        return Command::SUCCESS;
    }

    private function isValidPlugin(string $package): bool
    {
        try {
            $plugins = $this->packagist->searchPlugins();

            foreach ($plugins as $plugin) {
                if ($plugin['name'] === $package) {
                    return true;
                }
            }

            return false;
        } catch (PackagistException) {
            // If we can't verify, allow the install attempt
            // Composer will fail if the package doesn't exist
            return true;
        }
    }

    /**
     * @return list<string>
     */
    private function getInstalledPluginNames(): array
    {
        $names = [];
        foreach ($this->registry->all() as $loaded) {
            $names[] = $loaded->instance->getName();
        }
        return $names;
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3) . '...';
    }

    private function formatNumber(int $number): string
    {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        }

        if ($number >= 1000) {
            return round($number / 1000, 1) . 'k';
        }

        return (string) $number;
    }
}
