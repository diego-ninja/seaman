<?php

// ABOUTME: Lists installed and available Seaman plugins.
// ABOUTME: Shows installed plugins and queries Packagist for available ones.

declare(strict_types=1);

namespace Seaman\Command\Plugin;

use Seaman\Command\AbstractSeamanCommand;
use Seaman\Exception\PackagistException;
use Seaman\Plugin\PluginRegistry;
use Seaman\Service\PackagistClient;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'plugin:list',
    description: 'List installed and available plugins',
)]
final class PluginListCommand extends AbstractSeamanCommand
{
    public function __construct(
        private readonly PluginRegistry $registry,
        private readonly PackagistClient $packagist,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'installed',
            'i',
            InputOption::VALUE_NONE,
            'Show only installed plugins',
        );

        $this->addOption(
            'available',
            'a',
            InputOption::VALUE_NONE,
            'Show only available plugins from Packagist',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $showInstalled = !$input->getOption('available');
        $showAvailable = !$input->getOption('installed');

        if ($showInstalled) {
            $this->showInstalledPlugins();
        }

        if ($showAvailable) {
            $this->showAvailablePlugins();
        }

        return Command::SUCCESS;
    }

    private function showInstalledPlugins(): void
    {
        $plugins = $this->registry->all();

        Terminal::info('Installed plugins:');
        Terminal::output()->writeln('');

        if (empty($plugins)) {
            Terminal::output()->writeln('  <fg=gray>No plugins installed</>');
            Terminal::output()->writeln('');
            return;
        }

        foreach ($plugins as $loaded) {
            $plugin = $loaded->instance;
            $source = $loaded->source === 'composer' ? '📦' : '📁';

            Terminal::output()->writeln(sprintf(
                '  %s <fg=green>%s</> <fg=gray>v%s</> - %s',
                $source,
                $plugin->getName(),
                $plugin->getVersion(),
                $plugin->getDescription() ?: 'No description',
            ));
        }

        Terminal::output()->writeln('');
    }

    private function showAvailablePlugins(): void
    {
        Terminal::info('Available plugins (Packagist):');
        Terminal::output()->writeln('');

        try {
            $packages = $this->packagist->searchPlugins();
        } catch (PackagistException $e) {
            Terminal::output()->writeln(sprintf(
                '  <fg=yellow>⚠ Could not fetch from Packagist: %s</>',
                $e->getMessage(),
            ));
            Terminal::output()->writeln('');
            return;
        }

        if (empty($packages)) {
            Terminal::output()->writeln('  <fg=gray>No plugins available on Packagist</>');
            Terminal::output()->writeln('');
            return;
        }

        // Get Composer-installed plugin names (not bundled)
        $composerInstalledNames = [];
        foreach ($this->registry->all() as $loaded) {
            if ($loaded->source === 'composer') {
                $composerInstalledNames[] = $loaded->instance->getName();
            }
        }

        foreach ($packages as $package) {
            $isInstalled = in_array($package['name'], $composerInstalledNames, true);
            $downloads = $package['downloads'] > 0 ? sprintf(' · %s downloads', $this->formatNumber($package['downloads'])) : '';

            if ($isInstalled) {
                Terminal::output()->writeln(sprintf(
                    '  <fg=green>✓</> <fg=green>%s</> - %s <fg=gray>(installed)</>',
                    $package['name'],
                    $this->truncate($package['description'], 50),
                ));
            } else {
                Terminal::output()->writeln(sprintf(
                    '  📦 <fg=cyan>%s</> - %s<fg=gray>%s</>',
                    $package['name'],
                    $this->truncate($package['description'], 50),
                    $downloads,
                ));
            }
        }

        Terminal::output()->writeln('');
        Terminal::output()->writeln('  <fg=gray>Install with:</> seaman plugin:install <fg=cyan><package-name></>');
        Terminal::output()->writeln('');
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
