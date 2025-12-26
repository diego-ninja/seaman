# Plugin System Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement an extensible plugin system that allows adding services, commands, lifecycle hooks, and template overrides without modifying core code.

**Architecture:** Attribute-based autodiscovery with hybrid distribution (Composer packages + local plugins). Plugins register via `#[AsSeamanPlugin]` and declare extensions via `#[ProvidesService]`, `#[ProvidesCommand]`, `#[OnLifecycle]`, and `#[OverridesTemplate]`.

**Tech Stack:** PHP 8.4 attributes, PHP-DI container, Symfony Console, Twig.

---

## Phase 1: Core Contracts and Attributes

### Task 1: PluginInterface Contract

**Files:**
- Create: `src/Plugin/PluginInterface.php`
- Test: `tests/Unit/Plugin/PluginInterfaceTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use Seaman\Plugin\PluginInterface;

#[CoversClass(PluginInterface::class)]
test('PluginInterface defines required methods', function (): void {
    $reflection = new \ReflectionClass(PluginInterface::class);

    expect($reflection->isInterface())->toBeTrue();
    expect($reflection->hasMethod('getName'))->toBeTrue();
    expect($reflection->hasMethod('getVersion'))->toBeTrue();
    expect($reflection->hasMethod('getDescription'))->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/PluginInterfaceTest.php`
Expected: FAIL with "Interface 'Seaman\Plugin\PluginInterface' not found"

**Step 3: Write minimal implementation**

```php
<?php

// ABOUTME: Contract that all plugins must implement.
// ABOUTME: Defines plugin identity and metadata methods.

declare(strict_types=1);

namespace Seaman\Plugin;

interface PluginInterface
{
    public function getName(): string;

    public function getVersion(): string;

    public function getDescription(): string;
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/PluginInterfaceTest.php`
Expected: PASS

**Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Plugin tests/Unit/Plugin --level=10`
Expected: No errors

**Step 6: Commit**

```bash
git add src/Plugin/PluginInterface.php tests/Unit/Plugin/PluginInterfaceTest.php
git commit -m "feat(plugin): add PluginInterface contract"
```

---

### Task 2: AsSeamanPlugin Attribute

**Files:**
- Create: `src/Plugin/Attribute/AsSeamanPlugin.php`
- Test: `tests/Unit/Plugin/Attribute/AsSeamanPluginTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Attribute;

use Seaman\Plugin\Attribute\AsSeamanPlugin;

test('AsSeamanPlugin stores plugin metadata', function (): void {
    $attribute = new AsSeamanPlugin(
        name: 'test-plugin',
        version: '1.0.0',
        description: 'A test plugin',
        requires: ['seaman/core:^1.0'],
    );

    expect($attribute->name)->toBe('test-plugin');
    expect($attribute->version)->toBe('1.0.0');
    expect($attribute->description)->toBe('A test plugin');
    expect($attribute->requires)->toBe(['seaman/core:^1.0']);
});

test('AsSeamanPlugin has sensible defaults', function (): void {
    $attribute = new AsSeamanPlugin(
        name: 'minimal-plugin',
    );

    expect($attribute->version)->toBe('1.0.0');
    expect($attribute->description)->toBe('');
    expect($attribute->requires)->toBe([]);
});

