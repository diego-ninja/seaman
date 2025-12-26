<?php

// ABOUTME: Creates a new local plugin scaffold.
// ABOUTME: Generates plugin directory structure and boilerplate code.

declare(strict_types=1);

namespace Seaman\Command\Plugin;

use Seaman\Command\AbstractSeamanCommand;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'plugin:create',
    description: 'Create a new local plugin',
)]
final class PluginCreateCommand extends AbstractSeamanCommand
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Plugin name (kebab-case)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $name */
        $name = $input->getArgument('name');

        if (!preg_match('/^[a-z][a-z0-9-]*$/', $name)) {
            Terminal::error('Plugin name must be kebab-case (e.g., my-plugin)');
            return Command::FAILURE;
        }

        $pluginDir = $this->projectRoot . '/.seaman/plugins/' . $name;

        if (is_dir($pluginDir)) {
            Terminal::error("Plugin directory already exists: {$pluginDir}");
            return Command::FAILURE;
        }

        // Create directory structure
        mkdir($pluginDir . '/src', 0755, true);
        mkdir($pluginDir . '/templates', 0755, true);

        // Generate class name from plugin name
        $className = $this->toClassName($name) . 'Plugin';
        $namespace = 'Seaman\\LocalPlugins\\' . $this->toClassName($name);

        // Generate plugin file
        $content = $this->generatePluginCode($name, $className, $namespace);
        file_put_contents($pluginDir . '/src/' . $className . '.php', $content);

        Terminal::success("Created plugin scaffold at: {$pluginDir}");
        Terminal::output()->writeln("  Main file: src/{$className}.php");
        Terminal::output()->writeln("  Templates: templates/");

        return Command::SUCCESS;
    }

    private function toClassName(string $kebabCase): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $kebabCase)));
    }

    private function generatePluginCode(string $name, string $className, string $namespace): string
    {
        return <<<PHP
<?php

// ABOUTME: Local plugin for project-specific customizations.
// ABOUTME: Add services, commands, and hooks as needed.

declare(strict_types=1);

namespace {$namespace};

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\OnLifecycle;
use Seaman\Plugin\PluginInterface;

#[AsSeamanPlugin(
    name: '{$name}',
    version: '1.0.0',
    description: 'Local plugin for project customizations',
)]
final class {$className} implements PluginInterface
{
    public function getName(): string
    {
        return '{$name}';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Local plugin for project customizations';
    }

    // Example lifecycle hook - uncomment to use:
    // #[OnLifecycle(event: 'after:start')]
    // public function onAfterStart(): void
    // {
    //     // Run after containers start
    // }
}

PHP;
    }
}
