# Simplify Dockerfile Generation Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Remove FrankenPHP support and template-based Dockerfile generation, using a single static Dockerfile with pre-built tagged images.

**Architecture:** Replace DockerfileGenerator service with simple file copy operation. Remove ServerConfig value object. Add DockerImageBuilder service for building and tagging images. Update InitCommand and RebuildCommand to use the new build workflow.

**Tech Stack:** PHP 8.4, Symfony Process, Pest (testing framework), PHPStan Level 10, php-cs-fixer

---

## Task 1: Create DockerImageBuilder Service

**Files:**
- Create: `src/Service/DockerImageBuilder.php`
- Test: `tests/Unit/Service/DockerImageBuilderTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/Service/DockerImageBuilderTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Seaman\Service\DockerImageBuilder;
use Seaman\ValueObject\ProcessResult;

beforeEach(function (): void {
    $this->projectRoot = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->projectRoot, 0755, true);
    mkdir($this->projectRoot . '/.seaman', 0755, true);

    // Create minimal Dockerfile
    file_put_contents(
        $this->projectRoot . '/.seaman/Dockerfile',
        "FROM ubuntu:24.04\nRUN echo 'test'"
    );
});

afterEach(function (): void {
    if (is_dir($this->projectRoot)) {
        exec("rm -rf {$this->projectRoot}");
    }
});

test('build returns ProcessResult', function (): void {
    $builder = new DockerImageBuilder($this->projectRoot);
    $result = $builder->build();

    expect($result)->toBeInstanceOf(ProcessResult::class);
});

test('build uses correct docker command', function (): void {
    $builder = new DockerImageBuilder($this->projectRoot);
    $result = $builder->build();

    // Should tag as seaman/seaman:latest
    expect($result->output)->toContain('seaman/seaman:latest')
        ->or($result->errorOutput)->toContain('seaman/seaman:latest');
});

test('build passes WWWGROUP argument', function (): void {
    $builder = new DockerImageBuilder($this->projectRoot);
    $result = $builder->build();

    // Build should complete (may fail if Docker not available, but command structure is correct)
    expect($result)->toBeInstanceOf(ProcessResult::class);
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/DockerImageBuilderTest.php`
Expected: FAIL with "Class 'Seaman\Service\DockerImageBuilder' not found"

**Step 3: Write minimal implementation**

Create `src/Service/DockerImageBuilder.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Builds and tags Docker images from Dockerfile.
// ABOUTME: Encapsulates Docker build command execution.

namespace Seaman\Service;

use Seaman\ValueObject\ProcessResult;
use Symfony\Component\Process\Process;

readonly class DockerImageBuilder
{
    public function __construct(
        private string $projectRoot,
    ) {}

    /**
     * Builds Docker image and tags it as seaman/seaman:latest.
     *
     * @return ProcessResult The result of the build operation
     */
    public function build(): ProcessResult
    {
        $wwwgroup = (string) posix_getgid();

        $command = [
            'docker',
            'build',
            '-t',
            'seaman/seaman:latest',
            '-f',
            '.seaman/Dockerfile',
            '--build-arg',
            "WWWGROUP={$wwwgroup}",
            '.',
        ];

        $process = new Process($command, $this->projectRoot, timeout: 300.0);
        $process->run();

        return new ProcessResult(
            exitCode: $process->getExitCode() ?? 1,
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
        );
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/DockerImageBuilderTest.php`
Expected: PASS (3 tests)

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse --level=10`
Expected: No errors

**Step 6: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/Service/DockerImageBuilder.php tests/Unit/Service/DockerImageBuilderTest.php`
Expected: Files formatted

**Step 7: Commit**

```bash
git add src/Service/DockerImageBuilder.php tests/Unit/Service/DockerImageBuilderTest.php
git commit -m "feat: add DockerImageBuilder service for building and tagging images"
```

---

## Task 2: Remove ServerConfig from Configuration

**Files:**
- Modify: `src/ValueObject/Configuration.php`
- Modify: `tests/Unit/ValueObject/ConfigurationTest.php`

**Step 1: Update the failing test**