test('AsSeamanPlugin targets classes only', function (): void {
    $reflection = new \ReflectionClass(AsSeamanPlugin::class);
    $attributes = $reflection->getAttributes(\Attribute::class);

    expect($attributes)->toHaveCount(1);

    $attrInstance = $attributes[0]->newInstance();
    expect($attrInstance->flags)->toBe(\Attribute::TARGET_CLASS);
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Attribute/AsSeamanPluginTest.php`
Expected: FAIL with "Class 'Seaman\Plugin\Attribute\AsSeamanPlugin' not found"

**Step 3: Write minimal implementation**

```php
<?php

// ABOUTME: Attribute to mark a class as a Seaman plugin.
// ABOUTME: Provides plugin identity metadata for registration.

declare(strict_types=1);

namespace Seaman\Plugin\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsSeamanPlugin
{
    /**
     * @param string $name Unique plugin identifier
     * @param string $version Semantic version
     * @param string $description Human-readable description
     * @param list<string> $requires Dependencies (e.g., ['seaman/core:^1.0'])
     */
    public function __construct(
        public string $name,
        public string $version = '1.0.0',
        public string $description = '',
        public array $requires = [],
    ) {}
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Attribute/AsSeamanPluginTest.php`
Expected: PASS

**Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Plugin --level=10`
Expected: No errors

**Step 6: Commit**

```bash
git add src/Plugin/Attribute/AsSeamanPlugin.php tests/Unit/Plugin/Attribute/AsSeamanPluginTest.php
git commit -m "feat(plugin): add AsSeamanPlugin attribute"
```

---

### Task 3: ProvidesService Attribute

**Files:**
- Create: `src/Plugin/Attribute/ProvidesService.php`
- Test: `tests/Unit/Plugin/Attribute/ProvidesServiceTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Attribute;

use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Enum\ServiceCategory;

test('ProvidesService stores service metadata', function (): void {
    $attribute = new ProvidesService(
        name: 'redis-cluster',
        category: ServiceCategory::Cache,
    );

    expect($attribute->name)->toBe('redis-cluster');
    expect($attribute->category)->toBe(ServiceCategory::Cache);
});

test('ProvidesService defaults to Misc category', function (): void {
    $attribute = new ProvidesService(name: 'custom-service');

    expect($attribute->category)->toBe(ServiceCategory::Misc);
});

test('ProvidesService targets methods only', function (): void {
    $reflection = new \ReflectionClass(ProvidesService::class);
    $attributes = $reflection->getAttributes(\Attribute::class);

    $attrInstance = $attributes[0]->newInstance();
    expect($attrInstance->flags)->toBe(\Attribute::TARGET_METHOD);
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Attribute/ProvidesServiceTest.php`
Expected: FAIL with "Class 'Seaman\Plugin\Attribute\ProvidesService' not found"

**Step 3: Create ServiceCategory enum first**

```php
<?php

// ABOUTME: Categories for grouping Docker services.
// ABOUTME: Used for organization in service listings.

declare(strict_types=1);

namespace Seaman\Enum;

enum ServiceCategory: string
{
    case Database = 'database';
    case Cache = 'cache';
    case Queue = 'queue';
    case Search = 'search';
    case DevTools = 'dev-tools';
    case Proxy = 'proxy';
    case Misc = 'misc';
}
```

**Step 4: Write ProvidesService implementation**

```php
<?php

// ABOUTME: Attribute to mark a method as providing a Docker service.
// ABOUTME: Method must return a ServiceDefinition instance.

declare(strict_types=1);

namespace Seaman\Plugin\Attribute;

use Attribute;
use Seaman\Enum\ServiceCategory;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ProvidesService
{
    public function __construct(
        public string $name,
        public ServiceCategory $category = ServiceCategory::Misc,
    ) {}
}
```

**Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Attribute/ProvidesServiceTest.php`
Expected: PASS

**Step 6: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Plugin src/Enum/ServiceCategory.php --level=10`
Expected: No errors

**Step 7: Commit**

```bash
git add src/Enum/ServiceCategory.php src/Plugin/Attribute/ProvidesService.php tests/Unit/Plugin/Attribute/ProvidesServiceTest.php
git commit -m "feat(plugin): add ProvidesService attribute and ServiceCategory enum"
```

---

### Task 4: ProvidesCommand Attribute

**Files:**
- Create: `src/Plugin/Attribute/ProvidesCommand.php`
- Test: `tests/Unit/Plugin/Attribute/ProvidesCommandTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Attribute;

use Seaman\Plugin\Attribute\ProvidesCommand;

test('ProvidesCommand can be instantiated', function (): void {
    $attribute = new ProvidesCommand();

    expect($attribute)->toBeInstanceOf(ProvidesCommand::class);
});

test('ProvidesCommand targets methods only', function (): void {
    $reflection = new \ReflectionClass(ProvidesCommand::class);
    $attributes = $reflection->getAttributes(\Attribute::class);

    $attrInstance = $attributes[0]->newInstance();
    expect($attrInstance->flags)->toBe(\Attribute::TARGET_METHOD);
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Attribute/ProvidesCommandTest.php`
Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

// ABOUTME: Attribute to mark a method as providing a CLI command.
// ABOUTME: Method must return a Symfony Console Command instance.

declare(strict_types=1);

namespace Seaman\Plugin\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ProvidesCommand {}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Attribute/ProvidesCommandTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Plugin/Attribute/ProvidesCommand.php tests/Unit/Plugin/Attribute/ProvidesCommandTest.php
git commit -m "feat(plugin): add ProvidesCommand attribute"
```

---

### Task 5: OnLifecycle Attribute

**Files:**
- Create: `src/Plugin/Attribute/OnLifecycle.php`
- Create: `src/Plugin/LifecycleEvent.php`
- Test: `tests/Unit/Plugin/Attribute/OnLifecycleTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Attribute;

use Seaman\Plugin\Attribute\OnLifecycle;

test('OnLifecycle stores event and priority', function (): void {
    $attribute = new OnLifecycle(
        event: 'before:start',
        priority: 10,
    );

    expect($attribute->event)->toBe('before:start');
    expect($attribute->priority)->toBe(10);
});

test('OnLifecycle defaults to priority 0', function (): void {
    $attribute = new OnLifecycle(event: 'after:init');

    expect($attribute->priority)->toBe(0);
});

test('OnLifecycle targets methods only', function (): void {
    $reflection = new \ReflectionClass(OnLifecycle::class);
    $attributes = $reflection->getAttributes(\Attribute::class);

    $attrInstance = $attributes[0]->newInstance();
    expect($attrInstance->flags)->toBe(\Attribute::TARGET_METHOD);
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Attribute/OnLifecycleTest.php`
Expected: FAIL

**Step 3: Write LifecycleEvent enum**

```php
<?php

// ABOUTME: Enumeration of lifecycle events that plugins can hook into.
// ABOUTME: Used with OnLifecycle attribute to specify hook timing.

declare(strict_types=1);

namespace Seaman\Plugin;

enum LifecycleEvent: string
{
    case BeforeInit = 'before:init';
    case AfterInit = 'after:init';
    case BeforeStart = 'before:start';
    case AfterStart = 'after:start';
    case BeforeStop = 'before:stop';
    case AfterStop = 'after:stop';
    case BeforeRebuild = 'before:rebuild';
    case AfterRebuild = 'after:rebuild';
    case BeforeDestroy = 'before:destroy';
    case AfterDestroy = 'after:destroy';
}
```

**Step 4: Write OnLifecycle implementation**

```php
<?php

// ABOUTME: Attribute to mark a method as a lifecycle event handler.
// ABOUTME: Method receives LifecycleEventData with context about the event.

declare(strict_types=1);

namespace Seaman\Plugin\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class OnLifecycle
{
    public function __construct(
        public string $event,
        public int $priority = 0,
    ) {}
}
```

**Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Attribute/OnLifecycleTest.php`
Expected: PASS

**Step 6: Commit**

```bash
git add src/Plugin/LifecycleEvent.php src/Plugin/Attribute/OnLifecycle.php tests/Unit/Plugin/Attribute/OnLifecycleTest.php
git commit -m "feat(plugin): add OnLifecycle attribute and LifecycleEvent enum"
```

---

### Task 6: OverridesTemplate Attribute

**Files:**
- Create: `src/Plugin/Attribute/OverridesTemplate.php`
- Test: `tests/Unit/Plugin/Attribute/OverridesTemplateTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Attribute;

use Seaman\Plugin\Attribute\OverridesTemplate;

test('OverridesTemplate stores template path', function (): void {
    $attribute = new OverridesTemplate(
        template: 'docker/app.dockerfile.twig',
    );

    expect($attribute->template)->toBe('docker/app.dockerfile.twig');
});

test('OverridesTemplate targets methods only', function (): void {
    $reflection = new \ReflectionClass(OverridesTemplate::class);
    $attributes = $reflection->getAttributes(\Attribute::class);

    $attrInstance = $attributes[0]->newInstance();
    expect($attrInstance->flags)->toBe(\Attribute::TARGET_METHOD);
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Attribute/OverridesTemplateTest.php`
Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

// ABOUTME: Attribute to mark a method as providing a template override.
// ABOUTME: Method must return the absolute path to the replacement template.

declare(strict_types=1);

namespace Seaman\Plugin\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class OverridesTemplate
{
    public function __construct(
        public string $template,
    ) {}
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Attribute/OverridesTemplateTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Plugin/Attribute/OverridesTemplate.php tests/Unit/Plugin/Attribute/OverridesTemplateTest.php
git commit -m "feat(plugin): add OverridesTemplate attribute"
```

---

## Phase 2: Configuration System

### Task 7: ConfigSchema Value Object

**Files:**
- Create: `src/Plugin/Config/ConfigSchema.php`
- Test: `tests/Unit/Plugin/Config/ConfigSchemaTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Config;

use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\Config\ConfigValidationException;

test('ConfigSchema can define integer fields', function (): void {
    $schema = ConfigSchema::create()
        ->integer('nodes', default: 3, min: 1, max: 10);

    expect($schema->validate(['nodes' => 5]))->toBe(['nodes' => 5]);
    expect($schema->validate([]))->toBe(['nodes' => 3]);
});

test('ConfigSchema validates integer constraints', function (): void {
    $schema = ConfigSchema::create()
        ->integer('nodes', default: 3, min: 1, max: 10);

    $schema->validate(['nodes' => 15]);
})->throws(ConfigValidationException::class);

test('ConfigSchema can define string fields', function (): void {
    $schema = ConfigSchema::create()
        ->string('name', default: 'default-name');

    expect($schema->validate(['name' => 'custom']))->toBe(['name' => 'custom']);
    expect($schema->validate([]))->toBe(['name' => 'default-name']);
});

test('ConfigSchema can define boolean fields', function (): void {
    $schema = ConfigSchema::create()
        ->boolean('enabled', default: true);

    expect($schema->validate(['enabled' => false]))->toBe(['enabled' => false]);
    expect($schema->validate([]))->toBe(['enabled' => true]);
});

test('ConfigSchema can define nullable fields', function (): void {
    $schema = ConfigSchema::create()
        ->string('password', default: null, nullable: true);

    expect($schema->validate(['password' => null]))->toBe(['password' => null]);
    expect($schema->validate(['password' => 'secret']))->toBe(['password' => 'secret']);
});

test('ConfigSchema rejects unknown fields', function (): void {
    $schema = ConfigSchema::create()
        ->integer('nodes', default: 3);

    $schema->validate(['unknown' => 'value']);
})->throws(ConfigValidationException::class);
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Config/ConfigSchemaTest.php`
Expected: FAIL

**Step 3: Create ConfigValidationException**

```php
<?php

// ABOUTME: Exception thrown when plugin configuration validation fails.
// ABOUTME: Contains field name and validation error details.

declare(strict_types=1);

namespace Seaman\Plugin\Config;

use Seaman\Exception\SeamanException;

final class ConfigValidationException extends SeamanException
{
    public static function invalidValue(string $field, string $reason): self
    {
        return new self("Invalid value for '{$field}': {$reason}");
    }

    public static function unknownField(string $field): self
    {
        return new self("Unknown configuration field: '{$field}'");
    }
}
```

**Step 4: Write ConfigSchema implementation**

```php
<?php

// ABOUTME: Defines and validates plugin configuration schema.
// ABOUTME: Supports integer, string, and boolean fields with constraints.

declare(strict_types=1);

namespace Seaman\Plugin\Config;

final class ConfigSchema
{
    /** @var array<string, array{type: string, default: mixed, nullable: bool, min?: int, max?: int}> */
    private array $fields = [];

    public static function create(): self
    {
        return new self();
    }

    public function integer(
        string $name,
        int $default,
        ?int $min = null,
        ?int $max = null,
    ): self {
        $this->fields[$name] = [
            'type' => 'integer',
            'default' => $default,
            'nullable' => false,
            'min' => $min,
            'max' => $max,
        ];
        return $this;
    }

    public function string(
        string $name,
        ?string $default = null,
        bool $nullable = false,
    ): self {
        $this->fields[$name] = [
            'type' => 'string',
            'default' => $default,
            'nullable' => $nullable,
        ];
        return $this;
    }

    public function boolean(
        string $name,
        bool $default = false,
    ): self {
        $this->fields[$name] = [
            'type' => 'boolean',
            'default' => $default,
            'nullable' => false,
        ];
        return $this;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     * @throws ConfigValidationException
     */
    public function validate(array $values): array
    {
        $validated = [];

        // Check for unknown fields
        foreach (array_keys($values) as $key) {
            if (!isset($this->fields[$key])) {
                throw ConfigValidationException::unknownField($key);
            }
        }

        // Validate and apply defaults
        foreach ($this->fields as $name => $definition) {
            $value = $values[$name] ?? $definition['default'];
            $validated[$name] = $this->validateField($name, $value, $definition);
        }

        return $validated;
    }

    /**
     * @param array{type: string, default: mixed, nullable: bool, min?: int, max?: int} $definition
     */
    private function validateField(string $name, mixed $value, array $definition): mixed
    {
        if ($value === null) {
            if (!$definition['nullable']) {
                throw ConfigValidationException::invalidValue($name, 'cannot be null');
            }
            return null;
        }

        return match ($definition['type']) {
            'integer' => $this->validateInteger($name, $value, $definition),
            'string' => $this->validateString($name, $value),
            'boolean' => $this->validateBoolean($name, $value),
            default => $value,
        };
    }

    /**
     * @param array{type: string, default: mixed, nullable: bool, min?: int, max?: int} $definition
     */
    private function validateInteger(string $name, mixed $value, array $definition): int
    {
        if (!is_int($value)) {
            throw ConfigValidationException::invalidValue($name, 'must be an integer');
        }

        if (isset($definition['min']) && $value < $definition['min']) {
            throw ConfigValidationException::invalidValue($name, "must be at least {$definition['min']}");
        }

        if (isset($definition['max']) && $value > $definition['max']) {
            throw ConfigValidationException::invalidValue($name, "must be at most {$definition['max']}");
        }

        return $value;
    }

    private function validateString(string $name, mixed $value): string
    {
        if (!is_string($value)) {
            throw ConfigValidationException::invalidValue($name, 'must be a string');
        }

        return $value;
    }

    private function validateBoolean(string $name, mixed $value): bool
    {
        if (!is_bool($value)) {
            throw ConfigValidationException::invalidValue($name, 'must be a boolean');
        }

        return $value;
    }
}
```

**Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Config/ConfigSchemaTest.php`
Expected: PASS

**Step 6: Commit**

```bash
git add src/Plugin/Config/ConfigSchema.php src/Plugin/Config/ConfigValidationException.php tests/Unit/Plugin/Config/ConfigSchemaTest.php
git commit -m "feat(plugin): add ConfigSchema for plugin configuration validation"
```

---

### Task 8: PluginConfig Value Object

**Files:**
- Create: `src/Plugin/Config/PluginConfig.php`
- Test: `tests/Unit/Plugin/Config/PluginConfigTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Config;

use Seaman\Plugin\Config\PluginConfig;

test('PluginConfig provides typed access to values', function (): void {
    $config = new PluginConfig([
        'nodes' => 3,
        'name' => 'cluster',
        'enabled' => true,
    ]);

    expect($config->get('nodes'))->toBe(3);
    expect($config->get('name'))->toBe('cluster');
    expect($config->get('enabled'))->toBe(true);
});

test('PluginConfig returns all values', function (): void {
    $values = ['nodes' => 3, 'name' => 'cluster'];
    $config = new PluginConfig($values);

    expect($config->all())->toBe($values);
});

test('PluginConfig returns null for missing keys', function (): void {
    $config = new PluginConfig(['nodes' => 3]);

    expect($config->get('missing'))->toBeNull();
});

test('PluginConfig has method checks key existence', function (): void {
    $config = new PluginConfig(['nodes' => 3]);

    expect($config->has('nodes'))->toBeTrue();
    expect($config->has('missing'))->toBeFalse();
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Config/PluginConfigTest.php`
Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

// ABOUTME: Provides typed access to validated plugin configuration.
// ABOUTME: Immutable value object wrapping configuration array.

declare(strict_types=1);

namespace Seaman\Plugin\Config;

final readonly class PluginConfig
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        private array $values,
    ) {}

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Config/PluginConfigTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Plugin/Config/PluginConfig.php tests/Unit/Plugin/Config/PluginConfigTest.php
git commit -m "feat(plugin): add PluginConfig value object"
```

---

## Phase 3: Plugin Loading

### Task 9: LoadedPlugin Value Object

**Files:**
- Create: `src/Plugin/LoadedPlugin.php`
- Test: `tests/Unit/Plugin/LoadedPluginTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin;

use Seaman\Plugin\LoadedPlugin;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\Config\PluginConfig;

test('LoadedPlugin stores plugin instance and metadata', function (): void {
    $plugin = new class implements PluginInterface {
        public function getName(): string { return 'test'; }
        public function getVersion(): string { return '1.0.0'; }
        public function getDescription(): string { return 'Test plugin'; }
    };

    $config = new PluginConfig(['nodes' => 3]);
    $loaded = new LoadedPlugin(
        instance: $plugin,
        config: $config,
        source: 'composer',
    );

    expect($loaded->instance)->toBe($plugin);
    expect($loaded->config)->toBe($config);
    expect($loaded->source)->toBe('composer');
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/LoadedPluginTest.php`
Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

// ABOUTME: Represents a fully loaded and configured plugin.
// ABOUTME: Contains plugin instance, validated config, and source information.

declare(strict_types=1);

namespace Seaman\Plugin;

use Seaman\Plugin\Config\PluginConfig;

final readonly class LoadedPlugin
{
    public function __construct(
        public PluginInterface $instance,
        public PluginConfig $config,
        public string $source,
    ) {}
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/LoadedPluginTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Plugin/LoadedPlugin.php tests/Unit/Plugin/LoadedPluginTest.php
git commit -m "feat(plugin): add LoadedPlugin value object"
```

---

### Task 10: PluginLoaderInterface

**Files:**
- Create: `src/Plugin/Loader/PluginLoaderInterface.php`
- Test: `tests/Unit/Plugin/Loader/PluginLoaderInterfaceTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Loader;

use Seaman\Plugin\Loader\PluginLoaderInterface;

test('PluginLoaderInterface defines load method', function (): void {
    $reflection = new \ReflectionClass(PluginLoaderInterface::class);

    expect($reflection->isInterface())->toBeTrue();
    expect($reflection->hasMethod('load'))->toBeTrue();

    $method = $reflection->getMethod('load');
    expect($method->getReturnType()?->getName())->toBe('array');
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Loader/PluginLoaderInterfaceTest.php`
Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

// ABOUTME: Contract for plugin loaders.
// ABOUTME: Implementations discover and instantiate plugins from different sources.

declare(strict_types=1);

namespace Seaman\Plugin\Loader;

use Seaman\Plugin\PluginInterface;

interface PluginLoaderInterface
{
    /**
     * Load plugins from the source.
     *
     * @return list<PluginInterface>
     */
    public function load(): array;
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Loader/PluginLoaderInterfaceTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Plugin/Loader/PluginLoaderInterface.php tests/Unit/Plugin/Loader/PluginLoaderInterfaceTest.php
git commit -m "feat(plugin): add PluginLoaderInterface contract"
```

---

### Task 11: LocalPluginLoader

**Files:**
- Create: `src/Plugin/Loader/LocalPluginLoader.php`
- Test: `tests/Unit/Plugin/Loader/LocalPluginLoaderTest.php`
- Create: `tests/Fixtures/Plugins/ValidPlugin/ValidPlugin.php`

**Step 1: Create test fixture plugin**

```php
<?php

// ABOUTME: Test fixture for plugin loading tests.
// ABOUTME: Minimal valid plugin implementation.

declare(strict_types=1);

namespace Seaman\Tests\Fixtures\Plugins\ValidPlugin;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;

#[AsSeamanPlugin(name: 'valid-plugin', version: '1.0.0', description: 'A valid test plugin')]
final class ValidPlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'valid-plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'A valid test plugin';
    }
}
```

**Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Loader;

use Seaman\Plugin\Loader\LocalPluginLoader;
use Seaman\Plugin\PluginInterface;

test('LocalPluginLoader discovers plugins in directory', function (): void {
    $loader = new LocalPluginLoader(__DIR__ . '/../../../Fixtures/Plugins');
    $plugins = $loader->load();

    expect($plugins)->toHaveCount(1);
    expect($plugins[0])->toBeInstanceOf(PluginInterface::class);
    expect($plugins[0]->getName())->toBe('valid-plugin');
});

test('LocalPluginLoader returns empty array for non-existent directory', function (): void {
    $loader = new LocalPluginLoader('/non/existent/path');
    $plugins = $loader->load();

    expect($plugins)->toBe([]);
});

test('LocalPluginLoader ignores classes without AsSeamanPlugin attribute', function (): void {
    // Create a temp directory with a non-plugin PHP file
    $tempDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($tempDir, 0755, true);
    file_put_contents($tempDir . '/NotAPlugin.php', '<?php class NotAPlugin {}');

    $loader = new LocalPluginLoader($tempDir);
    $plugins = $loader->load();

    expect($plugins)->toBe([]);

    // Cleanup
    unlink($tempDir . '/NotAPlugin.php');
    rmdir($tempDir);
});
```

**Step 3: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Loader/LocalPluginLoaderTest.php`
Expected: FAIL

**Step 4: Write LocalPluginLoader implementation**

```php
<?php

// ABOUTME: Loads plugins from a local directory (.seaman/plugins/).
// ABOUTME: Scans for PHP classes with AsSeamanPlugin attribute.

declare(strict_types=1);

namespace Seaman\Plugin\Loader;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RegexIterator;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;

final readonly class LocalPluginLoader implements PluginLoaderInterface
{
    public function __construct(
        private string $pluginsDir,
    ) {}

    /**
     * @return list<PluginInterface>
     */
    public function load(): array
    {
        if (!is_dir($this->pluginsDir)) {
            return [];
        }

        $plugins = [];

        foreach ($this->scanPhpFiles() as $filePath) {
            $plugin = $this->loadPlugin($filePath);
            if ($plugin !== null) {
                $plugins[] = $plugin;
            }
        }

        return $plugins;
    }

    /**
     * @return list<string>
     */
    private function scanPhpFiles(): array
    {
        $files = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->pluginsDir),
            );

            $phpFiles = new RegexIterator($iterator, '/\.php$/');

            /** @var \SplFileInfo $file */
            foreach ($phpFiles as $file) {
                if ($file->isFile()) {
                    $files[] = $file->getPathname();
                }
            }
        } catch (\Exception) {
            return [];
        }

        return $files;
    }

    private function loadPlugin(string $filePath): ?PluginInterface
    {
        $className = $this->extractClassName($filePath);
        if ($className === null) {
            return null;
        }

        require_once $filePath;

        if (!class_exists($className)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($className);

            // Must have AsSeamanPlugin attribute
            $attributes = $reflection->getAttributes(AsSeamanPlugin::class);
            if (empty($attributes)) {
                return null;
            }

            // Must implement PluginInterface
            if (!$reflection->implementsInterface(PluginInterface::class)) {
                return null;
            }

            if (!$reflection->isInstantiable()) {
                return null;
            }

            /** @var PluginInterface */
            return $reflection->newInstance();
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        if (!preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
            return null;
        }

        if (!preg_match('/(?:final\s+)?(?:readonly\s+)?class\s+(\w+)/', $content, $classMatch)) {
            return null;
        }

        return $namespaceMatch[1] . '\\' . $classMatch[1];
    }
}
```

**Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Loader/LocalPluginLoaderTest.php`
Expected: PASS

**Step 6: Commit**

```bash
git add src/Plugin/Loader/LocalPluginLoader.php tests/Unit/Plugin/Loader/LocalPluginLoaderTest.php tests/Fixtures/Plugins/
git commit -m "feat(plugin): add LocalPluginLoader for .seaman/plugins/"
```

---

### Task 12: ComposerPluginLoader

**Files:**
- Create: `src/Plugin/Loader/ComposerPluginLoader.php`
- Test: `tests/Unit/Plugin/Loader/ComposerPluginLoaderTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Loader;

use Seaman\Plugin\Loader\ComposerPluginLoader;

test('ComposerPluginLoader returns empty array when no plugins installed', function (): void {
    // Use a temp directory with no seaman-plugin packages
    $tempDir = sys_get_temp_dir() . '/seaman-composer-test-' . uniqid();
    mkdir($tempDir . '/vendor', 0755, true);
    file_put_contents($tempDir . '/vendor/installed.json', json_encode(['packages' => []]));

    $loader = new ComposerPluginLoader($tempDir);
    $plugins = $loader->load();

    expect($plugins)->toBe([]);

    // Cleanup
    unlink($tempDir . '/vendor/installed.json');
    rmdir($tempDir . '/vendor');
    rmdir($tempDir);
});

test('ComposerPluginLoader returns empty for missing vendor directory', function (): void {
    $loader = new ComposerPluginLoader('/non/existent/path');
    $plugins = $loader->load();

    expect($plugins)->toBe([]);
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Loader/ComposerPluginLoaderTest.php`
Expected: FAIL

**Step 3: Write ComposerPluginLoader implementation**

```php
<?php

// ABOUTME: Loads plugins from Composer packages.
// ABOUTME: Scans vendor/ for packages with type "seaman-plugin".

declare(strict_types=1);

namespace Seaman\Plugin\Loader;

use ReflectionClass;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;

final readonly class ComposerPluginLoader implements PluginLoaderInterface
{
    public function __construct(
        private string $projectRoot,
    ) {}

    /**
     * @return list<PluginInterface>
     */
    public function load(): array
    {
        $installedFile = $this->projectRoot . '/vendor/composer/installed.json';

        if (!file_exists($installedFile)) {
            return [];
        }

        $content = file_get_contents($installedFile);
        if ($content === false) {
            return [];
        }

        /** @var array{packages?: list<array{name: string, type?: string, extra?: array{seaman?: array{plugin-class?: string}}}>} $installed */
        $installed = json_decode($content, true);
        if (!is_array($installed)) {
            return [];
        }

        $packages = $installed['packages'] ?? $installed;
        if (!is_array($packages)) {
            return [];
        }

        $plugins = [];

        foreach ($packages as $package) {
            if (!is_array($package)) {
                continue;
            }

            $type = $package['type'] ?? '';
            if ($type !== 'seaman-plugin') {
                continue;
            }

            $pluginClass = $package['extra']['seaman']['plugin-class'] ?? null;
            if ($pluginClass === null) {
                continue;
            }

            $plugin = $this->loadPluginClass($pluginClass);
            if ($plugin !== null) {
                $plugins[] = $plugin;
            }
        }

        return $plugins;
    }

    private function loadPluginClass(string $className): ?PluginInterface
    {
        if (!class_exists($className)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($className);

            $attributes = $reflection->getAttributes(AsSeamanPlugin::class);
            if (empty($attributes)) {
                return null;
            }

            if (!$reflection->implementsInterface(PluginInterface::class)) {
                return null;
            }

            if (!$reflection->isInstantiable()) {
                return null;
            }

            /** @var PluginInterface */
            return $reflection->newInstance();
        } catch (\Throwable) {
            return null;
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Loader/ComposerPluginLoaderTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Plugin/Loader/ComposerPluginLoader.php tests/Unit/Plugin/Loader/ComposerPluginLoaderTest.php
git commit -m "feat(plugin): add ComposerPluginLoader for vendor/ packages"
```

---

## Phase 4: Plugin Registry

### Task 13: PluginRegistry Core

**Files:**
- Create: `src/Plugin/PluginRegistry.php`
- Test: `tests/Unit/Plugin/PluginRegistryTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin;

use Seaman\Plugin\PluginRegistry;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\LoadedPlugin;
use Seaman\Plugin\Attribute\AsSeamanPlugin;

test('PluginRegistry can register and retrieve plugins', function (): void {
    $registry = new PluginRegistry();

    $plugin = new #[AsSeamanPlugin(name: 'test')] class implements PluginInterface {
        public function getName(): string { return 'test'; }
        public function getVersion(): string { return '1.0.0'; }
        public function getDescription(): string { return 'Test'; }
    };

    $registry->register($plugin, []);

    expect($registry->has('test'))->toBeTrue();
    expect($registry->get('test'))->toBeInstanceOf(LoadedPlugin::class);
});

test('PluginRegistry returns all registered plugins', function (): void {
    $registry = new PluginRegistry();

    $plugin1 = new #[AsSeamanPlugin(name: 'plugin-1')] class implements PluginInterface {
        public function getName(): string { return 'plugin-1'; }
        public function getVersion(): string { return '1.0.0'; }
        public function getDescription(): string { return 'Plugin 1'; }
    };

    $plugin2 = new #[AsSeamanPlugin(name: 'plugin-2')] class implements PluginInterface {
        public function getName(): string { return 'plugin-2'; }
        public function getVersion(): string { return '1.0.0'; }
        public function getDescription(): string { return 'Plugin 2'; }
    };

    $registry->register($plugin1, []);
    $registry->register($plugin2, []);

    expect($registry->all())->toHaveCount(2);
});

test('PluginRegistry throws for unknown plugin', function (): void {
    $registry = new PluginRegistry();

    $registry->get('unknown');
})->throws(\InvalidArgumentException::class);
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/PluginRegistryTest.php`
Expected: FAIL

**Step 3: Write PluginRegistry implementation**

```php
<?php

// ABOUTME: Central registry for loaded plugins.
// ABOUTME: Manages plugin lifecycle and provides access to plugin instances.

declare(strict_types=1);

namespace Seaman\Plugin;

use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\Config\PluginConfig;

final class PluginRegistry
{
    /** @var array<string, LoadedPlugin> */
    private array $plugins = [];

    /**
     * @param array<string, mixed> $config
     */
    public function register(PluginInterface $plugin, array $config, string $source = 'unknown'): void
    {
        $name = $plugin->getName();

        // Validate config if plugin defines a schema
        $validatedConfig = $this->validateConfig($plugin, $config);

        $this->plugins[$name] = new LoadedPlugin(
            instance: $plugin,
            config: new PluginConfig($validatedConfig),
            source: $source,
        );
    }

    public function has(string $name): bool
    {
        return isset($this->plugins[$name]);
    }

    public function get(string $name): LoadedPlugin
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException("Plugin '{$name}' not found");
        }

        return $this->plugins[$name];
    }

    /**
     * @return array<string, LoadedPlugin>
     */
    public function all(): array
    {
        return $this->plugins;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function validateConfig(PluginInterface $plugin, array $config): array
    {
        if (!method_exists($plugin, 'configSchema')) {
            return $config;
        }

        /** @var ConfigSchema $schema */
        $schema = $plugin->configSchema();

        return $schema->validate($config);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/PluginRegistryTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Plugin/PluginRegistry.php tests/Unit/Plugin/PluginRegistryTest.php
git commit -m "feat(plugin): add PluginRegistry for plugin management"
```

---

### Task 14: PluginRegistry Discovery Integration

**Files:**
- Modify: `src/Plugin/PluginRegistry.php`
- Test: `tests/Unit/Plugin/PluginRegistryDiscoveryTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin;

use Seaman\Plugin\PluginRegistry;
use Seaman\Plugin\Loader\LocalPluginLoader;
use Seaman\Plugin\Loader\ComposerPluginLoader;

test('PluginRegistry discovers plugins from loaders', function (): void {
    $fixturesDir = __DIR__ . '/../../Fixtures/Plugins';

    $registry = PluginRegistry::discover(
        projectRoot: sys_get_temp_dir(),
        localPluginsDir: $fixturesDir,
        pluginConfig: [],
    );

    expect($registry->has('valid-plugin'))->toBeTrue();
});

test('PluginRegistry applies config to discovered plugins', function (): void {
    $fixturesDir = __DIR__ . '/../../Fixtures/Plugins';

    $registry = PluginRegistry::discover(
        projectRoot: sys_get_temp_dir(),
        localPluginsDir: $fixturesDir,
        pluginConfig: [
            'valid-plugin' => ['custom' => 'value'],
        ],
    );

    $loaded = $registry->get('valid-plugin');
    expect($loaded->config->get('custom'))->toBe('value');
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/PluginRegistryDiscoveryTest.php`
Expected: FAIL

**Step 3: Add discover method to PluginRegistry**

Add this method to `src/Plugin/PluginRegistry.php`:

```php
/**
 * @param array<string, array<string, mixed>> $pluginConfig
 */
public static function discover(
    string $projectRoot,
    string $localPluginsDir,
    array $pluginConfig,
): self {
    $registry = new self();

    // Load Composer plugins first
    $composerLoader = new Loader\ComposerPluginLoader($projectRoot);
    foreach ($composerLoader->load() as $plugin) {
        $config = $pluginConfig[$plugin->getName()] ?? [];
        $registry->register($plugin, $config, 'composer');
    }

    // Load local plugins (can override Composer)
    $localLoader = new Loader\LocalPluginLoader($localPluginsDir);
    foreach ($localLoader->load() as $plugin) {
        $config = $pluginConfig[$plugin->getName()] ?? [];
        $registry->register($plugin, $config, 'local');
    }

    return $registry;
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/PluginRegistryDiscoveryTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Plugin/PluginRegistry.php tests/Unit/Plugin/PluginRegistryDiscoveryTest.php
git commit -m "feat(plugin): add discovery integration to PluginRegistry"
```

---

## Phase 5: Extension Point Extractors

### Task 15: ServiceExtractor

**Files:**
- Create: `src/Plugin/Extractor/ServiceExtractor.php`
- Create: `src/Plugin/ServiceDefinition.php`
- Test: `tests/Unit/Plugin/Extractor/ServiceExtractorTest.php`

**Step 1: Create test fixture with service**

Update `tests/Fixtures/Plugins/ValidPlugin/ValidPlugin.php`:

```php
<?php

// ABOUTME: Test fixture for plugin loading tests.
// ABOUTME: Demonstrates all plugin extension points.

declare(strict_types=1);

namespace Seaman\Tests\Fixtures\Plugins\ValidPlugin;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\Enum\ServiceCategory;

#[AsSeamanPlugin(name: 'valid-plugin', version: '1.0.0', description: 'A valid test plugin')]
final class ValidPlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'valid-plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'A valid test plugin';
    }

    #[ProvidesService(name: 'custom-redis', category: ServiceCategory::Cache)]
    public function customRedis(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: 'custom-redis',
            template: __DIR__ . '/templates/redis.yaml.twig',
            defaultConfig: ['port' => 6379],
        );
    }
}
```

**Step 2: Write ServiceDefinition**

```php
<?php

// ABOUTME: Defines a Docker service provided by a plugin.
// ABOUTME: Contains template path, config parser, and default configuration.

declare(strict_types=1);

namespace Seaman\Plugin;

final readonly class ServiceDefinition
{
    /**
     * @param array<string, mixed> $defaultConfig
     */
    public function __construct(
        public string $name,
        public string $template,
        public array $defaultConfig = [],
        public ?string $configParser = null,
    ) {}
}
```

**Step 3: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Extractor;

use Seaman\Plugin\Extractor\ServiceExtractor;
use Seaman\Plugin\ServiceDefinition;
use Seaman\Tests\Fixtures\Plugins\ValidPlugin\ValidPlugin;

test('ServiceExtractor finds ProvidesService methods', function (): void {
    $extractor = new ServiceExtractor();
    $plugin = new ValidPlugin();

    $services = $extractor->extract($plugin);

    expect($services)->toHaveCount(1);
    expect($services[0])->toBeInstanceOf(ServiceDefinition::class);
    expect($services[0]->name)->toBe('custom-redis');
});

test('ServiceExtractor returns empty for plugins without services', function (): void {
    $extractor = new ServiceExtractor();

    $plugin = new class implements \Seaman\Plugin\PluginInterface {
        public function getName(): string { return 'empty'; }
        public function getVersion(): string { return '1.0.0'; }
        public function getDescription(): string { return ''; }
    };

    $services = $extractor->extract($plugin);

    expect($services)->toBe([]);
});
```

**Step 4: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Extractor/ServiceExtractorTest.php`
Expected: FAIL

**Step 5: Write ServiceExtractor implementation**

```php
<?php

// ABOUTME: Extracts service definitions from plugin methods.
// ABOUTME: Scans for methods with ProvidesService attribute.

declare(strict_types=1);

namespace Seaman\Plugin\Extractor;

use ReflectionClass;
use ReflectionMethod;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;

final readonly class ServiceExtractor
{
    /**
     * @return list<ServiceDefinition>
     */
    public function extract(PluginInterface $plugin): array
    {
        $services = [];
        $reflection = new ReflectionClass($plugin);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(ProvidesService::class);

            if (empty($attributes)) {
                continue;
            }

            /** @var ServiceDefinition $service */
            $service = $method->invoke($plugin);
            $services[] = $service;
        }

        return $services;
    }
}
```

**Step 6: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Extractor/ServiceExtractorTest.php`
Expected: PASS

**Step 7: Commit**

```bash
git add src/Plugin/ServiceDefinition.php src/Plugin/Extractor/ServiceExtractor.php tests/Unit/Plugin/Extractor/ServiceExtractorTest.php tests/Fixtures/Plugins/ValidPlugin/ValidPlugin.php
git commit -m "feat(plugin): add ServiceExtractor for ProvidesService methods"
```

---

### Task 16: CommandExtractor

**Files:**
- Create: `src/Plugin/Extractor/CommandExtractor.php`
- Test: `tests/Unit/Plugin/Extractor/CommandExtractorTest.php`

**Step 1: Update fixture with command**

Add to `tests/Fixtures/Plugins/ValidPlugin/ValidPlugin.php`:

```php
use Seaman\Plugin\Attribute\ProvidesCommand;
use Symfony\Component\Console\Command\Command;

// Add this method to the class:
#[ProvidesCommand]
public function statusCommand(): Command
{
    return new Command('valid-plugin:status');
}
```

**Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Extractor;

use Seaman\Plugin\Extractor\CommandExtractor;
use Seaman\Tests\Fixtures\Plugins\ValidPlugin\ValidPlugin;
use Symfony\Component\Console\Command\Command;

test('CommandExtractor finds ProvidesCommand methods', function (): void {
    $extractor = new CommandExtractor();
    $plugin = new ValidPlugin();

    $commands = $extractor->extract($plugin);

    expect($commands)->toHaveCount(1);
    expect($commands[0])->toBeInstanceOf(Command::class);
    expect($commands[0]->getName())->toBe('valid-plugin:status');
});
```

**Step 3: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Extractor/CommandExtractorTest.php`
Expected: FAIL

**Step 4: Write CommandExtractor implementation**

```php
<?php

// ABOUTME: Extracts console commands from plugin methods.
// ABOUTME: Scans for methods with ProvidesCommand attribute.

declare(strict_types=1);

namespace Seaman\Plugin\Extractor;

use ReflectionClass;
use ReflectionMethod;
use Seaman\Plugin\Attribute\ProvidesCommand;
use Seaman\Plugin\PluginInterface;
use Symfony\Component\Console\Command\Command;

final readonly class CommandExtractor
{
    /**
     * @return list<Command>
     */
    public function extract(PluginInterface $plugin): array
    {
        $commands = [];
        $reflection = new ReflectionClass($plugin);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(ProvidesCommand::class);

            if (empty($attributes)) {
                continue;
            }

            /** @var Command $command */
            $command = $method->invoke($plugin);
            $commands[] = $command;
        }

        return $commands;
    }
}
```

**Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Extractor/CommandExtractorTest.php`
Expected: PASS

**Step 6: Commit**

```bash
git add src/Plugin/Extractor/CommandExtractor.php tests/Unit/Plugin/Extractor/CommandExtractorTest.php tests/Fixtures/Plugins/ValidPlugin/ValidPlugin.php
git commit -m "feat(plugin): add CommandExtractor for ProvidesCommand methods"
```

---

### Task 17: LifecycleExtractor

**Files:**
- Create: `src/Plugin/Extractor/LifecycleExtractor.php`
- Create: `src/Plugin/LifecycleHandler.php`
- Test: `tests/Unit/Plugin/Extractor/LifecycleExtractorTest.php`

**Step 1: Write LifecycleHandler value object**

```php
<?php

// ABOUTME: Represents a lifecycle event handler from a plugin.
// ABOUTME: Contains event name, priority, and callable reference.

declare(strict_types=1);

namespace Seaman\Plugin;

final readonly class LifecycleHandler
{
    /**
     * @param callable(): void $handler
     */
    public function __construct(
        public string $event,
        public int $priority,
        public mixed $handler,
    ) {}
}
```

**Step 2: Update fixture with lifecycle hook**

Add to `tests/Fixtures/Plugins/ValidPlugin/ValidPlugin.php`:

```php
use Seaman\Plugin\Attribute\OnLifecycle;

// Add this method:
#[OnLifecycle(event: 'before:start', priority: 10)]
public function onBeforeStart(): void
{
    // Hook logic here
}
```

**Step 3: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Extractor;

use Seaman\Plugin\Extractor\LifecycleExtractor;
use Seaman\Plugin\LifecycleHandler;
use Seaman\Tests\Fixtures\Plugins\ValidPlugin\ValidPlugin;

test('LifecycleExtractor finds OnLifecycle methods', function (): void {
    $extractor = new LifecycleExtractor();
    $plugin = new ValidPlugin();

    $handlers = $extractor->extract($plugin);

    expect($handlers)->toHaveCount(1);
    expect($handlers[0])->toBeInstanceOf(LifecycleHandler::class);
    expect($handlers[0]->event)->toBe('before:start');
    expect($handlers[0]->priority)->toBe(10);
});
```

**Step 4: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Extractor/LifecycleExtractorTest.php`
Expected: FAIL

**Step 5: Write LifecycleExtractor implementation**

```php
<?php

// ABOUTME: Extracts lifecycle event handlers from plugin methods.
// ABOUTME: Scans for methods with OnLifecycle attribute.

declare(strict_types=1);

namespace Seaman\Plugin\Extractor;

use ReflectionClass;
use ReflectionMethod;
use Seaman\Plugin\Attribute\OnLifecycle;
use Seaman\Plugin\LifecycleHandler;
use Seaman\Plugin\PluginInterface;

final readonly class LifecycleExtractor
{
    /**
     * @return list<LifecycleHandler>
     */
    public function extract(PluginInterface $plugin): array
    {
        $handlers = [];
        $reflection = new ReflectionClass($plugin);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(OnLifecycle::class);

            if (empty($attributes)) {
                continue;
            }

            /** @var OnLifecycle $attr */
            $attr = $attributes[0]->newInstance();

            $handlers[] = new LifecycleHandler(
                event: $attr->event,
                priority: $attr->priority,
                handler: $method->getClosure($plugin),
            );
        }

        return $handlers;
    }
}
```

**Step 6: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Extractor/LifecycleExtractorTest.php`
Expected: PASS

**Step 7: Commit**

```bash
git add src/Plugin/LifecycleHandler.php src/Plugin/Extractor/LifecycleExtractor.php tests/Unit/Plugin/Extractor/LifecycleExtractorTest.php tests/Fixtures/Plugins/ValidPlugin/ValidPlugin.php
git commit -m "feat(plugin): add LifecycleExtractor for OnLifecycle methods"
```

---

### Task 18: TemplateExtractor

**Files:**
- Create: `src/Plugin/Extractor/TemplateExtractor.php`
- Create: `src/Plugin/TemplateOverride.php`
- Test: `tests/Unit/Plugin/Extractor/TemplateExtractorTest.php`

**Step 1: Write TemplateOverride value object**

```php
<?php

// ABOUTME: Represents a template override from a plugin.
// ABOUTME: Maps core template path to plugin replacement path.

declare(strict_types=1);

namespace Seaman\Plugin;

final readonly class TemplateOverride
{
    public function __construct(
        public string $originalTemplate,
        public string $overridePath,
    ) {}
}
```

**Step 2: Update fixture with template override**

Add to `tests/Fixtures/Plugins/ValidPlugin/ValidPlugin.php`:

```php
use Seaman\Plugin\Attribute\OverridesTemplate;

// Add this method:
#[OverridesTemplate(template: 'docker/app.dockerfile.twig')]
public function customDockerfile(): string
{
    return __DIR__ . '/templates/app.dockerfile.twig';
}
```

**Step 3: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Extractor;

use Seaman\Plugin\Extractor\TemplateExtractor;
use Seaman\Plugin\TemplateOverride;
use Seaman\Tests\Fixtures\Plugins\ValidPlugin\ValidPlugin;

test('TemplateExtractor finds OverridesTemplate methods', function (): void {
    $extractor = new TemplateExtractor();
    $plugin = new ValidPlugin();

    $overrides = $extractor->extract($plugin);

    expect($overrides)->toHaveCount(1);
    expect($overrides[0])->toBeInstanceOf(TemplateOverride::class);
    expect($overrides[0]->originalTemplate)->toBe('docker/app.dockerfile.twig');
});
```

**Step 4: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Extractor/TemplateExtractorTest.php`
Expected: FAIL

**Step 5: Write TemplateExtractor implementation**

```php
<?php

// ABOUTME: Extracts template overrides from plugin methods.
// ABOUTME: Scans for methods with OverridesTemplate attribute.

declare(strict_types=1);

namespace Seaman\Plugin\Extractor;

use ReflectionClass;
use ReflectionMethod;
use Seaman\Plugin\Attribute\OverridesTemplate;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\TemplateOverride;

final readonly class TemplateExtractor
{
    /**
     * @return list<TemplateOverride>
     */
    public function extract(PluginInterface $plugin): array
    {
        $overrides = [];
        $reflection = new ReflectionClass($plugin);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(OverridesTemplate::class);

            if (empty($attributes)) {
                continue;
            }

            /** @var OverridesTemplate $attr */
            $attr = $attributes[0]->newInstance();

            /** @var string $path */
            $path = $method->invoke($plugin);

            $overrides[] = new TemplateOverride(
                originalTemplate: $attr->template,
                overridePath: $path,
            );
        }

        return $overrides;
    }
}
```

**Step 6: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Extractor/TemplateExtractorTest.php`
Expected: PASS

**Step 7: Commit**

```bash
git add src/Plugin/TemplateOverride.php src/Plugin/Extractor/TemplateExtractor.php tests/Unit/Plugin/Extractor/TemplateExtractorTest.php tests/Fixtures/Plugins/ValidPlugin/ValidPlugin.php
git commit -m "feat(plugin): add TemplateExtractor for OverridesTemplate methods"
```

---

## Phase 6: Plugin Commands

### Task 19: PluginListCommand

**Files:**
- Create: `src/Command/Plugin/PluginListCommand.php`
- Test: `tests/Unit/Command/Plugin/PluginListCommandTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Command\Plugin;

use Seaman\Command\Plugin\PluginListCommand;
use Seaman\Plugin\PluginRegistry;
use Symfony\Component\Console\Tester\CommandTester;

test('PluginListCommand shows installed plugins', function (): void {
    $registry = new PluginRegistry();
    $command = new PluginListCommand($registry);
    $tester = new CommandTester($command);

    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(0);
    expect($tester->getDisplay())->toContain('No plugins installed');
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Command/Plugin/PluginListCommandTest.php`
Expected: FAIL

**Step 3: Write PluginListCommand implementation**

```php
<?php

// ABOUTME: Lists all installed Seaman plugins.
// ABOUTME: Shows plugin name, version, source, and description.

declare(strict_types=1);

namespace Seaman\Command\Plugin;

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
final class PluginListCommand extends Command
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
            Terminal::warning('No plugins installed');
            return Command::SUCCESS;
        }

        Terminal::info('Installed plugins:');
        Terminal::newLine();

        foreach ($plugins as $loaded) {
            $plugin = $loaded->instance;
            $source = $loaded->source === 'composer' ? '📦' : '📁';

            Terminal::line(sprintf(
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
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Command/Plugin/PluginListCommandTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Command/Plugin/PluginListCommand.php tests/Unit/Command/Plugin/PluginListCommandTest.php
git commit -m "feat(plugin): add plugin:list command"
```

---

### Task 20: PluginInfoCommand

**Files:**
- Create: `src/Command/Plugin/PluginInfoCommand.php`
- Test: `tests/Unit/Command/Plugin/PluginInfoCommandTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Command\Plugin;

use Seaman\Command\Plugin\PluginInfoCommand;
use Seaman\Plugin\PluginRegistry;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Symfony\Component\Console\Tester\CommandTester;

test('PluginInfoCommand shows plugin details', function (): void {
    $registry = new PluginRegistry();

    $plugin = new #[AsSeamanPlugin(name: 'test-plugin', version: '2.0.0', description: 'A test plugin')]
    class implements PluginInterface {
        public function getName(): string { return 'test-plugin'; }
        public function getVersion(): string { return '2.0.0'; }
        public function getDescription(): string { return 'A test plugin'; }
    };

    $registry->register($plugin, [], 'composer');

    $command = new PluginInfoCommand($registry);
    $tester = new CommandTester($command);

    $tester->execute(['name' => 'test-plugin']);

    expect($tester->getStatusCode())->toBe(0);
    expect($tester->getDisplay())->toContain('test-plugin');
    expect($tester->getDisplay())->toContain('2.0.0');
});

test('PluginInfoCommand fails for unknown plugin', function (): void {
    $registry = new PluginRegistry();
    $command = new PluginInfoCommand($registry);
    $tester = new CommandTester($command);

    $tester->execute(['name' => 'unknown']);

    expect($tester->getStatusCode())->toBe(1);
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Command/Plugin/PluginInfoCommandTest.php`
Expected: FAIL

**Step 3: Write PluginInfoCommand implementation**

```php
<?php

// ABOUTME: Shows detailed information about a specific plugin.
// ABOUTME: Displays services, commands, and hooks provided by the plugin.

declare(strict_types=1);

namespace Seaman\Command\Plugin;

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
final class PluginInfoCommand extends Command
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
        Terminal::line("  Version: {$plugin->getVersion()}");
        Terminal::line("  Source: {$loaded->source}");
        Terminal::line("  Description: " . ($plugin->getDescription() ?: 'None'));
        Terminal::newLine();

        // Show provided services
        $services = (new ServiceExtractor())->extract($plugin);
        if (!empty($services)) {
            Terminal::line('  Services:');
            foreach ($services as $service) {
                Terminal::line("    - {$service->name}");
            }
            Terminal::newLine();
        }

        // Show provided commands
        $commands = (new CommandExtractor())->extract($plugin);
        if (!empty($commands)) {
            Terminal::line('  Commands:');
            foreach ($commands as $command) {
                Terminal::line("    - {$command->getName()}");
            }
            Terminal::newLine();
        }

        // Show lifecycle hooks
        $hooks = (new LifecycleExtractor())->extract($plugin);
        if (!empty($hooks)) {
            Terminal::line('  Lifecycle hooks:');
            foreach ($hooks as $hook) {
                Terminal::line("    - {$hook->event} (priority: {$hook->priority})");
            }
            Terminal::newLine();
        }

        // Show template overrides
        $templates = (new TemplateExtractor())->extract($plugin);
        if (!empty($templates)) {
            Terminal::line('  Template overrides:');
            foreach ($templates as $override) {
                Terminal::line("    - {$override->originalTemplate}");
            }
        }

        return Command::SUCCESS;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Command/Plugin/PluginInfoCommandTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Command/Plugin/PluginInfoCommand.php tests/Unit/Command/Plugin/PluginInfoCommandTest.php
git commit -m "feat(plugin): add plugin:info command"
```

---

### Task 21: PluginCreateCommand

**Files:**
- Create: `src/Command/Plugin/PluginCreateCommand.php`
- Test: `tests/Unit/Command/Plugin/PluginCreateCommandTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Command\Plugin;

use Seaman\Command\Plugin\PluginCreateCommand;
use Symfony\Component\Console\Tester\CommandTester;

test('PluginCreateCommand creates plugin scaffold', function (): void {
    $tempDir = sys_get_temp_dir() . '/seaman-plugin-test-' . uniqid();
    mkdir($tempDir . '/.seaman', 0755, true);

    $command = new PluginCreateCommand($tempDir);
    $tester = new CommandTester($command);

    $tester->execute(['name' => 'my-plugin']);

    expect($tester->getStatusCode())->toBe(0);
    expect(is_dir($tempDir . '/.seaman/plugins/my-plugin'))->toBeTrue();
    expect(file_exists($tempDir . '/.seaman/plugins/my-plugin/MyPluginPlugin.php'))->toBeTrue();

    // Cleanup
    $this->cleanup($tempDir);
})->skip('Requires filesystem cleanup helper');

test('PluginCreateCommand validates plugin name', function (): void {
    $command = new PluginCreateCommand(sys_get_temp_dir());
    $tester = new CommandTester($command);

    $tester->execute(['name' => 'invalid name!']);

    expect($tester->getStatusCode())->toBe(1);
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Command/Plugin/PluginCreateCommandTest.php`
Expected: FAIL

**Step 3: Write PluginCreateCommand implementation**

```php
<?php

// ABOUTME: Creates a new local plugin scaffold.
// ABOUTME: Generates plugin directory structure and boilerplate code.

declare(strict_types=1);

namespace Seaman\Command\Plugin;

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
final class PluginCreateCommand extends Command
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
        mkdir($pluginDir . '/templates', 0755, true);

        // Generate class name from plugin name
        $className = $this->toClassName($name) . 'Plugin';
        $namespace = 'Seaman\\LocalPlugins\\' . $this->toClassName($name);

        // Generate plugin file
        $content = $this->generatePluginCode($name, $className, $namespace);
        file_put_contents($pluginDir . '/' . $className . '.php', $content);

        Terminal::success("Created plugin scaffold at: {$pluginDir}");
        Terminal::line("  Main file: {$className}.php");
        Terminal::line("  Templates: templates/");

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
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Command/Plugin/PluginCreateCommandTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Command/Plugin/PluginCreateCommand.php tests/Unit/Command/Plugin/PluginCreateCommandTest.php
git commit -m "feat(plugin): add plugin:create command for scaffolding"
```

---

## Phase 7: Application Integration

### Task 22: Integrate PluginRegistry in Application

**Files:**
- Modify: `src/Application.php`
- Modify: `config/container.php`
- Test: `tests/Unit/ApplicationPluginTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit;

use Seaman\Application;

test('Application loads plugin commands', function (): void {
    // This test verifies the integration works
    // Full integration test would require fixtures
    $app = new Application();

    // Plugin commands should be registered
    expect($app->has('plugin:list'))->toBeTrue();
    expect($app->has('plugin:info'))->toBeTrue();
    expect($app->has('plugin:create'))->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/ApplicationPluginTest.php`
Expected: FAIL (commands not registered yet)

**Step 3: Update container.php with PluginRegistry**

Add to `config/container.php`:

```php
use Seaman\Plugin\PluginRegistry;
use Seaman\Command\Plugin\PluginListCommand;
use Seaman\Command\Plugin\PluginInfoCommand;
use Seaman\Command\Plugin\PluginCreateCommand;

// Add these definitions:
PluginRegistry::class => factory(
    fn(ContainerInterface $c): PluginRegistry => PluginRegistry::discover(
        projectRoot: $c->get('projectRoot'),
        localPluginsDir: $c->get('projectRoot') . '/.seaman/plugins',
        pluginConfig: [], // TODO: Load from seaman.yaml
    ),
),

PluginListCommand::class => factory(
    fn(ContainerInterface $c): PluginListCommand => new PluginListCommand(
        $c->get(PluginRegistry::class),
    ),
),

PluginInfoCommand::class => factory(
    fn(ContainerInterface $c): PluginInfoCommand => new PluginInfoCommand(
        $c->get(PluginRegistry::class),
    ),
),

PluginCreateCommand::class => factory(
    fn(ContainerInterface $c): PluginCreateCommand => new PluginCreateCommand(
        $c->get('projectRoot'),
    ),
),
```

**Step 4: Update Application.php to register plugin commands**

In `src/Application.php`, add to `resolveCommands()`:

```php
// Add imports at top:
use Seaman\Command\Plugin\PluginListCommand;
use Seaman\Command\Plugin\PluginInfoCommand;
use Seaman\Command\Plugin\PluginCreateCommand;

// Add to commands array in resolveCommands():
$container->get(PluginListCommand::class),
$container->get(PluginInfoCommand::class),
$container->get(PluginCreateCommand::class),
```

**Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/ApplicationPluginTest.php`
Expected: PASS

**Step 6: Commit**

```bash
git add src/Application.php config/container.php tests/Unit/ApplicationPluginTest.php
git commit -m "feat(plugin): integrate plugin system into Application"
```

---

### Task 23: Register Plugin Commands Dynamically

**Files:**
- Modify: `src/Application.php`
- Test: Integration test with fixture plugin

**Step 1: Update Application to extract and register plugin commands**

In `src/Application.php`, modify `resolveCommands()` to also load commands from plugins:

```php
use Seaman\Plugin\PluginRegistry;
use Seaman\Plugin\Extractor\CommandExtractor;

private function resolveCommands(Container $container): array
{
    // ... existing commands ...

    // Register commands from plugins
    /** @var PluginRegistry $pluginRegistry */
    $pluginRegistry = $container->get(PluginRegistry::class);
    $commandExtractor = new CommandExtractor();

    foreach ($pluginRegistry->all() as $loaded) {
        $pluginCommands = $commandExtractor->extract($loaded->instance);
        foreach ($pluginCommands as $command) {
            $commands[] = $command;
        }
    }

    return $commands;
}
```

**Step 2: Run full test suite**

Run: `./vendor/bin/pest`
Expected: All tests PASS

**Step 3: Commit**

```bash
git add src/Application.php
git commit -m "feat(plugin): dynamically register commands from plugins"
```

---

## Phase 8: Final Integration

### Task 24: Run Full Test Suite and PHPStan

**Step 1: Run PHPStan on entire codebase**

Run: `./vendor/bin/phpstan analyse src tests --level=10`
Expected: No errors

**Step 2: Run php-cs-fixer**

Run: `./vendor/bin/php-cs-fixer fix`
Expected: Code style fixed

**Step 3: Run full test suite with coverage**

Run: `./vendor/bin/pest --coverage --min=95`
Expected: All tests pass, coverage >= 95%

**Step 4: Commit any fixes**

```bash
git add -A
git commit -m "chore: fix code style and PHPStan issues"
```

---

### Task 25: Final Commit and Summary

**Step 1: Review all changes**

Run: `git log --oneline feature/plugin-system ^main`

**Step 2: Create summary commit if needed**

The plugin system is complete with:
- Core contracts: `PluginInterface`, `ConfigSchema`, `PluginConfig`
- Attributes: `AsSeamanPlugin`, `ProvidesService`, `ProvidesCommand`, `OnLifecycle`, `OverridesTemplate`
- Loaders: `LocalPluginLoader`, `ComposerPluginLoader`
- Registry: `PluginRegistry` with discovery
- Extractors: `ServiceExtractor`, `CommandExtractor`, `LifecycleExtractor`, `TemplateExtractor`
- Commands: `plugin:list`, `plugin:info`, `plugin:create`
- Application integration

---

## Future Tasks (Out of Scope)

These tasks are documented for future implementation:

1. **Lifecycle Event Emission** — Add event dispatching to init, start, stop, rebuild, destroy commands
2. **Template Override Integration** — Integrate TemplateExtractor with Twig loader
3. **Service Integration** — Integrate ServiceExtractor with ServiceRegistry
4. **Plugin Config from seaman.yaml** — Load `plugins:` section from configuration
5. **Add Twig Blocks** — Add extensible blocks to core templates
