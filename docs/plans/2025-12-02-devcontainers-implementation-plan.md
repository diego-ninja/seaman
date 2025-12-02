# DevContainers Support Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add VS Code DevContainers support to Seaman with optional generation during init and manual generation command.

**Architecture:** Create DevContainerGenerator service for extension selection and file generation. Add DevContainerGenerateCommand for manual generation. Integrate with InitCommand via --with-devcontainer flag and interactive prompt. Use Twig templates for devcontainer.json and README.md generation.

**Tech Stack:** PHP 8.4, Symfony Console, Twig templates, Laravel Prompts, PHPStan level 10, Pest testing framework

---

## Task 1: Create DevContainerGenerator Service

**Files:**
- Create: `src/Service/DevContainerGenerator.php`
- Test: `tests/Unit/Service/DevContainerGeneratorTest.php`

**Step 1: Write the failing test for extension building**

Create test file with basic extension selection logic:

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Service;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\Service\DevContainerGenerator;
use Seaman\Service\TemplateRenderer;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;

test('builds base extensions correctly', function () {
    $renderer = \Mockery::mock(TemplateRenderer::class);
    $configManager = \Mockery::mock(ConfigManager::class);

    $generator = new DevContainerGenerator($renderer, $configManager);

    $config = new Configuration(
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, new XdebugConfig(false, 'VSCODE', 'host.docker.internal')),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
    );

    $extensions = $generator->buildExtensions($config);

    expect($extensions)->toContain('bmewburn.vscode-intelephense-client')
        ->and($extensions)->toContain('xdebug.php-debug')
        ->and($extensions)->toContain('junstyle.php-cs-fixer')
        ->and($extensions)->toContain('swordev.phpstan');
});

test('adds database extension when postgresql enabled', function () {
    $renderer = \Mockery::mock(TemplateRenderer::class);
    $configManager = \Mockery::mock(ConfigManager::class);

    $generator = new DevContainerGenerator($renderer, $configManager);

    $serviceConfig = new ServiceConfig(
        name: 'postgresql',
        enabled: true,
        type: Service::PostgreSQL,
        version: '16',
        port: 5432,
        additionalPorts: [],
        environmentVariables: [],
    );

    $config = new Configuration(
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, new XdebugConfig(false, 'VSCODE', 'host.docker.internal')),
        services: new ServiceCollection(['postgresql' => $serviceConfig]),
        volumes: new VolumeConfig([]),
    );

    $extensions = $generator->buildExtensions($config);

    expect($extensions)->toContain('cweijan.vscode-database-client2');
});

