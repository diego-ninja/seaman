<?php

// ABOUTME: Exports local plugins to distributable Composer packages.
// ABOUTME: Validates plugin structure and delegates to export service.

declare(strict_types=1);

namespace Seaman\Command\Plugin;

use Seaman\Command\AbstractSeamanCommand;
use Seaman\Plugin\Export\PluginExporter;
use Seaman\UI\Prompts;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'plugin:export',
    description: 'Export a local plugin to a distributable Composer package',
)]
final class PluginExportCommand extends AbstractSeamanCommand
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly PluginExporter $exporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('plugin-name', InputArgument::OPTIONAL, 'Name of the plugin to export');
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output directory');
        $this->addOption('vendor', null, InputOption::VALUE_REQUIRED, 'Vendor name for Composer package');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string|null $pluginName */
        $pluginName = $input->getArgument('plugin-name');

        if ($pluginName === null) {
            $pluginName = $this->selectPlugin();
            if ($pluginName === null) {
                return Command::FAILURE;
            }
        }

        $pluginPath = $this->projectRoot . '/.seaman/plugins/' . $pluginName;

        if (!$this->validatePlugin($pluginPath)) {
            return Command::FAILURE;
        }

        /** @var string|null $vendorName */
        $vendorName = $input->getOption('vendor');
        if ($vendorName === null) {
            $vendorName = $this->getDefaultVendor();
        }

        /** @var string|null $outputPath */
        $outputPath = $input->getOption('output');
        if ($outputPath === null) {
            $outputPath = getcwd() . '/exports/' . $pluginName;
        }

        try {
            $this->exporter->export($pluginPath, $outputPath, $vendorName);
            Terminal::success('Plugin exported successfully!');
            Terminal::output()->writeln('');
            Terminal::output()->writeln("  Location: {$outputPath}");
            Terminal::output()->writeln('');
            Terminal::output()->writeln('  Next steps:');
            Terminal::output()->writeln("  1. cd {$outputPath}");
            Terminal::output()->writeln('  2. Review and customize composer.json');
            Terminal::output()->writeln('  3. Initialize git: git init');
            Terminal::output()->writeln('  4. Publish to Packagist: composer publish');
            Terminal::output()->writeln('');
            Terminal::output()->writeln("  Install with: composer require {$vendorName}/{$pluginName}");

            return Command::SUCCESS;
        } catch (\RuntimeException $e) {
            Terminal::error('Export failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function selectPlugin(): ?string
    {
        $pluginsDir = $this->projectRoot . '/.seaman/plugins';

        if (!is_dir($pluginsDir)) {
            Terminal::error('No local plugins directory found');
            return null;
        }

        $plugins = array_diff(scandir($pluginsDir) ?: [], ['.', '..']);
        $plugins = array_values(array_filter($plugins, fn(string $name): bool => is_dir($pluginsDir . '/' . $name)));

        if (empty($plugins)) {
            Terminal::error('No local plugins found');
            return null;
        }

        return Prompts::select(
            label: 'Select a plugin to export:',
            options: $plugins,
        );
    }

    private function validatePlugin(string $pluginPath): bool
    {
        if (!is_dir($pluginPath)) {
            Terminal::error('Plugin not found at: ' . $pluginPath);
            return false;
        }

        $srcPath = $pluginPath . '/src';
        if (!is_dir($srcPath)) {
            Terminal::error("Plugin must have a src/ directory: {$pluginPath}");
            return false;
        }

        if (!$this->hasPluginAttribute($srcPath)) {
            Terminal::error('Plugin must have at least one PHP file with #[AsSeamanPlugin] attribute');
            return false;
        }

        return true;
    }

    private function hasPluginAttribute(string $srcPath): bool
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcPath, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            if (str_contains($content, '#[AsSeamanPlugin')) {
                return true;
            }
        }

        return false;
    }

    private function getDefaultVendor(): string
    {
        $gitUser = shell_exec('git config user.name 2>/dev/null');
        if ($gitUser !== null && $gitUser !== false && trim($gitUser) !== '') {
            return strtolower(str_replace(' ', '-', trim($gitUser)));
        }

        return 'your-vendor';
    }
}
