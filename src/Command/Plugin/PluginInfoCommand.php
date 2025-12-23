<?php

// ABOUTME: Shows detailed information about a specific plugin.
// ABOUTME: Displays services, commands, and hooks provided by the plugin.

declare(strict_types=1);

namespace Seaman\Command\Plugin;

use Seaman\Command\AbstractSeamanCommand;
use Seaman\Plugin\Extractor\CommandExtractor;
use Seaman\Plugin\Extractor\LifecycleExtractor;
use Seaman\Plugin\Extractor\ServiceExtractor;
use Seaman\Plugin\Extractor\TemplateExtractor;
use Seaman\Plugin\PluginRegistry;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'plugin:info',
    description: 'Show plugin details',
)]
final class PluginInfoCommand extends AbstractSeamanCommand
{
    public function __construct(
        private readonly PluginRegistry $registry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Plugin name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $name */
        $name = $input->getArgument('name');

        if (!$this->registry->has($name)) {
            Terminal::error("Plugin '{$name}' not found");
            return Command::FAILURE;
        }

        $loaded = $this->registry->get($name);
        $plugin = $loaded->instance;

        Terminal::info("Plugin: {$plugin->getName()}");
        Terminal::output()->writeln("  Version: {$plugin->getVersion()}");
        Terminal::output()->writeln("  Source: {$loaded->source}");
        Terminal::output()->writeln("  Description: " . ($plugin->getDescription() ?: 'None'));
        Terminal::output()->writeln('');

        // Show provided services
        $services = (new ServiceExtractor())->extract($plugin);
        if (!empty($services)) {
            Terminal::output()->writeln('  Services:');
            foreach ($services as $service) {
                Terminal::output()->writeln("    - {$service->name}");
            }
            Terminal::output()->writeln('');
        }

        // Show provided commands
        $commands = (new CommandExtractor())->extract($plugin);
        if (!empty($commands)) {
            Terminal::output()->writeln('  Commands:');
            foreach ($commands as $command) {
                Terminal::output()->writeln("    - {$command->getName()}");
            }
            Terminal::output()->writeln('');
        }

        // Show lifecycle hooks
        $hooks = (new LifecycleExtractor())->extract($plugin);
        if (!empty($hooks)) {
            Terminal::output()->writeln('  Lifecycle hooks:');
            foreach ($hooks as $hook) {
                Terminal::output()->writeln("    - {$hook->event} (priority: {$hook->priority})");
            }
            Terminal::output()->writeln('');
        }

        // Show template overrides
        $templates = (new TemplateExtractor())->extract($plugin);
        if (!empty($templates)) {
            Terminal::output()->writeln('  Template overrides:');
            foreach ($templates as $override) {
                Terminal::output()->writeln("    - {$override->originalTemplate}");
            }
        }

        return Command::SUCCESS;
    }
}
