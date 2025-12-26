# Interactive Service Configuration Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement `seaman configure <service>` command that allows interactive configuration of services through their plugin's ConfigSchema.

**Architecture:** Extend existing ConfigSchema with UI metadata (label, description, secret). ConfigurationService orchestrates loading, rendering with Laravel Prompts, and saving to seaman.yaml. After saving, .env regenerates automatically and user can optionally restart the service or stack.

**Tech Stack:** PHP 8.4, Laravel Prompts, Symfony Console

---

## Design Decisions

1. **What to configure**: Full plugin ConfigSchema (not just basics like version/port)
2. **Where to persist**: Only seaman.yaml - .env regenerates automatically from it
3. **How to expose fields**: Extend existing ConfigSchema with UI metadata methods

## ConfigSchema Extensions

Each field type (StringField, IntegerField, BooleanField) gets these fluent methods:

```php
->label(string $label): self      // Display name
->description(string $desc): self  // Hint text
->secret(): self                   // For passwords (StringField only)
```

Example usage in plugin:
```php
ConfigSchema::create()
    ->string('version', default: '8.0')
        ->label('MySQL Version')
        ->description('Docker image tag to use')
    ->integer('port', default: 3306, min: 1, max: 65535)
        ->label('External Port')
    ->string('root_password', default: 'seaman')
        ->label('Root Password')
        ->secret();
```

## Laravel Prompts Mapping

| ConfigSchema Field | Laravel Prompt |
|-------------------|----------------|
| `StringField` | `text()` |
| `StringField->secret()` | `password()` |
| `StringField->enum([...])` | `select()` |
| `IntegerField` | `text()` with numeric validation |
| `BooleanField` | `confirm()` |

## Command Flow

```
$ seaman configure mysql

[Interactive form with Laravel Prompts for each field]

Configuration saved. What would you like to do?
> Nothing - I'll restart manually
  Restart only mysql
  Restart entire stack
```

## seaman.yaml Structure

```yaml
services:
  mysql:
    enabled: true
    config:
      version: "8.0"
      port: 3306
      root_password: "my_secure_password"
```

---

## Tasks

### Task 1: Create FieldMetadata Value Object

**Files:**
- Create: `src/Plugin/Config/FieldMetadata.php`
- Test: `tests/Unit/Plugin/Config/FieldMetadataTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Unit tests for FieldMetadata value object.
// ABOUTME: Validates metadata storage for configuration fields.

namespace Seaman\Tests\Unit\Plugin\Config;

use Seaman\Plugin\Config\FieldMetadata;

test('FieldMetadata stores label', function () {
    $metadata = new FieldMetadata(label: 'MySQL Version');

    expect($metadata->label)->toBe('MySQL Version');
});

test('FieldMetadata stores description', function () {
    $metadata = new FieldMetadata(
        label: 'Port',
        description: 'External port to expose',
    );

    expect($metadata->description)->toBe('External port to expose');
});

test('FieldMetadata defaults description to empty string', function () {
    $metadata = new FieldMetadata(label: 'Port');

    expect($metadata->description)->toBe('');
});

test('FieldMetadata stores secret flag', function () {
    $metadata = new FieldMetadata(
        label: 'Password',
        isSecret: true,
    );

    expect($metadata->isSecret)->toBeTrue();
});

test('FieldMetadata defaults secret to false', function () {
    $metadata = new FieldMetadata(label: 'Username');

    expect($metadata->isSecret)->toBeFalse();
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Config/FieldMetadataTest.php`
Expected: FAIL - class not found

**Step 3: Write minimal implementation**

```php
<?php

// ABOUTME: Value object storing UI metadata for configuration fields.
// ABOUTME: Holds label, description, and secret flag for form rendering.

declare(strict_types=1);

namespace Seaman\Plugin\Config;

final readonly class FieldMetadata
{
    public function __construct(
        public string $label,
        public string $description = '',
        public bool $isSecret = false,
    ) {}
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Config/FieldMetadataTest.php`
Expected: PASS

**Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Plugin/Config/FieldMetadata.php`
Expected: No errors

**Step 6: Commit**

```bash
git add src/Plugin/Config/FieldMetadata.php tests/Unit/Plugin/Config/FieldMetadataTest.php
git commit -m "feat(config): add FieldMetadata value object for UI metadata"
```

---

### Task 2: Add Metadata Methods to StringField

**Files:**
- Modify: `src/Plugin/Config/StringField.php`
- Test: `tests/Unit/Plugin/Config/StringFieldTest.php`

**Step 1: Write the failing tests**

Add to existing test file:

```php
test('StringField supports label method', function () {
    $schema = ConfigSchema::create()
        ->string('version', default: '8.0')
            ->label('MySQL Version');

    $fields = $schema->getFields();
    expect($fields['version']->getMetadata()->label)->toBe('MySQL Version');
});

test('StringField supports description method', function () {
    $schema = ConfigSchema::create()
        ->string('version', default: '8.0')
            ->description('Docker image tag');

    $fields = $schema->getFields();
    expect($fields['version']->getMetadata()->description)->toBe('Docker image tag');
});

test('StringField supports secret method', function () {
    $schema = ConfigSchema::create()
        ->string('password', default: 'secret')
            ->secret();

    $fields = $schema->getFields();
    expect($fields['password']->getMetadata()->isSecret)->toBeTrue();
});

test('StringField generates label from field name if not set', function () {
    $schema = ConfigSchema::create()
        ->string('root_password', default: 'secret');

    $fields = $schema->getFields();
    expect($fields['root_password']->getMetadata()->label)->toBe('Root Password');
});

