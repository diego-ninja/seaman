<?php

// ABOUTME: Lists all installed Seaman plugins.
// ABOUTME: Shows plugin name, version, source, and description.

declare(strict_types=1);

namespace Seaman\Command\Plugin;

use Seaman\Command\AbstractSeamanCommand;
use Seaman\Plugin\PluginRegistry;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'plugin:list',
    description: 'List installed plugins',
)]
final class PluginListCommand extends AbstractSeamanCommand
{
    public function __construct(
        private readonly PluginRegistry $registry,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $plugins = $this->registry->all();

        if (empty($plugins)) {
            Terminal::info('No plugins installed');
            return Command::SUCCESS;
        }

        Terminal::info('Installed plugins:');
        Terminal::output()->writeln('');

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

        return Command::SUCCESS;
    }
}