test('adds redis extension when redis enabled', function () {
    $renderer = \Mockery::mock(TemplateRenderer::class);
    $configManager = \Mockery::mock(ConfigManager::class);

    $generator = new DevContainerGenerator($renderer, $configManager);

    $serviceConfig = new ServiceConfig(
        name: 'redis',
        enabled: true,
        type: Service::Redis,
        version: '7-alpine',
        port: 6379,
        additionalPorts: [],
        environmentVariables: [],
    );

    $config = new Configuration(
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, new XdebugConfig(false, 'VSCODE', 'host.docker.internal')),
        services: new ServiceCollection(['redis' => $serviceConfig]),
        volumes: new VolumeConfig([]),
    );

    $extensions = $generator->buildExtensions($config);

    expect($extensions)->toContain('cisco.redis-xplorer');
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/DevContainerGeneratorTest.php`
Expected: FAIL with "Class Seaman\Service\DevContainerGenerator not found"

**Step 3: Write minimal DevContainerGenerator implementation**

Create `src/Service/DevContainerGenerator.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Generates DevContainer configuration files for VS Code.
// ABOUTME: Builds dynamic extension list based on enabled services.

namespace Seaman\Service;

use Seaman\Enum\Service;
use Seaman\ValueObject\Configuration;

class DevContainerGenerator
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly ConfigManager $configManager,
    ) {}

    /**
     * @return list<string>
     */
    public function buildExtensions(Configuration $config): array
    {
        $extensions = [
            'bmewburn.vscode-intelephense-client',
            'xdebug.php-debug',
            'junstyle.php-cs-fixer',
            'swordev.phpstan',
        ];

        $services = $config->services;

        // Database extensions
        if ($this->hasAnyDatabase($services->all())) {
            $extensions[] = 'cweijan.vscode-database-client2';
        }

        if ($services->has('mongodb')) {
            $extensions[] = 'mongodb.mongodb-vscode';
        }

        // Cache/queue extensions
        if ($services->has('redis')) {
            $extensions[] = 'cisco.redis-xplorer';
        }

        // Search extensions
        if ($services->has('elasticsearch')) {
            $extensions[] = 'ria.elastic';
        }

        return $extensions;
    }

    /**
     * @param array<string, \Seaman\ValueObject\ServiceConfig> $services
     */
    private function hasAnyDatabase(array $services): bool
    {
        $databases = ['postgresql', 'mysql', 'mariadb'];

        foreach ($databases as $db) {
            if (isset($services[$db]) && $services[$db]->enabled) {
                return true;
            }
        }

        return false;
    }

    public function generate(string $projectRoot): void
    {
        $config = $this->configManager->load();

        $devcontainerDir = $projectRoot . '/.devcontainer';
        if (!is_dir($devcontainerDir)) {
            mkdir($devcontainerDir, 0755, true);
        }

        $extensions = $this->buildExtensions($config);
        $projectName = basename($projectRoot);

        // Generate devcontainer.json
        $devcontainerJson = $this->renderer->render('devcontainer/devcontainer.json.twig', [
            'project_name' => $projectName,
            'php_version' => $config->php->version->value,
            'xdebug' => $config->php->xdebug,
            'extensions' => $extensions,
        ]);

        file_put_contents($devcontainerDir . '/devcontainer.json', $devcontainerJson);

        // Generate README.md
        $readme = $this->renderer->render('devcontainer/README.md.twig', [
            'project_name' => $projectName,
        ]);

        file_put_contents($devcontainerDir . '/README.md', $readme);
    }

    public function shouldOverwrite(string $projectRoot): bool
    {
        $devcontainerPath = $projectRoot . '/.devcontainer/devcontainer.json';

        if (!file_exists($devcontainerPath)) {
            return true;
        }

        // Backup existing file
        copy($devcontainerPath, $devcontainerPath . '.backup');

        return true;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/DevContainerGeneratorTest.php`
Expected: PASS (all 3 tests pass)

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/Service/DevContainerGenerator.php`
Expected: No errors at level 10

**Step 6: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/Service/DevContainerGenerator.php tests/Unit/Service/DevContainerGeneratorTest.php`
Expected: Files formatted correctly

**Step 7: Commit**

```bash
git add src/Service/DevContainerGenerator.php tests/Unit/Service/DevContainerGeneratorTest.php
git commit -m "feat(devcontainer): add DevContainerGenerator service

- Builds dynamic VS Code extension list based on enabled services
- Generates devcontainer.json and README.md files
- Handles backup of existing devcontainer files
- Tests for extension selection logic

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 2: Create Twig Templates for DevContainer Files

**Files:**
- Create: `src/Template/devcontainer/devcontainer.json.twig`
- Create: `src/Template/devcontainer/README.md.twig`

**Step 1: Create devcontainer.json template**

Create `src/Template/devcontainer/devcontainer.json.twig`:

```twig
{
  "name": "{{ project_name }}",
  "dockerComposeFile": "../docker-compose.yml",
  "service": "app",
  "workspaceFolder": "/var/www/html",
  "shutdownAction": "stopCompose",
  "customizations": {
    "vscode": {
      "extensions": [
{% for extension in extensions %}
        "{{ extension }}"{{ not loop.last ? ',' : '' }}
{% endfor %}
      ],
      "settings": {
        "php.suggest.basic": false,
        "intelephense.telemetry.enabled": false,
        "php.validate.enable": false,
        "[php]": {
          "editor.defaultFormatter": "junstyle.php-cs-fixer"
        },
        "php-cs-fixer.executablePath": "${workspaceFolder}/vendor/bin/php-cs-fixer",
        "php-cs-fixer.onsave": true,
        "phpstan.path": "${workspaceFolder}/vendor/bin/phpstan",
        "phpstan.enableStatusBar": true,
        "xdebug.mode": "{{ xdebug.enabled ? 'debug' : 'off' }}",
        "php.debug.ideKey": "{{ xdebug.ideKey }}",
        "php.debug.host": "{{ xdebug.clientHost }}"
      }
    }
  },
  "postCreateCommand": "composer install",
  "remoteUser": "www-data"
}
```

**Step 2: Create README.md template**

Create `src/Template/devcontainer/README.md.twig`:

```twig
# DevContainer Configuration for {{ project_name }}

This directory contains the DevContainer configuration for VS Code's "Remote - Containers" extension.

## What are DevContainers?

DevContainers allow you to use a Docker container as a complete development environment. When you open this project in VS Code, you can develop inside the container with all tools and extensions pre-configured.

## Requirements

- [VS Code](https://code.visualstudio.com/)
- [Remote - Containers extension](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers)
- Docker Desktop or Docker Engine running

## How to Use

1. **Open project in VS Code**
2. **Reopen in Container**
   - VS Code will prompt: "Folder contains a Dev Container configuration file. Reopen folder to develop in a container"
   - Click "Reopen in Container"
   - Or use Command Palette: `Remote-Containers: Reopen in Container`
3. **Wait for setup**
   - VS Code will start all Docker services
   - Install configured extensions
   - Run `composer install`
4. **Start coding!**

## What's Included

### Pre-installed Extensions

- **PHP IntelliSense** (Intelephense) - Advanced PHP code intelligence
- **PHP Debug** - Xdebug integration for step debugging
- **PHP CS Fixer** - Automatic code formatting (PER standard)
- **PHPStan** - Static analysis for type safety

{% if extensions|length > 4 %}
Additional service-specific extensions:
{% for extension in extensions %}
{% if extension not in ['bmewburn.vscode-intelephense-client', 'xdebug.php-debug', 'junstyle.php-cs-fixer', 'swordev.phpstan'] %}
- {{ extension }}
{% endif %}
{% endfor %}
{% endif %}

### Configured Tools

- PHP {{ php_version }}
- Composer
- Symfony CLI
- All services from docker-compose.yml (database, Redis, etc.)

### VS Code Settings

- PHP CS Fixer runs on save
- PHPStan enabled with status bar
- Xdebug configured and ready
- Proper PHP language settings

## Customization

You can customize the DevContainer by editing `devcontainer.json`:

- **Add more extensions**: Add to the `extensions` array
- **Change settings**: Modify the `settings` object
- **Add post-create commands**: Change `postCreateCommand`
- **Environment variables**: Add `containerEnv` section

Example:

```json
{
  "customizations": {
    "vscode": {
      "extensions": [
        "your.extension.id"
      ],
      "settings": {
        "your.setting": "value"
      }
    }
  },
  "postCreateCommand": "composer install && php bin/console cache:clear"
}
```

## Troubleshooting

### Container won't start

1. Check Docker is running: `docker ps`
2. Check docker-compose.yml is valid: `docker-compose config`
3. Rebuild container: Command Palette → `Remote-Containers: Rebuild Container`

### Extensions not installing

1. Check internet connection
2. Rebuild container without cache
3. Check VS Code extension marketplace is accessible

### Xdebug not working

1. Verify Xdebug is enabled in seaman.yaml
2. Run `seaman xdebug on` if needed
3. Check VS Code launch configuration exists
4. Verify firewall allows Docker connections

### Performance issues

1. Ensure Docker has enough resources (CPU, RAM)
2. Check volume mounting performance
3. Consider using named volumes for vendor/

## Regenerating Configuration

If you change services in `seaman.yaml`, regenerate devcontainer files:

```bash
seaman devcontainer:generate
```

This will update the configuration based on your current services.

## More Information

- [VS Code DevContainers Documentation](https://code.visualstudio.com/docs/remote/containers)
- [DevContainer Specification](https://containers.dev/)
- [Seaman Documentation](https://github.com/diego-ninja/seaman)
```

**Step 3: Verify templates exist**

Run: `ls -la src/Template/devcontainer/`
Expected: Both devcontainer.json.twig and README.md.twig exist

**Step 4: Commit**

```bash
git add src/Template/devcontainer/
git commit -m "feat(devcontainer): add Twig templates for devcontainer files

- devcontainer.json.twig with dynamic extension list
- README.md.twig with comprehensive documentation
- VS Code settings for PHP development tools
- Xdebug configuration from seaman.yaml

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 3: Create DevContainerGenerateCommand

**Files:**
- Create: `src/Command/DevContainerGenerateCommand.php`
- Test: `tests/Unit/Command/DevContainerGenerateCommandTest.php`

**Step 1: Write the failing test**

Create test file:

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Command;

use Seaman\Command\DevContainerGenerateCommand;
use Seaman\Exception\SeamanException;
use Seaman\Service\ConfigManager;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\DevContainerGenerator;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->testDir);
    chdir($this->testDir);
});

afterEach(function () {
    if (is_dir($this->testDir)) {
        exec("rm -rf {$this->testDir}");
    }
});

test('command requires seaman.yaml to exist', function () {
    $registry = \Mockery::mock(ServiceRegistry::class);
    $generator = \Mockery::mock(DevContainerGenerator::class);

    $command = new DevContainerGenerateCommand($registry, $generator);
    $tester = new CommandTester($command);

    expect(fn() => $tester->execute([]))->toThrow(SeamanException::class);
});

test('command generates devcontainer files successfully', function () {
    // Create seaman.yaml and docker-compose.yml
    mkdir('.seaman');
    file_put_contents('.seaman/seaman.yaml', 'version: "1.0"');
    file_put_contents('docker-compose.yml', 'services:');

    $registry = \Mockery::mock(ServiceRegistry::class);
    $generator = \Mockery::mock(DevContainerGenerator::class);
    $generator->shouldReceive('generate')->once();

    $command = new DevContainerGenerateCommand($registry, $generator);
    $tester = new CommandTester($command);

    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(0);
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Command/DevContainerGenerateCommandTest.php`
Expected: FAIL with "Class Seaman\Command\DevContainerGenerateCommand not found"

**Step 3: Write minimal DevContainerGenerateCommand implementation**

Create `src/Command/DevContainerGenerateCommand.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Generates DevContainer configuration for VS Code.
// ABOUTME: Can be run standalone or called from InitCommand.

namespace Seaman\Command;

use Seaman\Contracts\Decorable;
use Seaman\Exception\SeamanException;
use Seaman\Service\ConfigManager;
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
    description: 'Generate DevContainer configuration for VS Code',
)]
class DevContainerGenerateCommand extends AbstractSeamanCommand implements Decorable
{
    public function __construct(
        private readonly ServiceRegistry $registry,
        private readonly ?DevContainerGenerator $generator = null,
    ) {
        parent::__construct();
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
        $configManager = new ConfigManager($projectRoot, $this->registry);

        return new DevContainerGenerator($renderer, $configManager);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Command/DevContainerGenerateCommandTest.php`
Expected: PASS

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/Command/DevContainerGenerateCommand.php`
Expected: No errors at level 10

**Step 6: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/Command/DevContainerGenerateCommand.php tests/Unit/Command/DevContainerGenerateCommandTest.php`
Expected: Files formatted correctly

**Step 7: Commit**

```bash
git add src/Command/DevContainerGenerateCommand.php tests/Unit/Command/DevContainerGenerateCommandTest.php
git commit -m "feat(devcontainer): add devcontainer:generate command

- Validates seaman.yaml and docker-compose.yml exist
- Prompts before overwriting existing devcontainer
- Creates .devcontainer directory and files
- Provides next steps instructions

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 4: Integrate with InitCommand

**Files:**
- Modify: `src/Command/InitCommand.php:53-157`

**Step 1: Write failing integration test**

Add to existing `tests/Integration/Command/InitCommandTest.php`:

```php
test('init command with --with-devcontainer flag generates devcontainer files', function () {
    $testDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($testDir);
    chdir($testDir);

    // Create minimal composer.json for Symfony detection
    file_put_contents('composer.json', json_encode([
        'require' => ['symfony/framework-bundle' => '^7.0'],
    ]));

    $application = new \Seaman\Application();
    $command = $application->find('init');

    $tester = new \Symfony\Component\Console\Tester\CommandTester($command);
    $tester->setInputs(['yes', 'postgresql', 'y']); // Confirm, select DB, confirm config

    $tester->execute(['--with-devcontainer' => true]);

    expect(file_exists('.devcontainer/devcontainer.json'))->toBeTrue()
        ->and(file_exists('.devcontainer/README.md'))->toBeTrue();

    exec("rm -rf {$testDir}");
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Integration/Command/InitCommandTest.php --filter="with-devcontainer"`
Expected: FAIL with "Unknown option --with-devcontainer"

**Step 3: Add --with-devcontainer option to InitCommand**

Modify `src/Command/InitCommand.php`:

```php
use Symfony\Component\Console\Input\InputOption;
use Seaman\Service\DevContainerGenerator;

class InitCommand extends AbstractSeamanCommand implements Decorable
{
    public function __construct(
        private readonly ServiceRegistry $registry,
        private readonly SymfonyDetector $detector,
        private readonly ProjectBootstrapper $bootstrapper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'with-devcontainer',
            null,
            InputOption::VALUE_NONE,
            'Generate DevContainer configuration for VS Code'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // ... existing code until after generateDockerFiles() call ...

        // Generate Docker files
        $this->generateDockerFiles($config, $projectRoot);

        // Generate DevContainer files if requested
        $shouldGenerateDevContainer = $input->getOption('with-devcontainer')
            || confirm(label: 'Do you want to generate DevContainer configuration for VS Code?', default: false);

        if ($shouldGenerateDevContainer) {
            $this->generateDevContainerFiles($projectRoot);
        }

        Terminal::success('Seaman initialized successfully');

        // ... rest of the method ...
    }

    private function generateDevContainerFiles(string $projectRoot): void
    {
        $templateDir = __DIR__ . '/../Template';
        $renderer = new TemplateRenderer($templateDir);
        $configManager = new ConfigManager($projectRoot, $this->registry);
        $generator = new DevContainerGenerator($renderer, $configManager);

        $generator->generate($projectRoot);

        info('✓ DevContainer configuration created');
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Integration/Command/InitCommandTest.php --filter="with-devcontainer"`
Expected: PASS

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/Command/InitCommand.php`
Expected: No errors at level 10

**Step 6: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/Command/InitCommand.php`
Expected: File formatted correctly

**Step 7: Commit**

```bash
git add src/Command/InitCommand.php tests/Integration/Command/InitCommandTest.php
git commit -m "feat(devcontainer): integrate DevContainer generation with init command

- Add --with-devcontainer option to init command
- Add interactive prompt for devcontainer generation
- Generate devcontainer files after docker-compose generation
- Tests for flag and interactive mode

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 5: Update Application to Register DevContainerGenerateCommand

**Files:**
- Modify: `src/Application.php`

**Step 1: Check current Application structure**

Read `src/Application.php` to understand command registration pattern.

**Step 2: Add DevContainerGenerateCommand to Application**

Modify command registration to include new command:

```php
use Seaman\Command\DevContainerGenerateCommand;

// In the constructor or command registration method
$this->add(new DevContainerGenerateCommand($registry));
```

**Step 3: Verify command is registered**

Run: `php bin/seaman.php list`
Expected: `devcontainer:generate` appears in command list

**Step 4: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/Application.php`
Expected: No errors at level 10

**Step 5: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/Application.php`
Expected: File formatted correctly

**Step 6: Commit**

```bash
git add src/Application.php
git commit -m "feat(devcontainer): register devcontainer:generate command in application

- Add DevContainerGenerateCommand to command registry
- Command available via 'seaman devcontainer:generate'

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 6: Add Integration Tests for Full DevContainer Flow

**Files:**
- Create: `tests/Integration/DevContainerGenerationTest.php`

**Step 1: Write comprehensive integration test**

Create test file:

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Integration;

use Seaman\Application;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir() . '/seaman-devcontainer-test-' . uniqid();
    mkdir($this->testDir);
    chdir($this->testDir);
});

afterEach(function () {
    if (isset($this->testDir) && is_dir($this->testDir)) {
        exec("rm -rf {$this->testDir}");
    }
});

test('devcontainer:generate creates valid JSON configuration', function () {
    // Setup: Create seaman.yaml and docker-compose.yml
    mkdir('.seaman');
    file_put_contents('.seaman/seaman.yaml', <<<YAML
version: "1.0"
php:
  version: "8.4"
  xdebug:
    enabled: true
    ide_key: "VSCODE"
    client_host: "host.docker.internal"
services:
  postgresql:
    enabled: true
    type: "postgresql"
    version: "16"
    port: 5432
  redis:
    enabled: true
    type: "redis"
    version: "7-alpine"
    port: 6379
volumes:
  persist:
    - postgresql
    - redis
YAML
    );

    file_put_contents('docker-compose.yml', 'services: {}');

    // Execute command
    $app = new Application();
    $command = $app->find('devcontainer:generate');
    $tester = new CommandTester($command);
    $tester->execute([]);

    // Verify files created
    expect(file_exists('.devcontainer/devcontainer.json'))->toBeTrue()
        ->and(file_exists('.devcontainer/README.md'))->toBeTrue();

    // Verify JSON is valid
    $json = file_get_contents('.devcontainer/devcontainer.json');
    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray()
        ->and($decoded['name'] ?? null)->not->toBeNull()
        ->and($decoded['dockerComposeFile'])->toBe('../docker-compose.yml')
        ->and($decoded['service'])->toBe('app')
        ->and($decoded['workspaceFolder'])->toBe('/var/www/html');
});

test('devcontainer configuration includes database extensions when database enabled', function () {
    mkdir('.seaman');
    file_put_contents('.seaman/seaman.yaml', <<<YAML
version: "1.0"
php:
  version: "8.4"
services:
  postgresql:
    enabled: true
    type: "postgresql"
volumes:
  persist: []
YAML
    );

    file_put_contents('docker-compose.yml', 'services: {}');

    $app = new Application();
    $command = $app->find('devcontainer:generate');
    $tester = new CommandTester($command);
    $tester->execute([]);

    $json = file_get_contents('.devcontainer/devcontainer.json');
    $decoded = json_decode($json, true);

    $extensions = $decoded['customizations']['vscode']['extensions'] ?? [];

    expect($extensions)->toContain('cweijan.vscode-database-client2');
});

test('devcontainer configuration includes redis extension when redis enabled', function () {
    mkdir('.seaman');
    file_put_contents('.seaman/seaman.yaml', <<<YAML
version: "1.0"
php:
  version: "8.4"
services:
  redis:
    enabled: true
    type: "redis"
volumes:
  persist: []
YAML
    );

    file_put_contents('docker-compose.yml', 'services: {}');

    $app = new Application();
    $command = $app->find('devcontainer:generate');
    $tester = new CommandTester($command);
    $tester->execute([]);

    $json = file_get_contents('.devcontainer/devcontainer.json');
    $decoded = json_decode($json, true);

    $extensions = $decoded['customizations']['vscode']['extensions'] ?? [];

    expect($extensions)->toContain('cisco.redis-xplorer');
});

test('devcontainer README.md is generated with project information', function () {
    mkdir('.seaman');
    file_put_contents('.seaman/seaman.yaml', 'version: "1.0"');
    file_put_contents('docker-compose.yml', 'services: {}');

    $app = new Application();
    $command = $app->find('devcontainer:generate');
    $tester = new CommandTester($command);
    $tester->execute([]);

    $readme = file_get_contents('.devcontainer/README.md');

    expect($readme)->toContain('# DevContainer Configuration')
        ->and($readme)->toContain('What are DevContainers?')
        ->and($readme)->toContain('How to Use');
});
```

**Step 2: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Integration/DevContainerGenerationTest.php`
Expected: ALL PASS

**Step 3: Commit**

```bash
git add tests/Integration/DevContainerGenerationTest.php
git commit -m "test(devcontainer): add comprehensive integration tests

- Test valid JSON generation
- Test extension selection based on services
- Test README.md generation
- Test full end-to-end flow

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 7: Update README.md Documentation

**Files:**
- Modify: `README.md`

**Step 1: Add DevContainers section to README**

Add after "Configuration" section (around line 122):

```markdown
## DevContainers Support

Seaman supports VS Code Dev Containers for a fully configured development environment.

### Enabling DevContainers

**During initialization:**
```bash
seaman init --with-devcontainer
```

**For existing projects:**
```bash
seaman devcontainer:generate
```

### Using DevContainers

1. Open your project in VS Code
2. Click "Reopen in Container" when prompted (or use Command Palette: "Remote-Containers: Reopen in Container")
3. VS Code will start your Docker environment and connect to it
4. All services (database, Redis, etc.) will be available
5. Pre-configured extensions will be installed automatically
6. Start coding with Xdebug, IntelliSense, and all tools ready

### What's Included

- PHP with IntelliSense (Intelephense)
- Xdebug for step debugging
- PHPStan for static analysis
- php-cs-fixer for code formatting
- Service-specific extensions (database clients, Redis explorer, etc.)
- All your configured services from seaman.yaml

### Customization

DevContainer configuration is in `.devcontainer/devcontainer.json`. You can customize:
- VS Code settings
- Additional extensions
- Post-create commands
- Environment variables

See `.devcontainer/README.md` for more details.
```

**Step 2: Add command to Available Commands table**

Add row to "Service Management" section (around line 154):

```markdown
| `seaman devcontainer:generate` | Generate DevContainer configuration for VS Code |
```

**Step 3: Verify markdown formatting**

Run: `cat README.md | grep -A 10 "DevContainers Support"`
Expected: Section displays correctly

**Step 4: Commit**

```bash
git add README.md
git commit -m "docs: add DevContainers support section to README

- Document --with-devcontainer flag
- Document devcontainer:generate command
- Explain usage and benefits
- Add to Available Commands table

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 8: Run Full Quality Checks

**Files:**
- All modified files

**Step 1: Run complete test suite**

Run: `vendor/bin/pest`
Expected: All tests pass (note: pre-existing failures may exist, focus on new tests)

**Step 2: Run PHPStan on all new/modified files**

Run: `vendor/bin/phpstan analyse src/Service/DevContainerGenerator.php src/Command/DevContainerGenerateCommand.php`
Expected: No errors at level 10

**Step 3: Run php-cs-fixer on entire codebase**

Run: `vendor/bin/php-cs-fixer fix`
Expected: All files comply with PER standard

**Step 4: Check test coverage for new files**

Run: `vendor/bin/pest --coverage --min=95`
Expected: Coverage ≥ 95% for new files

**Step 5: Final commit**

```bash
git add -A
git commit -m "chore: run quality checks and fix code style

- PHPStan level 10 passes
- php-cs-fixer applied
- Test coverage ≥ 95%

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 9: Manual Testing and Verification

**Manual Testing Steps:**

1. **Test init with flag:**
   ```bash
   mkdir test-project && cd test-project
   seaman init --with-devcontainer
   # Verify .devcontainer/ created
   ```

2. **Test init interactive:**
   ```bash
   mkdir test-project2 && cd test-project2
   seaman init
   # Answer "yes" to devcontainer prompt
   # Verify .devcontainer/ created
   ```

3. **Test devcontainer:generate:**
   ```bash
   cd existing-seaman-project
   seaman devcontainer:generate
   # Verify files created
   ```

4. **Test in VS Code:**
   ```bash
   code test-project
   # Click "Reopen in Container"
   # Verify all extensions install
   # Verify Xdebug works
   # Verify services accessible
   ```

5. **Test JSON validity:**
   ```bash
   cat .devcontainer/devcontainer.json | jq .
   # Should parse without errors
   ```

**No commit needed for manual testing**

---

## Success Criteria

✅ `DevContainerGenerator` service created with extension selection logic
✅ Twig templates created for devcontainer.json and README.md
✅ `DevContainerGenerateCommand` command works standalone
✅ InitCommand integrated with `--with-devcontainer` flag and interactive prompt
✅ All unit tests pass (95%+ coverage)
✅ All integration tests pass
✅ PHPStan level 10 passes on all new code
✅ php-cs-fixer compliance on all files
✅ README.md updated with DevContainers documentation
✅ Manual testing in VS Code successful

## Notes

- Focus on new tests passing; pre-existing test failures are tracked separately
- Extension selection is dynamic based on enabled services
- Templates use existing TemplateRenderer service pattern
- Configuration reuses existing seaman.yaml structure
- DevContainer references docker-compose.yml (no duplication)