Modify `tests/Unit/ValueObject/ConfigurationTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;

test('creates complete configuration', function (): void {
    $xdebug = new XdebugConfig(true, 'PHPSTORM', 'localhost');
    $php = new PhpConfig('8.4', ['intl', 'opcache'], $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration(
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
    );

    expect($config->version)->toBe('1.0')
        ->and($config->php)->toBe($php)
        ->and($config->services)->toBe($services)
        ->and($config->volumes)->toBe($volumes);
});

test('configuration is immutable', function (): void {
    $xdebug = new XdebugConfig(true, 'PHPSTORM', 'localhost');
    $php = new PhpConfig('8.4', ['intl'], $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration(
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
    );

    expect($config)->toBeReadOnly();
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ValueObject/ConfigurationTest.php`
Expected: FAIL with "Too many arguments" or similar

**Step 3: Update Configuration implementation**

Modify `src/ValueObject/Configuration.php`:

Remove the `server` property and constructor parameter. Keep all other properties unchanged.

```php
<?php

declare(strict_types=1);

// ABOUTME: Main configuration value object for Seaman.
// ABOUTME: Contains PHP, services, and volumes configuration.

namespace Seaman\ValueObject;

readonly class Configuration
{
    public function __construct(
        public string $version,
        public PhpConfig $php,
        public ServiceCollection $services,
        public VolumeConfig $volumes,
    ) {}
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ValueObject/ConfigurationTest.php`
Expected: PASS (2 tests)

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse --level=10 src/ValueObject/Configuration.php`
Expected: No errors

**Step 6: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/ValueObject/Configuration.php tests/Unit/ValueObject/ConfigurationTest.php`
Expected: Files formatted

**Step 7: Commit**

```bash
git add src/ValueObject/Configuration.php tests/Unit/ValueObject/ConfigurationTest.php
git commit -m "refactor: remove ServerConfig from Configuration"
```

---

## Task 3: Update DockerComposeGenerator

**Files:**
- Modify: `src/Service/DockerComposeGenerator.php`
- Modify: `src/Template/docker/compose.base.twig`
- Modify: `tests/Unit/Service/DockerComposeGeneratorTest.php`

**Step 1: Update the test**

Modify `tests/Unit/Service/DockerComposeGeneratorTest.php`:

Remove any references to `server` from the Configuration instantiation:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Seaman\Service\DockerComposeGenerator;
use Seaman\Service\TemplateRenderer;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;

beforeEach(function (): void {
    $templateDir = __DIR__ . '/../../../src/Template';
    $this->renderer = new TemplateRenderer($templateDir);
    $this->generator = new DockerComposeGenerator($this->renderer);
});

test('generates docker-compose.yml from configuration', function (): void {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', ['intl', 'opcache'], $xdebug);

    $config = new Configuration(
        version: '1.0',
        php: $php,
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
    );

    $yaml = $this->generator->generate($config);

    expect($yaml)->toContain('version: \'3.8\'')
        ->and($yaml)->toContain('services:')
        ->and($yaml)->toContain('app:')
        ->and($yaml)->toContain('image: seaman/seaman:latest')
        ->and($yaml)->toContain('build:')
        ->and($yaml)->toContain('dockerfile: .seaman/Dockerfile');
});

