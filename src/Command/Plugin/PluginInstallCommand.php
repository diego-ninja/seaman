<?php

// ABOUTME: Installs Seaman plugins from Packagist.
// ABOUTME: Wraps composer require to install seaman-plugin packages.

declare(strict_types=1);

namespace Seaman\Command\Plugin;

use Seaman\Command\AbstractSeamanCommand;
use Seaman\Exception\PackagistException;
use Seaman\Service\PackagistClient;
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
    description: 'Install a plugin from Packagist',
)]
final class PluginInstallCommand extends AbstractSeamanCommand
{
    public function __construct(
        private readonly PackagistClient $packagist,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'package',
            InputArgument::REQUIRED,
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
        /** @var string $package */
        $package = $input->getArgument('package');
        $isDev = $input->getOption('dev');

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
            Terminal::error('Failed to install plugin');
            return Command::FAILURE;
        }

        Terminal::success(sprintf('Plugin "%s" installed successfully', $package));
        Terminal::output()->writeln('');
        Terminal::output()->writeln('  <fg=gray>Run "seaman plugin:list" to see installed plugins</>');

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
}