test('StringField chains metadata methods fluently', function () {
    $schema = ConfigSchema::create()
        ->string('password', default: 'secret')
            ->label('Root Password')
            ->description('Database root password')
            ->secret();

    $fields = $schema->getFields();
    $metadata = $fields['password']->getMetadata();

    expect($metadata->label)->toBe('Root Password')
        ->and($metadata->description)->toBe('Database root password')
        ->and($metadata->isSecret)->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Config/StringFieldTest.php --filter="label|description|secret"`
Expected: FAIL - method not found

**Step 3: Implement metadata methods in StringField**

Add to StringField class:

```php
private string $label = '';
private string $description = '';
private bool $isSecret = false;

public function label(string $label): self
{
    $this->label = $label;
    return $this;
}

public function description(string $description): self
{
    $this->description = $description;
    return $this;
}

public function secret(): self
{
    $this->isSecret = true;
    return $this;
}

public function getMetadata(): FieldMetadata
{
    $label = $this->label !== ''
        ? $this->label
        : $this->generateLabelFromName();

    return new FieldMetadata(
        label: $label,
        description: $this->description,
        isSecret: $this->isSecret,
    );
}

private function generateLabelFromName(): string
{
    // Convert snake_case to Title Case
    return ucwords(str_replace('_', ' ', $this->name));
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Config/StringFieldTest.php`
Expected: PASS

**Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Plugin/Config/StringField.php`
Expected: No errors

**Step 6: Commit**

```bash
git add src/Plugin/Config/StringField.php tests/Unit/Plugin/Config/StringFieldTest.php
git commit -m "feat(config): add metadata methods to StringField"
```

---

### Task 3: Add Metadata Methods to IntegerField

**Files:**
- Modify: `src/Plugin/Config/IntegerField.php`
- Test: `tests/Unit/Plugin/Config/IntegerFieldTest.php`

**Step 1: Write the failing tests**

Add to existing test file:

```php
test('IntegerField supports label method', function () {
    $schema = ConfigSchema::create()
        ->integer('port', default: 3306)
            ->label('External Port');

    $fields = $schema->getFields();
    expect($fields['port']->getMetadata()->label)->toBe('External Port');
});

test('IntegerField supports description method', function () {
    $schema = ConfigSchema::create()
        ->integer('port', default: 3306)
            ->description('Port to expose on host');

    $fields = $schema->getFields();
    expect($fields['port']->getMetadata()->description)->toBe('Port to expose on host');
});

test('IntegerField generates label from field name if not set', function () {
    $schema = ConfigSchema::create()
        ->integer('max_connections', default: 100);

    $fields = $schema->getFields();
    expect($fields['max_connections']->getMetadata()->label)->toBe('Max Connections');
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Config/IntegerFieldTest.php --filter="label|description"`
Expected: FAIL - method not found

**Step 3: Implement metadata methods in IntegerField**

Add same pattern as StringField (without secret):

```php
private string $label = '';
private string $description = '';

public function label(string $label): self
{
    $this->label = $label;
    return $this;
}

public function description(string $description): self
{
    $this->description = $description;
    return $this;
}

public function getMetadata(): FieldMetadata
{
    $label = $this->label !== ''
        ? $this->label
        : $this->generateLabelFromName();

    return new FieldMetadata(
        label: $label,
        description: $this->description,
        isSecret: false,
    );
}

private function generateLabelFromName(): string
{
    return ucwords(str_replace('_', ' ', $this->name));
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Config/IntegerFieldTest.php`
Expected: PASS

**Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Plugin/Config/IntegerField.php`
Expected: No errors

**Step 6: Commit**

```bash
git add src/Plugin/Config/IntegerField.php tests/Unit/Plugin/Config/IntegerFieldTest.php
git commit -m "feat(config): add metadata methods to IntegerField"
```

---

### Task 4: Add Metadata Methods to BooleanField

**Files:**
- Modify: `src/Plugin/Config/BooleanField.php`
- Test: `tests/Unit/Plugin/Config/BooleanFieldTest.php`

**Step 1: Write the failing tests**

Add to existing test file:

```php
test('BooleanField supports label method', function () {
    $schema = ConfigSchema::create()
        ->boolean('enabled', default: true)
            ->label('Enable Service');

    $fields = $schema->getFields();
    expect($fields['enabled']->getMetadata()->label)->toBe('Enable Service');
});

test('BooleanField supports description method', function () {
    $schema = ConfigSchema::create()
        ->boolean('metrics', default: false)
            ->description('Enable Prometheus metrics');

    $fields = $schema->getFields();
    expect($fields['metrics']->getMetadata()->description)->toBe('Enable Prometheus metrics');
});

test('BooleanField generates label from field name if not set', function () {
    $schema = ConfigSchema::create()
        ->boolean('enable_ssl', default: false);

    $fields = $schema->getFields();
    expect($fields['enable_ssl']->getMetadata()->label)->toBe('Enable Ssl');
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Config/BooleanFieldTest.php --filter="label|description"`
Expected: FAIL - method not found

**Step 3: Implement metadata methods in BooleanField**

Same pattern as IntegerField:

```php
private string $label = '';
private string $description = '';

public function label(string $label): self
{
    $this->label = $label;
    return $this;
}

public function description(string $description): self
{
    $this->description = $description;
    return $this;
}

public function getMetadata(): FieldMetadata
{
    $label = $this->label !== ''
        ? $this->label
        : $this->generateLabelFromName();

    return new FieldMetadata(
        label: $label,
        description: $this->description,
        isSecret: false,
    );
}

private function generateLabelFromName(): string
{
    return ucwords(str_replace('_', ' ', $this->name));
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Config/BooleanFieldTest.php`
Expected: PASS

**Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Plugin/Config/BooleanField.php`
Expected: No errors

**Step 6: Commit**

```bash
git add src/Plugin/Config/BooleanField.php tests/Unit/Plugin/Config/BooleanFieldTest.php
git commit -m "feat(config): add metadata methods to BooleanField"
```

---

### Task 5: Create ConfigurationService

**Files:**
- Create: `src/Service/ConfigurationService.php`
- Test: `tests/Unit/Service/ConfigurationServiceTest.php`

**Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

// ABOUTME: Unit tests for ConfigurationService.
// ABOUTME: Validates service configuration loading and saving.

namespace Seaman\Tests\Unit\Service;

use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Service\ConfigurationService;
use Seaman\Service\Container\ServiceRegistry;

test('ConfigurationService loads current config for service', function () {
    $registry = Mockery::mock(ServiceRegistry::class);
    $service = new ConfigurationService($registry);

    $config = [
        'services' => [
            'mysql' => [
                'enabled' => true,
                'config' => [
                    'version' => '8.0',
                    'port' => 3306,
                ],
            ],
        ],
    ];

    $result = $service->extractServiceConfig('mysql', $config);

    expect($result)->toBe([
        'version' => '8.0',
        'port' => 3306,
    ]);
});

test('ConfigurationService returns empty array if no config exists', function () {
    $registry = Mockery::mock(ServiceRegistry::class);
    $service = new ConfigurationService($registry);

    $config = [
        'services' => [
            'mysql' => [
                'enabled' => true,
            ],
        ],
    ];

    $result = $service->extractServiceConfig('mysql', $config);

    expect($result)->toBe([]);
});

test('ConfigurationService merges new config with existing', function () {
    $registry = Mockery::mock(ServiceRegistry::class);
    $service = new ConfigurationService($registry);

    $existingConfig = [
        'services' => [
            'mysql' => [
                'enabled' => true,
                'config' => [
                    'version' => '8.0',
                ],
            ],
        ],
    ];

    $newServiceConfig = [
        'version' => '8.4',
        'port' => 3307,
    ];

    $result = $service->mergeConfig($existingConfig, 'mysql', $newServiceConfig);

    expect($result['services']['mysql']['config'])->toBe([
        'version' => '8.4',
        'port' => 3307,
    ]);
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Service/ConfigurationServiceTest.php`
Expected: FAIL - class not found

**Step 3: Write minimal implementation**

```php
<?php

// ABOUTME: Orchestrates interactive service configuration.
// ABOUTME: Loads, renders forms, validates, and saves service config.

declare(strict_types=1);

namespace Seaman\Service;

use Seaman\Service\Container\ServiceRegistry;

final readonly class ConfigurationService
{
    public function __construct(
        private ServiceRegistry $registry,
    ) {}

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function extractServiceConfig(string $serviceName, array $config): array
    {
        /** @var array<string, mixed> */
        return $config['services'][$serviceName]['config'] ?? [];
    }

    /**
     * @param array<string, mixed> $existingConfig
     * @param array<string, mixed> $newServiceConfig
     * @return array<string, mixed>
     */
    public function mergeConfig(
        array $existingConfig,
        string $serviceName,
        array $newServiceConfig,
    ): array {
        $existingConfig['services'][$serviceName]['config'] = $newServiceConfig;
        return $existingConfig;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Service/ConfigurationServiceTest.php`
Expected: PASS

**Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Service/ConfigurationService.php`
Expected: No errors

**Step 6: Commit**

```bash
git add src/Service/ConfigurationService.php tests/Unit/Service/ConfigurationServiceTest.php
git commit -m "feat(config): add ConfigurationService for config management"
```

---

### Task 6: Add Form Rendering to ConfigurationService

**Files:**
- Modify: `src/Service/ConfigurationService.php`
- Test: `tests/Unit/Service/ConfigurationServiceTest.php`

**Step 1: Write the failing tests**

Add to test file:

```php
test('ConfigurationService renders text field', function () {
    // This test verifies the field rendering logic
    // We'll test the renderField method that returns prompt config
    $registry = Mockery::mock(ServiceRegistry::class);
    $service = new ConfigurationService($registry);

    $schema = ConfigSchema::create()
        ->string('version', default: '8.0')
            ->label('MySQL Version')
            ->description('Docker image tag');

    $fields = $schema->getFields();
    $config = $service->buildPromptConfig($fields['version'], ['version' => '8.4']);

    expect($config['type'])->toBe('text')
        ->and($config['label'])->toBe('MySQL Version')
        ->and($config['hint'])->toBe('Docker image tag')
        ->and($config['default'])->toBe('8.4');
});

test('ConfigurationService renders password field for secret', function () {
    $registry = Mockery::mock(ServiceRegistry::class);
    $service = new ConfigurationService($registry);

    $schema = ConfigSchema::create()
        ->string('password', default: 'secret')
            ->label('Root Password')
            ->secret();

    $fields = $schema->getFields();
    $config = $service->buildPromptConfig($fields['password'], []);

    expect($config['type'])->toBe('password');
});

test('ConfigurationService renders select for enum field', function () {
    $registry = Mockery::mock(ServiceRegistry::class);
    $service = new ConfigurationService($registry);

    $schema = ConfigSchema::create()
        ->string('log_level', default: 'info')
            ->enum(['debug', 'info', 'warn', 'error'])
            ->label('Log Level');

    $fields = $schema->getFields();
    $config = $service->buildPromptConfig($fields['log_level'], []);

    expect($config['type'])->toBe('select')
        ->and($config['options'])->toBe(['debug', 'info', 'warn', 'error']);
});

test('ConfigurationService renders confirm for boolean field', function () {
    $registry = Mockery::mock(ServiceRegistry::class);
    $service = new ConfigurationService($registry);

    $schema = ConfigSchema::create()
        ->boolean('metrics', default: false)
            ->label('Enable Metrics');

    $fields = $schema->getFields();
    $config = $service->buildPromptConfig($fields['metrics'], ['metrics' => true]);

    expect($config['type'])->toBe('confirm')
        ->and($config['default'])->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Service/ConfigurationServiceTest.php --filter="renders"`
Expected: FAIL - method not found

**Step 3: Implement buildPromptConfig method**

```php
use Seaman\Plugin\Config\StringField;
use Seaman\Plugin\Config\IntegerField;
use Seaman\Plugin\Config\BooleanField;
use Seaman\Plugin\Config\FieldInterface;

/**
 * @param array<string, mixed> $currentConfig
 * @return array<string, mixed>
 */
public function buildPromptConfig(FieldInterface $field, array $currentConfig): array
{
    $metadata = $field->getMetadata();
    $name = $field->getName();
    $default = $currentConfig[$name] ?? $field->getDefault();

    if ($field instanceof BooleanField) {
        return [
            'type' => 'confirm',
            'label' => $metadata->label,
            'hint' => $metadata->description,
            'default' => (bool) $default,
        ];
    }

    if ($field instanceof IntegerField) {
        return [
            'type' => 'text',
            'label' => $metadata->label,
            'hint' => $metadata->description,
            'default' => (string) $default,
        ];
    }

    // StringField
    if ($field instanceof StringField) {
        if ($metadata->isSecret) {
            return [
                'type' => 'password',
                'label' => $metadata->label,
                'hint' => $metadata->description,
            ];
        }

        $enum = $field->getEnum();
        if ($enum !== null) {
            return [
                'type' => 'select',
                'label' => $metadata->label,
                'hint' => $metadata->description,
                'options' => $enum,
                'default' => (string) $default,
            ];
        }

        return [
            'type' => 'text',
            'label' => $metadata->label,
            'hint' => $metadata->description,
            'default' => (string) $default,
        ];
    }

    throw new \InvalidArgumentException("Unknown field type");
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Service/ConfigurationServiceTest.php`
Expected: PASS

**Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Service/ConfigurationService.php`
Expected: No errors

**Step 6: Commit**

```bash
git add src/Service/ConfigurationService.php tests/Unit/Service/ConfigurationServiceTest.php
git commit -m "feat(config): add form rendering to ConfigurationService"
```

---

### Task 7: Create ConfigureCommand

**Files:**
- Create: `src/Command/ConfigureCommand.php`
- Test: `tests/Unit/Command/ConfigureCommandTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Unit tests for ConfigureCommand.
// ABOUTME: Validates interactive service configuration command.

namespace Seaman\Tests\Unit\Command;

use Seaman\Application;
use Seaman\Tests\Integration\TestHelper;
use Seaman\UI\HeadlessMode;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    HeadlessMode::reset();
    HeadlessMode::enable();
    $this->tempDir = TestHelper::createTempDir();
    $originalDir = getcwd();
    if ($originalDir === false) {
        throw new \RuntimeException('Failed to get current working directory');
    }
    $this->originalDir = $originalDir;
    chdir($this->tempDir);
});

afterEach(function () {
    HeadlessMode::reset();
    chdir($this->originalDir);
    TestHelper::removeTempDir($this->tempDir);
});

test('configure command requires seaman.yaml', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('configure'));

    $commandTester->execute(['service' => 'mysql']);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('Failed to load configuration');
});

test('configure command requires valid service name', function () {
    TestHelper::copyFixture('minimal-seaman.yaml', $this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('configure'));

    $commandTester->execute(['service' => 'nonexistent']);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('Service not found');
});

test('configure command requires service to be enabled', function () {
    TestHelper::copyFixture('minimal-seaman.yaml', $this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('configure'));

    $commandTester->execute(['service' => 'mysql']);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('Service is not enabled');
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Command/ConfigureCommandTest.php`
Expected: FAIL - command not found

**Step 3: Write minimal implementation**

```php
<?php

// ABOUTME: Interactive command for configuring services.
// ABOUTME: Renders form from plugin's ConfigSchema and saves to seaman.yaml.

declare(strict_types=1);

namespace Seaman\Command;

use Seaman\Config\ConfigLoader;
use Seaman\Service\ConfigurationService;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\UI\ConsoleOutput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'configure',
    description: 'Interactively configure a service',
)]
final class ConfigureCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument(
            'service',
            InputArgument::REQUIRED,
            'The service to configure',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new ConsoleOutput($output);

        $configLoader = new ConfigLoader();
        $config = $configLoader->load();

        if ($config === null) {
            $console->error('Failed to load configuration');
            return Command::FAILURE;
        }

        /** @var string $serviceName */
        $serviceName = $input->getArgument('service');

        $registry = new ServiceRegistry();

        if (!$registry->has($serviceName)) {
            $console->error("Service not found: {$serviceName}");
            return Command::FAILURE;
        }

        $enabledServices = $config->getEnabledServiceNames();
        if (!in_array($serviceName, $enabledServices, true)) {
            $console->error("Service is not enabled: {$serviceName}");
            return Command::FAILURE;
        }

        // TODO: Implement interactive form

        return Command::SUCCESS;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Command/ConfigureCommandTest.php`
Expected: PASS

**Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Command/ConfigureCommand.php`
Expected: No errors

**Step 6: Commit**

```bash
git add src/Command/ConfigureCommand.php tests/Unit/Command/ConfigureCommandTest.php
git commit -m "feat(command): add ConfigureCommand skeleton"
```

---

### Task 8: Implement Interactive Form in ConfigureCommand

**Files:**
- Modify: `src/Command/ConfigureCommand.php`
- Test: `tests/Integration/Command/ConfigureCommandTest.php`

**Step 1: Write the failing integration test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Integration tests for ConfigureCommand.
// ABOUTME: Validates interactive service configuration flow.

namespace Seaman\Tests\Integration\Command;

use Seaman\Application;
use Seaman\Tests\Integration\TestHelper;
use Seaman\UI\HeadlessMode;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    HeadlessMode::reset();
    HeadlessMode::enable();
    $this->tempDir = TestHelper::createTempDir();
    $originalDir = getcwd();
    if ($originalDir === false) {
        throw new \RuntimeException('Failed to get current working directory');
    }
    $this->originalDir = $originalDir;
    chdir($this->tempDir);
});

afterEach(function () {
    HeadlessMode::reset();
    chdir($this->originalDir);
    TestHelper::removeTempDir($this->tempDir);
});

test('configure command shows service configuration form', function () {
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('configure'));

    // In headless mode, Laravel Prompts uses defaults
    $commandTester->execute(['service' => 'mysql']);

    expect($commandTester->getStatusCode())->toBe(0);
    expect($commandTester->getDisplay())->toContain('Configuration saved');
});

test('configure command saves config to seaman.yaml', function () {
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('configure'));

    $commandTester->execute(['service' => 'mysql']);

    $savedConfig = yaml_parse_file($this->tempDir . '/seaman.yaml');
    expect($savedConfig['services']['mysql'])->toHaveKey('config');
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Integration/Command/ConfigureCommandTest.php`
Expected: FAIL - form not implemented

**Step 3: Implement interactive form**

Update ConfigureCommand execute method:

```php
use Seaman\Service\ConfigurationService;
use Seaman\Service\EnvGenerator;
use function Laravel\Prompts\text;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;

protected function execute(InputInterface $input, OutputInterface $output): int
{
    $console = new ConsoleOutput($output);

    $configLoader = new ConfigLoader();
    $config = $configLoader->load();

    if ($config === null) {
        $console->error('Failed to load configuration');
        return Command::FAILURE;
    }

    /** @var string $serviceName */
    $serviceName = $input->getArgument('service');

    $registry = new ServiceRegistry();

    if (!$registry->has($serviceName)) {
        $console->error("Service not found: {$serviceName}");
        return Command::FAILURE;
    }

    $enabledServices = $config->getEnabledServiceNames();
    if (!in_array($serviceName, $enabledServices, true)) {
        $console->error("Service is not enabled: {$serviceName}");
        return Command::FAILURE;
    }

    $service = $registry->get($serviceName);
    $configService = new ConfigurationService($registry);

    // Load current config
    $rawConfig = yaml_parse_file('seaman.yaml');
    $currentServiceConfig = $configService->extractServiceConfig($serviceName, $rawConfig);

    // Get schema from plugin
    $definition = $registry->getDefinition($serviceName);
    $schema = $definition->configSchema;

    $newConfig = [];

    // Render form for each field
    foreach ($schema->getFields() as $name => $field) {
        $promptConfig = $configService->buildPromptConfig($field, $currentServiceConfig);

        $newConfig[$name] = match ($promptConfig['type']) {
            'password' => password(
                label: $promptConfig['label'],
                hint: $promptConfig['hint'] ?? '',
            ),
            'select' => select(
                label: $promptConfig['label'],
                options: $promptConfig['options'],
                default: $promptConfig['default'] ?? null,
            ),
            'confirm' => confirm(
                label: $promptConfig['label'],
                hint: $promptConfig['hint'] ?? '',
                default: $promptConfig['default'] ?? false,
            ),
            default => text(
                label: $promptConfig['label'],
                hint: $promptConfig['hint'] ?? '',
                default: $promptConfig['default'] ?? '',
            ),
        };
    }

    // Save to seaman.yaml
    $updatedConfig = $configService->mergeConfig($rawConfig, $serviceName, $newConfig);
    file_put_contents('seaman.yaml', yaml_emit($updatedConfig));

    // Regenerate .env
    $envGenerator = new EnvGenerator();
    $envGenerator->generate($config);

    $console->success('Configuration saved');

    // Ask about restart
    $action = select(
        label: 'What would you like to do?',
        options: [
            'none' => 'Nothing - I\'ll restart manually',
            'service' => "Restart only {$serviceName}",
            'stack' => 'Restart entire stack',
        ],
        default: 'none',
    );

    // Handle restart (delegate to existing commands)
    // ...

    return Command::SUCCESS;
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Integration/Command/ConfigureCommandTest.php`
Expected: PASS

**Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Command/ConfigureCommand.php`
Expected: No errors

**Step 6: Commit**

```bash
git add src/Command/ConfigureCommand.php tests/Integration/Command/ConfigureCommandTest.php
git commit -m "feat(command): implement interactive form in ConfigureCommand"
```

---

### Task 9: Add Restart Options to ConfigureCommand

**Files:**
- Modify: `src/Command/ConfigureCommand.php`
- Test: `tests/Integration/Command/ConfigureCommandTest.php`

**Step 1: Write the failing test**

Add to integration tests:

```php
test('configure command offers restart options after saving', function () {
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);

    // Create docker-compose.yml so restart is possible
    file_put_contents($this->tempDir . '/docker-compose.yml', "version: '3'\n");

    $application = new Application();
    $commandTester = new CommandTester($application->find('configure'));

    $commandTester->execute(['service' => 'mysql']);

    expect($commandTester->getDisplay())->toContain('What would you like to do?');
});
```

**Step 2-6: Implementation similar to above**

The restart functionality uses existing Docker commands:
- `docker compose restart <service>` for single service
- `docker compose up -d` for full stack

**Step 6: Commit**

```bash
git add src/Command/ConfigureCommand.php tests/Integration/Command/ConfigureCommandTest.php
git commit -m "feat(command): add restart options to ConfigureCommand"
```

---

### Task 10: Update MySQL Plugin with UI Metadata

**Files:**
- Modify: `plugins/mysql/src/MysqlPlugin.php`
- Test: `tests/Unit/Plugin/MysqlPluginTest.php`

**Step 1: Write the failing test**

```php
test('MySQL plugin ConfigSchema has UI metadata', function () {
    $plugin = new MysqlPlugin();
    $definition = $plugin->mysql();

    $fields = $definition->configSchema->getFields();

    expect($fields['version']->getMetadata()->label)->toBe('MySQL Version')
        ->and($fields['port']->getMetadata()->label)->toBe('External Port')
        ->and($fields['root_password']->getMetadata()->isSecret)->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/MysqlPluginTest.php --filter="UI metadata"`
Expected: FAIL - no label set

**Step 3: Update MySQL plugin ConfigSchema**

```php
$this->schema = ConfigSchema::create()
    ->string('version', default: '8.0')
        ->label('MySQL Version')
        ->description('Docker image tag to use')
    ->integer('port', default: 3306, min: 1, max: 65535)
        ->label('External Port')
        ->description('Port exposed on host machine')
    ->string('root_password', default: 'seaman')
        ->label('Root Password')
        ->description('MySQL root user password')
        ->secret()
    ->string('database', default: 'seaman')
        ->label('Database Name')
        ->description('Default database to create');
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/MysqlPluginTest.php`
Expected: PASS

**Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse plugins/mysql/src/MysqlPlugin.php`
Expected: No errors

**Step 6: Commit**

```bash
git add plugins/mysql/src/MysqlPlugin.php tests/Unit/Plugin/MysqlPluginTest.php
git commit -m "feat(mysql): add UI metadata to ConfigSchema"
```

---

### Task 11: Update Remaining Database Plugins with UI Metadata

**Files:**
- Modify: `plugins/postgresql/src/PostgresqlPlugin.php`
- Modify: `plugins/mariadb/src/MariadbPlugin.php`
- Modify: `plugins/mongodb/src/MongodbPlugin.php`
- Modify: `plugins/sqlite/src/SqlitePlugin.php`

Follow same pattern as Task 10 for each plugin.

**Step 6: Commit**

```bash
git add plugins/*/src/*Plugin.php
git commit -m "feat(plugins): add UI metadata to all database plugin ConfigSchemas"
```

---

### Task 12: Update Non-Database Plugins with UI Metadata

**Files:**
- Modify: `plugins/redis/src/RedisPlugin.php`
- Modify: `plugins/memcached/src/MemcachedPlugin.php`
- Modify: `plugins/rabbitmq/src/RabbitmqPlugin.php`
- And remaining plugins...

Follow same pattern as Task 10 for each plugin.

**Step 6: Commit**

```bash
git add plugins/*/src/*Plugin.php
git commit -m "feat(plugins): add UI metadata to all plugin ConfigSchemas"
```

---

### Task 13: Update Documentation

**Files:**
- Modify: `docs/plugins.md`

Add section about ConfigSchema UI metadata:

```markdown
## Configuration UI Metadata

Plugins can add UI metadata to their ConfigSchema for better interactive configuration:

\`\`\`php
ConfigSchema::create()
    ->string('version', default: '8.0')
        ->label('MySQL Version')           // Display name in form
        ->description('Docker image tag')  // Hint text below field
    ->string('password', default: 'secret')
        ->label('Root Password')
        ->secret();                         // Renders as password field
\`\`\`

### Available Methods

| Method | Description |
|--------|-------------|
| `label(string)` | Display name shown in form |
| `description(string)` | Hint text shown below field |
| `secret()` | Renders as password input (StringField only) |

If `label()` is not set, it's auto-generated from the field name (snake_case → Title Case).
```

**Step 6: Commit**

```bash
git add docs/plugins.md
git commit -m "docs: add ConfigSchema UI metadata documentation"
```

---

### Task 14: Run Full Test Suite and Final Cleanup

**Step 1: Run all tests**

```bash
./vendor/bin/pest
```

Expected: All tests pass

**Step 2: Run PHPStan**

```bash
./vendor/bin/phpstan analyse
```

Expected: No errors at level 10

**Step 3: Run php-cs-fixer**

```bash
./vendor/bin/php-cs-fixer fix
```

**Step 4: Final commit**

```bash
git add -A
git commit -m "chore: code style fixes"
```