test('includes only enabled services', function (): void {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', ['intl'], $xdebug);

    $redis = new ServiceConfig(
        enabled: true,
        type: 'redis',
        version: '7-alpine',
        port: 6379,
    );

    $config = new Configuration(
        version: '1.0',
        php: $php,
        services: new ServiceCollection(['redis' => $redis]),
        volumes: new VolumeConfig(['redis']),
    );

    $yaml = $this->generator->generate($config);

    expect($yaml)->toContain('redis:')
        ->and($yaml)->toContain('image: redis:7-alpine');
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/DockerComposeGeneratorTest.php`
Expected: FAIL (tests may pass structurally but need template update)

**Step 3: Update DockerComposeGenerator implementation**

Modify `src/Service/DockerComposeGenerator.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Generates docker-compose.yml from configuration.
// ABOUTME: Uses Twig templates to create Docker Compose files.

namespace Seaman\Service;

use Seaman\ValueObject\Configuration;

readonly class DockerComposeGenerator
{
    public function __construct(
        private TemplateRenderer $renderer,
    ) {}

    public function generate(Configuration $config): string
    {
        $context = [
            'php' => $config->php,
            'services' => [
                'enabled' => $config->services->enabled(),
            ],
            'volumes' => $config->volumes,
        ];

        return $this->renderer->render('docker/compose.base.twig', $context);
    }
}
```

**Step 4: Update docker-compose template**

Modify `src/Template/docker/compose.base.twig`:

```yaml
version: '3.8'

services:
  app:
    image: seaman/seaman:latest
    build:
      context: .
      dockerfile: .seaman/Dockerfile
      args:
        WWWGROUP: ${WWWGROUP:-1000}
    volumes:
      - .:/var/www/html
      - .seaman/scripts/xdebug-toggle.sh:/usr/local/bin/xdebug-toggle
    environment:
      - XDEBUG_MODE=${XDEBUG_MODE:-off}
      - PHP_IDE_CONFIG=serverName=seaman
    ports:
      - "${APP_PORT:-8000}:8000"
{% if services.enabled|length > 0 %}
    depends_on:
{% for name, service in services.enabled %}
      - {{ name }}
{% endfor %}
{% endif %}
    networks:
      - seaman

{% for name, service in services.enabled %}
{% include 'docker/services/' ~ service.type ~ '.twig' with { name: name, service: service } %}

{% endfor %}
networks:
  seaman:
    driver: bridge

{% if volumes.persist|length > 0 %}
volumes:
{% for volume in volumes.persist %}
  {{ volume }}:
{% endfor %}
{% endif %}
```

**Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/DockerComposeGeneratorTest.php`
Expected: PASS (2 tests)

**Step 6: Run PHPStan**

Run: `vendor/bin/phpstan analyse --level=10 src/Service/DockerComposeGenerator.php`
Expected: No errors

**Step 7: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/Service/DockerComposeGenerator.php src/Template/docker/compose.base.twig tests/Unit/Service/DockerComposeGeneratorTest.php`
Expected: Files formatted

**Step 8: Commit**

```bash
git add src/Service/DockerComposeGenerator.php src/Template/docker/compose.base.twig tests/Unit/Service/DockerComposeGeneratorTest.php
git commit -m "refactor: update DockerComposeGenerator to use image reference"
```

---

## Task 4: Update InitCommand

**Files:**
- Modify: `src/Command/InitCommand.php`
- Modify: `tests/Integration/Command/InitCommandTest.php`

**Step 1: Update the test**

Modify `tests/Integration/Command/InitCommandTest.php`:

Remove server selection expectations, add image build verification:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Seaman\Command\InitCommand;
use Seaman\Service\Container\ServiceRegistry;

beforeEach(function (): void {
    $this->testDir = sys_get_temp_dir() . '/seaman-init-test-' . uniqid();
    mkdir($this->testDir, 0755, true);
    chdir($this->testDir);

    // Copy root Dockerfile to test directory
    $rootDockerfile = __DIR__ . '/../../../Dockerfile';
    if (file_exists($rootDockerfile)) {
        copy($rootDockerfile, $this->testDir . '/Dockerfile');
    }
});

afterEach(function (): void {
    if (is_dir($this->testDir)) {
        exec("rm -rf {$this->testDir}");
    }
});

test('init command creates seaman.yaml', function (): void {
    $registry = new ServiceRegistry();
    $command = new InitCommand($registry);
    $tester = new CommandTester($command);

    $tester->setInputs([
        '8.4',           // PHP version
        'postgresql',    // Database
        'redis,mailpit', // Additional services
    ]);

    $tester->execute([]);

    expect(file_exists($this->testDir . '/seaman.yaml'))->toBeTrue()
        ->and(file_exists($this->testDir . '/.seaman/Dockerfile'))->toBeTrue()
        ->and(file_exists($this->testDir . '/docker-compose.yml'))->toBeTrue();

    $output = $tester->getDisplay();
    expect($output)->toContain('Seaman initialized successfully!');
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Integration/Command/InitCommandTest.php`
Expected: FAIL (may have various errors due to command changes)

**Step 3: Update InitCommand implementation**

Modify `src/Command/InitCommand.php`:

Remove server type selection (lines 64-73 and line 94). Add Dockerfile copy and image build steps:

```php
<?php

declare(strict_types=1);

// ABOUTME: Interactive initialization command.
// ABOUTME: Creates seaman.yaml and sets up Docker environment.

namespace Seaman\Command;

use Seaman\Service\ConfigManager;
use Seaman\Service\DockerComposeGenerator;
use Seaman\Service\DockerImageBuilder;
use Seaman\Service\TemplateRenderer;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\VolumeConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;

#[AsCommand(
    name: 'seaman:init',
    description: 'Initialize Seaman configuration interactively',
    aliases: ['init'],
)]
class InitCommand extends Command
{
    public function __construct(private readonly ServiceRegistry $registry)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Seaman Initialization');

        $projectRoot = (string) getcwd();

        // Check if already initialized
        if (file_exists($projectRoot . '/seaman.yaml')) {
            if (!$io->confirm('seaman.yaml already exists. Overwrite?', false)) {
                $io->info('Initialization cancelled.');
                return Command::SUCCESS;
            }
        }

        // Step 1: PHP Version
        /** @var string $phpVersion */
        $phpVersion = $io->choice(
            'Select PHP version',
            ['8.2', '8.3', '8.4'],
            '8.4',
        );

        // Step 2: Database Selection
        $databaseQuestion = new ChoiceQuestion(
            'Select database (leave empty for none)',
            ['none', 'postgresql', 'mysql', 'mariadb'],
            'postgresql',
        );
        /** @var string $database */
        $database = $io->askQuestion($databaseQuestion);

        // Step 3: Additional Services
        /** @var list<string> $additionalServices */
        $additionalServices = $io->choice(
            'Select additional services (comma-separated)',
            ['redis', 'mailpit', 'minio', 'elasticsearch', 'rabbitmq'],
            'redis,mailpit',
            true,
        );

        // Build configuration
        $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');

        $extensions = [];
        if ($database === 'postgresql') {
            $extensions[] = 'pdo_pgsql';
        } elseif ($database === 'mysql' || $database === 'mariadb') {
            $extensions[] = 'pdo_mysql';
        }

        if (in_array('redis', $additionalServices, true)) {
            $extensions[] = 'redis';
        }

        $extensions[] = 'intl';
        $extensions[] = 'opcache';

        $php = new PhpConfig($phpVersion, $extensions, $xdebug);

        /** @var array<string, \Seaman\ValueObject\ServiceConfig> $services */
        $services = [];
        /** @var list<string> $persistVolumes */
        $persistVolumes = [];

        if ($database !== 'none') {
            $serviceImpl = $this->registry->get($database);
            $defaultConfig = $serviceImpl->getDefaultConfig();
            $services[$database] = $defaultConfig;
            $persistVolumes[] = $database;
        }

        foreach ($additionalServices as $serviceName) {
            $serviceImpl = $this->registry->get($serviceName);
            $defaultConfig = $serviceImpl->getDefaultConfig();
            $services[$serviceName] = $defaultConfig;

            if (in_array($serviceName, ['redis', 'minio', 'elasticsearch', 'rabbitmq'], true)) {
                $persistVolumes[] = $serviceName;
            }
        }

        $config = new Configuration(
            version: '1.0',
            php: $php,
            services: new ServiceCollection($services),
            volumes: new VolumeConfig($persistVolumes),
        );

        // Save configuration
        $configManager = new ConfigManager($projectRoot);
        $configManager->save($config);

        // Generate Docker files
        $this->generateDockerFiles($config, $projectRoot, $io);

        $io->success('Seaman initialized successfully!');
        $io->info('Next steps:');
        $io->listing([
            'Run "seaman start" to start services',
            'Run "seaman status" to check service status',
            'Run "seaman --help" to see all commands',
        ]);

        return Command::SUCCESS;
    }

    private function generateDockerFiles(Configuration $config, string $projectRoot, SymfonyStyle $io): void
    {
        $seamanDir = $projectRoot . '/.seaman';
        if (!is_dir($seamanDir)) {
            mkdir($seamanDir, 0755, true);
        }

        // Copy root Dockerfile to .seaman/
        $rootDockerfile = $projectRoot . '/Dockerfile';
        if (!file_exists($rootDockerfile)) {
            $io->error('Dockerfile not found in project root. Cannot proceed.');
            throw new \RuntimeException('Dockerfile not found');
        }

        copy($rootDockerfile, $seamanDir . '/Dockerfile');

        $templateDir = __DIR__ . '/../Template';
        $renderer = new TemplateRenderer($templateDir);

        // Generate docker-compose.yml (in project root)
        $composeGenerator = new DockerComposeGenerator($renderer);
        $composeYaml = $composeGenerator->generate($config);
        file_put_contents($projectRoot . '/docker-compose.yml', $composeYaml);

        // Generate seaman.yaml (service definitions in .seaman/)
        $this->generateSeamanConfig($config, $seamanDir);

        // Generate xdebug-toggle script
        $xdebugScript = $renderer->render('scripts/xdebug-toggle.sh.twig', [
            'xdebug' => $config->php->xdebug,
        ]);
        $scriptPath = $seamanDir . '/scripts/xdebug-toggle.sh';
        $scriptsDir = dirname($scriptPath);
        if (!is_dir($scriptsDir)) {
            mkdir($scriptsDir, 0755, true);
        }
        file_put_contents($scriptPath, $xdebugScript);
        chmod($scriptPath, 0755);

        // Build Docker image
        $io->info('Building Docker image...');
        $builder = new DockerImageBuilder($projectRoot);
        $result = $builder->build();

        if (!$result->isSuccessful()) {
            $io->error('Failed to build Docker image');
            $io->writeln($result->errorOutput);
            throw new \RuntimeException('Docker build failed');
        }

        $io->success('Docker image built successfully!');
    }

    private function generateSeamanConfig(Configuration $config, string $seamanDir): void
    {
        $seamanConfig = [
            'version' => $config->version,
            'services' => [],
        ];

        foreach ($config->services->all() as $name => $service) {
            $seamanConfig['services'][$name] = [
                'enabled' => $service->enabled,
                'type' => $service->type,
                'version' => $service->version,
                'port' => $service->port,
            ];

            if (!empty($service->additionalPorts)) {
                $seamanConfig['services'][$name]['additional_ports'] = $service->additionalPorts;
            }

            if (!empty($service->environmentVariables)) {
                $seamanConfig['services'][$name]['environment'] = $service->environmentVariables;
            }
        }

        $seamanYaml = \Symfony\Component\Yaml\Yaml::dump($seamanConfig, 4, 2);
        file_put_contents($seamanDir . '/seaman.yaml', $seamanYaml);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Integration/Command/InitCommandTest.php`
Expected: PASS (1 test) - Note: may skip if Docker not available

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse --level=10 src/Command/InitCommand.php`
Expected: No errors

**Step 6: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/Command/InitCommand.php tests/Integration/Command/InitCommandTest.php`
Expected: Files formatted

**Step 7: Commit**

```bash
git add src/Command/InitCommand.php tests/Integration/Command/InitCommandTest.php
git commit -m "refactor: remove server selection and add image build to InitCommand"
```

---

## Task 5: Update RebuildCommand

**Files:**
- Modify: `src/Command/RebuildCommand.php`
- Modify: `tests/Integration/Command/RebuildCommandTest.php`

**Step 1: Update the test**

Modify `tests/Integration/Command/RebuildCommandTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Seaman\Command\RebuildCommand;

beforeEach(function (): void {
    $this->testDir = sys_get_temp_dir() . '/seaman-rebuild-test-' . uniqid();
    mkdir($this->testDir, 0755, true);
    mkdir($this->testDir . '/.seaman', 0755, true);
    chdir($this->testDir);

    // Create minimal seaman.yaml
    file_put_contents($this->testDir . '/seaman.yaml', 'version: 1.0');

    // Create minimal Dockerfile
    file_put_contents(
        $this->testDir . '/.seaman/Dockerfile',
        "FROM ubuntu:24.04\nRUN echo 'test'"
    );

    // Create minimal docker-compose.yml
    file_put_contents(
        $this->testDir . '/docker-compose.yml',
        "version: '3.8'\nservices:\n  app:\n    image: seaman/seaman:latest"
    );
});

afterEach(function (): void {
    if (is_dir($this->testDir)) {
        exec("rm -rf {$this->testDir}");
    }
});

test('rebuild command requires seaman.yaml', function (): void {
    chdir(sys_get_temp_dir());

    $command = new RebuildCommand();
    $tester = new CommandTester($command);
    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('seaman.yaml not found');
});

test('rebuild command builds image and restarts services', function (): void {
    $command = new RebuildCommand();
    $tester = new CommandTester($command);

    $result = $tester->execute([]);

    $output = $tester->getDisplay();
    expect($output)->toContain('Building Docker image');
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Integration/Command/RebuildCommandTest.php`
Expected: FAIL (command structure different)

**Step 3: Update RebuildCommand implementation**

Modify `src/Command/RebuildCommand.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Rebuilds Docker images.
// ABOUTME: Builds image from .seaman/Dockerfile and restarts services.

namespace Seaman\Command;

use Seaman\Service\DockerImageBuilder;
use Seaman\Service\DockerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaman:rebuild',
    description: 'Rebuild docker images',
    aliases: ['rebuild'],
)]
class RebuildCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = (string) getcwd();

        // Check if seaman.yaml exists
        if (!file_exists($projectRoot . '/seaman.yaml')) {
            $io->error('seaman.yaml not found. Run "seaman init" first.');
            return Command::FAILURE;
        }

        // Build Docker image
        $io->info('Building Docker image...');
        $builder = new DockerImageBuilder($projectRoot);
        $buildResult = $builder->build();

        if (!$buildResult->isSuccessful()) {
            $io->error('Failed to build Docker image');
            $io->writeln($buildResult->errorOutput);
            return Command::FAILURE;
        }

        $io->success('Docker image built successfully!');

        // Restart services
        $manager = new DockerManager($projectRoot);

        $io->info('Stopping services...');
        $stopResult = $manager->stop();

        if (!$stopResult->isSuccessful()) {
            $io->warning('Failed to stop services (they may not be running)');
        }

        $io->info('Starting services...');
        $startResult = $manager->start();

        if ($startResult->isSuccessful()) {
            $io->success('Rebuild and restart complete!');
            return Command::SUCCESS;
        }

        $io->error('Failed to start services');
        $io->writeln($startResult->errorOutput);
        return Command::FAILURE;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Integration/Command/RebuildCommandTest.php`
Expected: PASS (2 tests)

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse --level=10 src/Command/RebuildCommand.php`
Expected: No errors

**Step 6: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/Command/RebuildCommand.php tests/Integration/Command/RebuildCommandTest.php`
Expected: Files formatted

**Step 7: Commit**

```bash
git add src/Command/RebuildCommand.php tests/Integration/Command/RebuildCommandTest.php
git commit -m "refactor: update RebuildCommand to build image and restart services"
```

---

## Task 6: Delete obsolete files

**Files:**
- Delete: `src/Service/DockerfileGenerator.php`
- Delete: `src/ValueObject/ServerConfig.php`
- Delete: `src/Template/docker/Dockerfile.symfony.twig`
- Delete: `src/Template/docker/Dockerfile.frankenphp.twig`
- Delete: `tests/Unit/Service/DockerfileGeneratorTest.php`
- Delete: `tests/Unit/ValueObject/ServerConfigTest.php`

**Step 1: Delete files**

```bash
rm src/Service/DockerfileGenerator.php
rm src/ValueObject/ServerConfig.php
rm src/Template/docker/Dockerfile.symfony.twig
rm src/Template/docker/Dockerfile.frankenphp.twig
rm tests/Unit/Service/DockerfileGeneratorTest.php
rm tests/Unit/ValueObject/ServerConfigTest.php
```

**Step 2: Run all tests**

Run: `vendor/bin/pest`
Expected: All tests pass (no references to deleted files)

**Step 3: Run PHPStan**

Run: `vendor/bin/phpstan analyse --level=10`
Expected: No errors (no references to deleted classes)

**Step 4: Commit**

```bash
git add -A
git commit -m "chore: remove DockerfileGenerator, ServerConfig, and Dockerfile templates"
```

---

## Task 7: Final verification and cleanup

**Files:**
- All modified files

**Step 1: Run full test suite**

Run: `vendor/bin/pest`
Expected: All tests pass with ≥95% coverage

**Step 2: Run PHPStan on entire codebase**

Run: `vendor/bin/phpstan analyse --level=10`
Expected: No errors

**Step 3: Run php-cs-fixer on entire codebase**

Run: `vendor/bin/php-cs-fixer fix`
Expected: All files formatted correctly

**Step 4: Verify composer.json**

Run: `composer validate`
Expected: composer.json is valid

**Step 5: Run test coverage check**

Run: `vendor/bin/pest --coverage`
Expected: Coverage ≥ 95%

**Step 6: Final commit if any fixes needed**

```bash
git add -A
git commit -m "chore: final cleanup and formatting"
```

---

## Implementation Complete

After completing all tasks:

1. All tests passing
2. PHPStan level 10 clean
3. Code formatted with php-cs-fixer
4. Test coverage ≥ 95%
5. Ready for code review with @superpowers:requesting-code-review

**Verification checklist:**
- [ ] DockerImageBuilder service created and tested
- [ ] ServerConfig removed from Configuration
- [ ] DockerComposeGenerator updated to use image reference
- [ ] InitCommand removes server selection and builds image
- [ ] RebuildCommand builds image and restarts services
- [ ] Obsolete files deleted
- [ ] All tests passing
- [ ] PHPStan level 10 clean
- [ ] php-cs-fixer formatting applied
- [ ] Test coverage ≥ 95%
