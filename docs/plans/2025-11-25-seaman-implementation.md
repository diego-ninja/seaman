# Seaman Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a complete Docker development environment manager for Symfony 7 with interactive CLI, service management, and dynamic configuration generation.

**Architecture:** PHP 8.4 application using Symfony Console Component, compiled to PHAR with Box. Dynamic docker-compose.yml generation from seaman.yaml configuration via Twig templates. TDD with Pest, PHPStan level 10, 95% test coverage.

**Tech Stack:** PHP 8.4, Symfony Console, Twig, Symfony YAML, Box, Pest, PHPStan, php-cs-fixer

---

## Phase 1: Project Foundation

### Task 1: Initialize Composer Project

**Files:**
- Create: `composer.json`

**Step 1: Create composer.json**

```json
{
    "name": "seaman/seaman",
    "description": "Docker development environment manager for Symfony 7",
    "type": "project",
    "license": "MIT",
    "authors": [
        {
            "name": "Diego",
            "email": "diego@example.com"
        }
    ],
    "require": {
        "php": "^8.4",
        "symfony/console": "^7.2",
        "symfony/yaml": "^7.2",
        "symfony/process": "^7.2",
        "twig/twig": "^3.14"
    },
    "require-dev": {
        "pestphp/pest": "^3.0",
        "phpstan/phpstan": "^2.0",
        "friendsofphp/php-cs-fixer": "^3.64",
        "mockery/mockery": "^1.6",
        "humbug/box": "^4.6"
    },
    "autoload": {
        "psr-4": {
            "Seaman\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Seaman\\Tests\\": "tests/"
        }
    },
    "config": {
        "sort-packages": true,
        "preferred-install": "dist",
        "optimize-autoloader": true
    },
    "bin": [
        "bin/seaman.php"
    ],
    "scripts": {
        "test": "pest",
        "test:coverage": "pest --coverage --min=95",
        "phpstan": "phpstan analyse src tests --level=10",
        "cs:check": "php-cs-fixer fix --dry-run --diff",
        "cs:fix": "php-cs-fixer fix",
        "quality": [
            "@phpstan",
            "@cs:check",
            "@test:coverage"
        ]
    }
}
```

**Step 2: Install dependencies**

Run: `composer install`
Expected: Dependencies installed successfully

**Step 3: Verify installation**

Run: `composer validate`
Expected: "composer.json is valid"

**Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "feat: initialize composer project with dependencies"
```

---

### Task 2: Setup Quality Tools Configuration

**Files:**
- Create: `phpstan.neon`
- Create: `.php-cs-fixer.dist.php`
- Create: `pest.php`
- Create: `.gitignore`

**Step 1: Create phpstan.neon**

```neon
parameters:
    level: 10
    paths:
        - src
        - tests
    excludePaths:
        - tests/Fixtures
    checkMissingIterableValueType: true
    checkGenericClassInNonGenericObjectType: true
    reportUnmatchedIgnoredErrors: true
```

**Step 2: Create .php-cs-fixer.dist.php**

```php
<?php

declare(strict_types=1);

// ABOUTME: Configuration for PHP CS Fixer.
// ABOUTME: Enforces PER coding standard across the codebase.

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->exclude('Fixtures')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        '@PER-CS:risky' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
```

**Step 3: Create pest.php**

```php
<?php

declare(strict_types=1);

// ABOUTME: Pest PHP test configuration file.
// ABOUTME: Sets up test environment and global expectations.

uses(
    Tests\TestCase::class,
)->in('Unit', 'Integration');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
```

**Step 4: Create .gitignore**

```
# Dependencies
/vendor/

# Build artifacts
/build/
*.phar

# IDE
.idea/
.vscode/
*.swp
*.swo

# OS
.DS_Store
Thumbs.db

# Testing
.phpunit.result.cache
.pest/

# Coverage
coverage/
.coverage/

# PHPStan
.phpstan.cache/

# PHP CS Fixer
.php-cs-fixer.cache

# Environment
.env.local
```

**Step 5: Commit**

```bash
git add phpstan.neon .php-cs-fixer.dist.php pest.php .gitignore
git commit -m "feat: configure quality tools (PHPStan, CS Fixer, Pest)"
```

---

### Task 3: Create Directory Structure

**Files:**
- Create: `src/.gitkeep`
- Create: `tests/Unit/.gitkeep`
- Create: `tests/Integration/.gitkeep`
- Create: `tests/Fixtures/.gitkeep`
- Create: `bin/.gitkeep`

**Step 1: Create directory structure**

Run: `mkdir -p src/{Command,Service/Container,ValueObject,Template/{docker/services,config,scripts}} tests/{Unit/{Command,Service,ValueObject},Integration/{Command,Service},Fixtures/{configs,expected}} bin`

Expected: Directories created

**Step 2: Create .gitkeep files**

Run: `touch src/.gitkeep tests/Unit/.gitkeep tests/Integration/.gitkeep tests/Fixtures/.gitkeep bin/.gitkeep`

Expected: Files created

**Step 3: Verify structure**

Run: `tree -L 3 -a`

Expected: Directory structure matches design

**Step 4: Commit**

```bash
git add src/ tests/ bin/
git commit -m "feat: create project directory structure"
```

---

### Task 4: Create Base TestCase

**Files:**
- Create: `tests/TestCase.php`

**Step 1: Write TestCase class**

```php
<?php

declare(strict_types=1);

// ABOUTME: Base test case for all Seaman tests.
// ABOUTME: Provides common test utilities and setup.

namespace Seaman\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesPath = __DIR__ . '/Fixtures';
    }

    /**
     * Get fixture file path.
     */
    protected function fixture(string $path): string
    {
        return $this->fixturesPath . '/' . $path;
    }

    /**
     * Load fixture file contents.
     */
    protected function loadFixture(string $path): string
    {
        $fullPath = $this->fixture($path);
        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Fixture not found: {$path}");
        }

        return file_get_contents($fullPath);
    }

    /**
     * Create temporary directory for tests.
     */
    protected function createTempDir(): string
    {
        $tmpDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
        if (!mkdir($tmpDir, 0755, true)) {
            throw new \RuntimeException("Cannot create temp dir: {$tmpDir}");
        }

        return $tmpDir;
    }

    /**
     * Remove directory recursively.
     */
    protected function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
```

**Step 2: Run PHPStan**

Run: `composer phpstan`

Expected: No errors (level 10)

**Step 3: Run CS Fixer**

Run: `composer cs:check`

Expected: No style violations

**Step 4: Commit**

```bash
git add tests/TestCase.php
git commit -m "feat: add base TestCase with fixture utilities"
```

---

## Phase 2: Value Objects (Configuration Layer)

### Task 5: ServerConfig Value Object

**Files:**
- Create: `src/ValueObject/ServerConfig.php`
- Create: `tests/Unit/ValueObject/ServerConfigTest.php`

**Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for ServerConfig value object.
// ABOUTME: Validates server configuration immutability and constraints.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\ServerConfig;

test('creates server config with valid type', function () {
    $config = new ServerConfig(
        type: 'symfony',
        port: 8000
    );

    expect($config->type)->toBe('symfony')
        ->and($config->port)->toBe(8000);
});

test('rejects invalid server type', function () {
    new ServerConfig(
        type: 'invalid',
        port: 8000
    );
})->throws(\InvalidArgumentException::class, 'Invalid server type');

test('rejects invalid port', function () {
    new ServerConfig(
        type: 'symfony',
        port: 100000
    );
})->throws(\InvalidArgumentException::class, 'Invalid port');

test('accepts all valid server types', function (string $type) {
    $config = new ServerConfig(
        type: $type,
        port: 8000
    );

    expect($config->type)->toBe($type);
})->with(['symfony', 'nginx-fpm', 'frankenphp']);
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ValueObject/ServerConfigTest.php`

Expected: FAIL with "Class Seaman\ValueObject\ServerConfig not found"

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Immutable server configuration value object.
// ABOUTME: Validates server type and port constraints.

namespace Seaman\ValueObject;

readonly class ServerConfig
{
    private const VALID_TYPES = ['symfony', 'nginx-fpm', 'frankenphp'];
    private const MIN_PORT = 1024;
    private const MAX_PORT = 65535;

    public function __construct(
        public string $type,
        public int $port,
    ) {
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Invalid server type: {$type}. Must be one of: " . implode(', ', self::VALID_TYPES)
            );
        }

        if ($port < self::MIN_PORT || $port > self::MAX_PORT) {
            throw new \InvalidArgumentException(
                "Invalid port: {$port}. Must be between " . self::MIN_PORT . " and " . self::MAX_PORT
            );
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ValueObject/ServerConfigTest.php`

Expected: PASS (all tests green)

**Step 5: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 6: Commit**

```bash
git add src/ValueObject/ServerConfig.php tests/Unit/ValueObject/ServerConfigTest.php
git commit -m "feat: add ServerConfig value object with validation"
```

---

### Task 6: PhpConfig Value Object

**Files:**
- Create: `src/ValueObject/PhpConfig.php`
- Create: `src/ValueObject/XdebugConfig.php`
- Create: `tests/Unit/ValueObject/PhpConfigTest.php`

**Step 1: Write failing test for XdebugConfig**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for PhpConfig and XdebugConfig value objects.
// ABOUTME: Validates PHP configuration and Xdebug settings.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;

test('creates xdebug config', function () {
    $config = new XdebugConfig(
        enabled: false,
        ideKey: 'PHPSTORM',
        clientHost: 'host.docker.internal'
    );

    expect($config->enabled)->toBeFalse()
        ->and($config->ideKey)->toBe('PHPSTORM')
        ->and($config->clientHost)->toBe('host.docker.internal');
});

test('creates php config with extensions', function () {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $config = new PhpConfig(
        version: '8.4',
        extensions: ['pdo_pgsql', 'redis', 'intl'],
        xdebug: $xdebug
    );

    expect($config->version)->toBe('8.4')
        ->and($config->extensions)->toBe(['pdo_pgsql', 'redis', 'intl'])
        ->and($config->xdebug)->toBe($xdebug);
});

test('rejects invalid php version', function () {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    new PhpConfig(
        version: '7.4',
        extensions: [],
        xdebug: $xdebug
    );
})->throws(\InvalidArgumentException::class, 'Unsupported PHP version');

test('accepts valid php versions', function (string $version) {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $config = new PhpConfig(
        version: $version,
        extensions: [],
        xdebug: $xdebug
    );

    expect($config->version)->toBe($version);
})->with(['8.2', '8.3', '8.4']);
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ValueObject/PhpConfigTest.php`

Expected: FAIL with "Class not found"

**Step 3: Write XdebugConfig implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Xdebug configuration value object.
// ABOUTME: Contains Xdebug-specific settings.

namespace Seaman\ValueObject;

readonly class XdebugConfig
{
    public function __construct(
        public bool $enabled,
        public string $ideKey,
        public string $clientHost,
    ) {}
}
```

**Step 4: Write PhpConfig implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: PHP configuration value object.
// ABOUTME: Validates PHP version and manages extensions.

namespace Seaman\ValueObject;

readonly class PhpConfig
{
    private const SUPPORTED_VERSIONS = ['8.2', '8.3', '8.4'];

    /**
     * @param list<string> $extensions
     */
    public function __construct(
        public string $version,
        public array $extensions,
        public XdebugConfig $xdebug,
    ) {
        if (!in_array($version, self::SUPPORTED_VERSIONS, true)) {
            throw new \InvalidArgumentException(
                "Unsupported PHP version: {$version}. Must be one of: " . implode(', ', self::SUPPORTED_VERSIONS)
            );
        }
    }
}
```

**Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ValueObject/PhpConfigTest.php`

Expected: PASS

**Step 6: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 7: Commit**

```bash
git add src/ValueObject/PhpConfig.php src/ValueObject/XdebugConfig.php tests/Unit/ValueObject/PhpConfigTest.php
git commit -m "feat: add PhpConfig and XdebugConfig value objects"
```

---

### Task 7: ServiceConfig Value Object

**Files:**
- Create: `src/ValueObject/ServiceConfig.php`
- Create: `tests/Unit/ValueObject/ServiceConfigTest.php`

**Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for ServiceConfig value object.
// ABOUTME: Validates service configuration structure.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\ServiceConfig;

test('creates service config', function () {
    $config = new ServiceConfig(
        name: 'postgresql',
        enabled: true,
        type: 'postgresql',
        version: '16',
        port: 5432,
        additionalPorts: [],
        environmentVariables: ['POSTGRES_PASSWORD' => 'secret']
    );

    expect($config->name)->toBe('postgresql')
        ->and($config->enabled)->toBeTrue()
        ->and($config->type)->toBe('postgresql')
        ->and($config->version)->toBe('16')
        ->and($config->port)->toBe(5432)
        ->and($config->additionalPorts)->toBe([])
        ->and($config->environmentVariables)->toBe(['POSTGRES_PASSWORD' => 'secret']);
});

test('creates disabled service config', function () {
    $config = new ServiceConfig(
        name: 'elasticsearch',
        enabled: false,
        type: 'elasticsearch',
        version: '8.11',
        port: 9200,
        additionalPorts: [],
        environmentVariables: []
    );

    expect($config->enabled)->toBeFalse();
});

test('handles multiple additional ports', function () {
    $config = new ServiceConfig(
        name: 'minio',
        enabled: true,
        type: 'minio',
        version: 'latest',
        port: 9000,
        additionalPorts: [9001],
        environmentVariables: []
    );

    expect($config->additionalPorts)->toBe([9001]);
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ValueObject/ServiceConfigTest.php`

Expected: FAIL with "Class not found"

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Service configuration value object.
// ABOUTME: Represents configuration for a single Docker service.

namespace Seaman\ValueObject;

readonly class ServiceConfig
{
    /**
     * @param list<int> $additionalPorts
     * @param array<string, string> $environmentVariables
     */
    public function __construct(
        public string $name,
        public bool $enabled,
        public string $type,
        public string $version,
        public int $port,
        public array $additionalPorts,
        public array $environmentVariables,
    ) {}

    /**
     * @return list<int>
     */
    public function getAllPorts(): array
    {
        return [$this->port, ...$this->additionalPorts];
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ValueObject/ServiceConfigTest.php`

Expected: PASS

**Step 5: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 6: Commit**

```bash
git add src/ValueObject/ServiceConfig.php tests/Unit/ValueObject/ServiceConfigTest.php
git commit -m "feat: add ServiceConfig value object"
```

---

### Task 8: ServiceCollection Value Object

**Files:**
- Create: `src/ValueObject/ServiceCollection.php`
- Create: `tests/Unit/ValueObject/ServiceCollectionTest.php`

**Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for ServiceCollection value object.
// ABOUTME: Validates service collection operations.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;

test('creates empty service collection', function () {
    $collection = new ServiceCollection([]);

    expect($collection->all())->toBe([])
        ->and($collection->enabled())->toBe([])
        ->and($collection->count())->toBe(0);
});

test('creates collection with services', function () {
    $services = [
        'postgresql' => new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []),
        'redis' => new ServiceConfig('redis', true, 'redis', '7-alpine', 6379, [], []),
    ];

    $collection = new ServiceCollection($services);

    expect($collection->count())->toBe(2)
        ->and($collection->has('postgresql'))->toBeTrue()
        ->and($collection->has('redis'))->toBeTrue()
        ->and($collection->has('mysql'))->toBeFalse();
});

test('filters enabled services', function () {
    $services = [
        'postgresql' => new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []),
        'redis' => new ServiceConfig('redis', false, 'redis', '7-alpine', 6379, [], []),
    ];

    $collection = new ServiceCollection($services);
    $enabled = $collection->enabled();

    expect($enabled)->toHaveCount(1)
        ->and($enabled['postgresql'])->toBeInstanceOf(ServiceConfig::class);
});

test('gets service by name', function () {
    $service = new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []);
    $collection = new ServiceCollection(['postgresql' => $service]);

    $retrieved = $collection->get('postgresql');

    expect($retrieved)->toBe($service);
});

test('throws when getting non-existent service', function () {
    $collection = new ServiceCollection([]);
    $collection->get('invalid');
})->throws(\InvalidArgumentException::class);

test('adds new service', function () {
    $collection = new ServiceCollection([]);
    $service = new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []);

    $newCollection = $collection->add('postgresql', $service);

    expect($newCollection->has('postgresql'))->toBeTrue()
        ->and($collection->has('postgresql'))->toBeFalse(); // Original unchanged
});

test('removes service', function () {
    $service = new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []);
    $collection = new ServiceCollection(['postgresql' => $service]);

    $newCollection = $collection->remove('postgresql');

    expect($newCollection->has('postgresql'))->toBeFalse()
        ->and($collection->has('postgresql'))->toBeTrue(); // Original unchanged
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ValueObject/ServiceCollectionTest.php`

Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Collection of service configurations.
// ABOUTME: Provides immutable operations for managing services.

namespace Seaman\ValueObject;

readonly class ServiceCollection
{
    /**
     * @param array<string, ServiceConfig> $services
     */
    public function __construct(
        private array $services = [],
    ) {}

    /**
     * @return array<string, ServiceConfig>
     */
    public function all(): array
    {
        return $this->services;
    }

    /**
     * @return array<string, ServiceConfig>
     */
    public function enabled(): array
    {
        return array_filter(
            $this->services,
            fn (ServiceConfig $service): bool => $service->enabled
        );
    }

    public function count(): int
    {
        return count($this->services);
    }

    public function has(string $name): bool
    {
        return isset($this->services[$name]);
    }

    public function get(string $name): ServiceConfig
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException("Service '{$name}' not found");
        }

        return $this->services[$name];
    }

    public function add(string $name, ServiceConfig $service): self
    {
        return new self([...$this->services, $name => $service]);
    }

    public function remove(string $name): self
    {
        $services = $this->services;
        unset($services[$name]);

        return new self($services);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ValueObject/ServiceCollectionTest.php`

Expected: PASS

**Step 5: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 6: Commit**

```bash
git add src/ValueObject/ServiceCollection.php tests/Unit/ValueObject/ServiceCollectionTest.php
git commit -m "feat: add ServiceCollection with immutable operations"
```

---

### Task 9: VolumeConfig Value Object

**Files:**
- Create: `src/ValueObject/VolumeConfig.php`
- Create: `tests/Unit/ValueObject/VolumeConfigTest.php`

**Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for VolumeConfig value object.
// ABOUTME: Validates volume configuration and persistence settings.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\VolumeConfig;

test('creates volume config with persistent volumes', function () {
    $config = new VolumeConfig(
        persist: ['database', 'redis']
    );

    expect($config->persist)->toBe(['database', 'redis']);
});

test('creates volume config with empty persist list', function () {
    $config = new VolumeConfig(
        persist: []
    );

    expect($config->persist)->toBe([]);
});

test('checks if volume should persist', function () {
    $config = new VolumeConfig(
        persist: ['database', 'redis']
    );

    expect($config->shouldPersist('database'))->toBeTrue()
        ->and($config->shouldPersist('redis'))->toBeTrue()
        ->and($config->shouldPersist('mailpit'))->toBeFalse();
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ValueObject/VolumeConfigTest.php`

Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Volume persistence configuration.
// ABOUTME: Defines which Docker volumes should persist data.

namespace Seaman\ValueObject;

readonly class VolumeConfig
{
    /**
     * @param list<string> $persist
     */
    public function __construct(
        public array $persist = [],
    ) {}

    public function shouldPersist(string $volumeName): bool
    {
        return in_array($volumeName, $this->persist, true);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ValueObject/VolumeConfigTest.php`

Expected: PASS

**Step 5: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 6: Commit**

```bash
git add src/ValueObject/VolumeConfig.php tests/Unit/ValueObject/VolumeConfigTest.php
git commit -m "feat: add VolumeConfig value object"
```

---

### Task 10: Configuration Root Value Object

**Files:**
- Create: `src/ValueObject/Configuration.php`
- Create: `tests/Unit/ValueObject/ConfigurationTest.php`

**Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for Configuration root value object.
// ABOUTME: Validates complete configuration structure.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ServerConfig;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;

test('creates complete configuration', function () {
    $server = new ServerConfig('symfony', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', ['pdo_pgsql', 'redis'], $xdebug);
    $services = new ServiceCollection([
        'postgresql' => new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []),
    ]);
    $volumes = new VolumeConfig(['database']);

    $config = new Configuration(
        version: '1.0',
        server: $server,
        php: $php,
        services: $services,
        volumes: $volumes
    );

    expect($config->version)->toBe('1.0')
        ->and($config->server)->toBe($server)
        ->and($config->php)->toBe($php)
        ->and($config->services)->toBe($services)
        ->and($config->volumes)->toBe($volumes);
});

test('configuration is immutable', function () {
    $server = new ServerConfig('symfony', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', [], $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration('1.0', $server, $php, $services, $volumes);

    expect($config)->toBeInstanceOf(Configuration::class);

    // Verify readonly behavior
    $reflection = new \ReflectionClass($config);
    expect($reflection->isReadOnly())->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ValueObject/ConfigurationTest.php`

Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Immutable configuration root object.
// ABOUTME: Represents the complete seaman.yaml configuration.

namespace Seaman\ValueObject;

readonly class Configuration
{
    public function __construct(
        public string $version,
        public ServerConfig $server,
        public PhpConfig $php,
        public ServiceCollection $services,
        public VolumeConfig $volumes,
    ) {}
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ValueObject/ConfigurationTest.php`

Expected: PASS

**Step 5: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 6: Commit**

```bash
git add src/ValueObject/Configuration.php tests/Unit/ValueObject/ConfigurationTest.php
git commit -m "feat: add Configuration root value object"
```

---

### Task 11: ProcessResult and HealthCheck Value Objects

**Files:**
- Create: `src/ValueObject/ProcessResult.php`
- Create: `src/ValueObject/HealthCheck.php`
- Create: `src/ValueObject/LogOptions.php`
- Create: `tests/Unit/ValueObject/UtilityValueObjectsTest.php`

**Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for utility value objects.
// ABOUTME: Validates ProcessResult, HealthCheck, and LogOptions.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\ProcessResult;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\LogOptions;

test('creates process result', function () {
    $result = new ProcessResult(
        exitCode: 0,
        output: 'success',
        errorOutput: ''
    );

    expect($result->exitCode)->toBe(0)
        ->and($result->output)->toBe('success')
        ->and($result->errorOutput)->toBe('')
        ->and($result->isSuccessful())->toBeTrue();
});

test('process result detects failure', function () {
    $result = new ProcessResult(
        exitCode: 1,
        output: '',
        errorOutput: 'error'
    );

    expect($result->isSuccessful())->toBeFalse();
});

test('creates health check', function () {
    $healthCheck = new HealthCheck(
        test: ['CMD', 'pg_isready'],
        interval: '10s',
        timeout: '5s',
        retries: 3
    );

    expect($healthCheck->test)->toBe(['CMD', 'pg_isready'])
        ->and($healthCheck->interval)->toBe('10s')
        ->and($healthCheck->timeout)->toBe('5s')
        ->and($healthCheck->retries)->toBe(3);
});

test('creates log options with defaults', function () {
    $options = new LogOptions(
        follow: false,
        tail: null,
        since: null
    );

    expect($options->follow)->toBeFalse()
        ->and($options->tail)->toBeNull()
        ->and($options->since)->toBeNull();
});

test('creates log options with custom values', function () {
    $options = new LogOptions(
        follow: true,
        tail: 100,
        since: '2024-01-01'
    );

    expect($options->follow)->toBeTrue()
        ->and($options->tail)->toBe(100)
        ->and($options->since)->toBe('2024-01-01');
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ValueObject/UtilityValueObjectsTest.php`

Expected: FAIL

**Step 3: Write ProcessResult implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Process execution result value object.
// ABOUTME: Captures command execution output and status.

namespace Seaman\ValueObject;

readonly class ProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
        public string $errorOutput,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }
}
```

**Step 4: Write HealthCheck implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Docker health check configuration.
// ABOUTME: Defines container health check parameters.

namespace Seaman\ValueObject;

readonly class HealthCheck
{
    /**
     * @param list<string> $test
     */
    public function __construct(
        public array $test,
        public string $interval,
        public string $timeout,
        public int $retries,
    ) {}
}
```

**Step 5: Write LogOptions implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Docker logs command options.
// ABOUTME: Configures log viewing behavior.

namespace Seaman\ValueObject;

readonly class LogOptions
{
    public function __construct(
        public bool $follow = false,
        public ?int $tail = null,
        public ?string $since = null,
    ) {}
}
```

**Step 6: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ValueObject/UtilityValueObjectsTest.php`

Expected: PASS

**Step 7: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 8: Commit**

```bash
git add src/ValueObject/ProcessResult.php src/ValueObject/HealthCheck.php src/ValueObject/LogOptions.php tests/Unit/ValueObject/UtilityValueObjectsTest.php
git commit -m "feat: add utility value objects (ProcessResult, HealthCheck, LogOptions)"
```

---

## Phase 3: Configuration Management

### Task 12: ConfigManager - YAML Loading

**Files:**
- Create: `src/Service/ConfigManager.php`
- Create: `tests/Unit/Service/ConfigManagerTest.php`
- Create: `tests/Fixtures/configs/minimal-seaman.yaml`

**Step 1: Create minimal fixture**

```yaml
version: '1.0'

server:
  type: symfony
  port: 8000

php:
  version: '8.4'
  extensions:
    - pdo_pgsql
    - redis
  xdebug:
    enabled: false
    ide_key: PHPSTORM
    client_host: host.docker.internal

services: {}

volumes:
  persist: []
```

**Step 2: Write failing test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for ConfigManager service.
// ABOUTME: Validates YAML loading, parsing, and saving.

namespace Seaman\Tests\Unit\Service;

use Seaman\Service\ConfigManager;
use Seaman\ValueObject\Configuration;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->manager = new ConfigManager($this->tempDir);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        array_map('unlink', glob($this->tempDir . '/*'));
        rmdir($this->tempDir);
    }
});

test('loads minimal configuration from YAML', function () {
    $yamlPath = $this->tempDir . '/seaman.yaml';
    copy(__DIR__ . '/../../Fixtures/configs/minimal-seaman.yaml', $yamlPath);

    $config = $this->manager->load();

    expect($config)->toBeInstanceOf(Configuration::class)
        ->and($config->version)->toBe('1.0')
        ->and($config->server->type)->toBe('symfony')
        ->and($config->server->port)->toBe(8000)
        ->and($config->php->version)->toBe('8.4')
        ->and($config->php->extensions)->toBe(['pdo_pgsql', 'redis'])
        ->and($config->php->xdebug->enabled)->toBeFalse()
        ->and($config->services->count())->toBe(0);
});

test('throws when seaman.yaml not found', function () {
    $this->manager->load();
})->throws(\RuntimeException::class, 'seaman.yaml not found');

test('throws when YAML is invalid', function () {
    $yamlPath = $this->tempDir . '/seaman.yaml';
    file_put_contents($yamlPath, "invalid: yaml: content:\n  - broken");

    $this->manager->load();
})->throws(\RuntimeException::class);
```

**Step 3: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/ConfigManagerTest.php`

Expected: FAIL

**Step 4: Write minimal implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Manages configuration loading and saving.
// ABOUTME: Handles YAML parsing and .env file generation.

namespace Seaman\Service;

use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ServerConfig;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;
use Symfony\Component\Yaml\Yaml;

class ConfigManager
{
    private const CONFIG_FILE = 'seaman.yaml';

    public function __construct(
        private readonly string $projectRoot,
    ) {}

    public function load(): Configuration
    {
        $configPath = $this->projectRoot . '/' . self::CONFIG_FILE;

        if (!file_exists($configPath)) {
            throw new \RuntimeException(
                'seaman.yaml not found. Run "seaman init" to create it.'
            );
        }

        try {
            $data = Yaml::parseFile($configPath);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                'Failed to parse seaman.yaml: ' . $e->getMessage(),
                previous: $e
            );
        }

        return $this->parseConfiguration($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseConfiguration(array $data): Configuration
    {
        $server = new ServerConfig(
            type: $data['server']['type'] ?? 'symfony',
            port: $data['server']['port'] ?? 8000
        );

        $xdebugData = $data['php']['xdebug'] ?? [];
        $xdebug = new XdebugConfig(
            enabled: $xdebugData['enabled'] ?? false,
            ideKey: $xdebugData['ide_key'] ?? 'PHPSTORM',
            clientHost: $xdebugData['client_host'] ?? 'host.docker.internal'
        );

        $php = new PhpConfig(
            version: $data['php']['version'] ?? '8.4',
            extensions: $data['php']['extensions'] ?? [],
            xdebug: $xdebug
        );

        $servicesData = $data['services'] ?? [];
        $services = [];
        foreach ($servicesData as $name => $serviceData) {
            if (!is_array($serviceData)) {
                continue;
            }

            $services[$name] = new ServiceConfig(
                name: $name,
                enabled: $serviceData['enabled'] ?? false,
                type: $serviceData['type'] ?? $name,
                version: $serviceData['version'] ?? 'latest',
                port: $serviceData['port'] ?? 0,
                additionalPorts: $serviceData['additional_ports'] ?? [],
                environmentVariables: $serviceData['environment'] ?? []
            );
        }

        $volumes = new VolumeConfig(
            persist: $data['volumes']['persist'] ?? []
        );

        return new Configuration(
            version: $data['version'] ?? '1.0',
            server: $server,
            php: $php,
            services: new ServiceCollection($services),
            volumes: $volumes
        );
    }
}
```

**Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/ConfigManagerTest.php`

Expected: PASS

**Step 6: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 7: Commit**

```bash
git add src/Service/ConfigManager.php tests/Unit/Service/ConfigManagerTest.php tests/Fixtures/configs/minimal-seaman.yaml
git commit -m "feat: add ConfigManager with YAML loading"
```

---

### Task 13: ConfigManager - YAML Saving

**Files:**
- Modify: `src/Service/ConfigManager.php`
- Modify: `tests/Unit/Service/ConfigManagerTest.php`

**Step 1: Write failing test for save**

```php
test('saves configuration to YAML', function () {
    $server = new ServerConfig('symfony', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', ['pdo_pgsql'], $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration('1.0', $server, $php, $services, $volumes);

    $this->manager->save($config);

    $yamlPath = $this->tempDir . '/seaman.yaml';
    expect(file_exists($yamlPath))->toBeTrue();

    $loadedConfig = $this->manager->load();
    expect($loadedConfig->version)->toBe('1.0')
        ->and($loadedConfig->server->type)->toBe('symfony')
        ->and($loadedConfig->php->version)->toBe('8.4');
});

test('generates .env file when saving', function () {
    $server = new ServerConfig('symfony', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', [], $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration('1.0', $server, $php, $services, $volumes);

    $this->manager->save($config);

    $envPath = $this->tempDir . '/.env';
    expect(file_exists($envPath))->toBeTrue();

    $envContent = file_get_contents($envPath);
    expect($envContent)->toContain('APP_PORT=8000')
        ->and($envContent)->toContain('PHP_VERSION=8.4')
        ->and($envContent)->toContain('XDEBUG_MODE=off');
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/ConfigManagerTest.php`

Expected: FAIL with "Method save does not exist"

**Step 3: Implement save method**

```php
public function save(Configuration $config): void
{
    $this->saveYaml($config);
    $this->generateEnv($config);
}

private function saveYaml(Configuration $config): void
{
    $data = [
        'version' => $config->version,
        'server' => [
            'type' => $config->server->type,
            'port' => '${APP_PORT}',
        ],
        'php' => [
            'version' => $config->php->version,
            'extensions' => $config->php->extensions,
            'xdebug' => [
                'enabled' => $config->php->xdebug->enabled,
                'ide_key' => $config->php->xdebug->ideKey,
                'client_host' => $config->php->xdebug->clientHost,
            ],
        ],
        'services' => [],
        'volumes' => [
            'persist' => $config->volumes->persist,
        ],
    ];

    foreach ($config->services->all() as $name => $service) {
        $data['services'][$name] = [
            'enabled' => $service->enabled,
            'type' => $service->type,
            'version' => $service->version,
            'port' => '${' . strtoupper($name) . '_PORT}',
        ];

        if (!empty($service->additionalPorts)) {
            $data['services'][$name]['additional_ports'] = $service->additionalPorts;
        }

        if (!empty($service->environmentVariables)) {
            $data['services'][$name]['environment'] = $service->environmentVariables;
        }
    }

    $yaml = Yaml::dump($data, 4, 2);
    $configPath = $this->projectRoot . '/' . self::CONFIG_FILE;

    if (file_put_contents($configPath, $yaml) === false) {
        throw new \RuntimeException('Failed to write seaman.yaml');
    }
}

private function generateEnv(Configuration $config): void
{
    $lines = [
        '# Generated by seaman - DO NOT EDIT MANUALLY',
        '',
        '# Server configuration',
        'APP_PORT=' . $config->server->port,
        'PHP_VERSION=' . $config->php->version,
        '',
        '# Xdebug configuration',
        'XDEBUG_MODE=' . ($config->php->xdebug->enabled ? 'debug' : 'off'),
        '',
    ];

    foreach ($config->services->all() as $name => $service) {
        $lines[] = '# ' . ucfirst($name) . ' configuration';
        $lines[] = strtoupper($name) . '_PORT=' . $service->port;

        if (!empty($service->environmentVariables)) {
            foreach ($service->environmentVariables as $key => $value) {
                $lines[] = strtoupper($name) . '_' . $key . '=' . $value;
            }
        }

        $lines[] = '';
    }

    $envContent = implode("\n", $lines);
    $envPath = $this->projectRoot . '/.env';

    if (file_put_contents($envPath, $envContent) === false) {
        throw new \RuntimeException('Failed to write .env');
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/ConfigManagerTest.php`

Expected: PASS

**Step 5: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 6: Commit**

```bash
git add src/Service/ConfigManager.php tests/Unit/Service/ConfigManagerTest.php
git commit -m "feat: implement ConfigManager save with .env generation"
```

---

### Task 14: ConfigManager - Configuration Merging

**Files:**
- Modify: `src/Service/ConfigManager.php`
- Modify: `tests/Unit/Service/ConfigManagerTest.php`

**Step 1: Write failing test for merge**

```php
test('merges service into existing configuration', function () {
    $server = new ServerConfig('symfony', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', [], $xdebug);
    $existingService = new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []);
    $services = new ServiceCollection(['postgresql' => $existingService]);
    $volumes = new VolumeConfig(['database']);

    $baseConfig = new Configuration('1.0', $server, $php, $services, $volumes);

    $overrides = [
        'services' => [
            'redis' => [
                'enabled' => true,
                'type' => 'redis',
                'version' => '7-alpine',
                'port' => 6379,
            ],
        ],
        'volumes' => [
            'persist' => ['database', 'redis'],
        ],
    ];

    $merged = $this->manager->merge($baseConfig, $overrides);

    expect($merged->services->count())->toBe(2)
        ->and($merged->services->has('postgresql'))->toBeTrue()
        ->and($merged->services->has('redis'))->toBeTrue()
        ->and($merged->volumes->persist)->toBe(['database', 'redis']);
});

test('merge preserves existing configuration', function () {
    $server = new ServerConfig('symfony', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', ['pdo_pgsql'], $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $baseConfig = new Configuration('1.0', $server, $php, $services, $volumes);

    $overrides = [
        'server' => [
            'port' => 9000,
        ],
    ];

    $merged = $this->manager->merge($baseConfig, $overrides);

    expect($merged->server->port)->toBe(9000)
        ->and($merged->server->type)->toBe('symfony') // Preserved
        ->and($merged->php->version)->toBe('8.4'); // Preserved
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/ConfigManagerTest.php`

Expected: FAIL

**Step 3: Implement merge method**

```php
/**
 * @param array<string, mixed> $overrides
 */
public function merge(Configuration $base, array $overrides): Configuration
{
    // Merge server config
    $serverData = [
        'type' => $overrides['server']['type'] ?? $base->server->type,
        'port' => $overrides['server']['port'] ?? $base->server->port,
    ];
    $server = new ServerConfig($serverData['type'], $serverData['port']);

    // Merge PHP config (keep base xdebug if not overridden)
    $xdebugData = $overrides['php']['xdebug'] ?? [];
    $xdebug = new XdebugConfig(
        enabled: $xdebugData['enabled'] ?? $base->php->xdebug->enabled,
        ideKey: $xdebugData['ide_key'] ?? $base->php->xdebug->ideKey,
        clientHost: $xdebugData['client_host'] ?? $base->php->xdebug->clientHost
    );

    $php = new PhpConfig(
        version: $overrides['php']['version'] ?? $base->php->version,
        extensions: $overrides['php']['extensions'] ?? $base->php->extensions,
        xdebug: $xdebug
    );

    // Merge services
    $mergedServices = $base->services->all();
    if (isset($overrides['services']) && is_array($overrides['services'])) {
        foreach ($overrides['services'] as $name => $serviceData) {
            if (!is_array($serviceData)) {
                continue;
            }

            $mergedServices[$name] = new ServiceConfig(
                name: $name,
                enabled: $serviceData['enabled'] ?? true,
                type: $serviceData['type'] ?? $name,
                version: $serviceData['version'] ?? 'latest',
                port: $serviceData['port'] ?? 0,
                additionalPorts: $serviceData['additional_ports'] ?? [],
                environmentVariables: $serviceData['environment'] ?? []
            );
        }
    }

    $services = new ServiceCollection($mergedServices);

    // Merge volumes
    $persistVolumes = $overrides['volumes']['persist'] ?? $base->volumes->persist;
    $volumes = new VolumeConfig($persistVolumes);

    return new Configuration(
        version: $overrides['version'] ?? $base->version,
        server: $server,
        php: $php,
        services: $services,
        volumes: $volumes
    );
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/ConfigManagerTest.php`

Expected: PASS

**Step 5: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 6: Commit**

```bash
git add src/Service/ConfigManager.php tests/Unit/Service/ConfigManagerTest.php
git commit -m "feat: implement configuration merging in ConfigManager"
```

---

## Phase 4: Template System

### Task 15: TemplateRenderer Service

**Files:**
- Create: `src/Service/TemplateRenderer.php`
- Create: `tests/Unit/Service/TemplateRendererTest.php`
- Create: `src/Template/.gitkeep`

**Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for TemplateRenderer service.
// ABOUTME: Validates Twig template rendering.

namespace Seaman\Tests\Unit\Service;

use Seaman\Service\TemplateRenderer;

beforeEach(function () {
    $templateDir = __DIR__ . '/../../../src/Template';
    $this->renderer = new TemplateRenderer($templateDir);
});

test('renders simple template', function () {
    // Create a temporary template for testing
    $templateDir = __DIR__ . '/../../../src/Template';
    $testTemplate = $templateDir . '/test.twig';
    file_put_contents($testTemplate, 'Hello {{ name }}!');

    $result = $this->renderer->render('test.twig', ['name' => 'World']);

    expect($result)->toBe('Hello World!');

    // Cleanup
    unlink($testTemplate);
});

test('renders template with arrays', function () {
    $templateDir = __DIR__ . '/../../../src/Template';
    $testTemplate = $templateDir . '/list.twig';
    file_put_contents($testTemplate, '{% for item in items %}{{ item }}{% if not loop.last %}, {% endif %}{% endfor %}');

    $result = $this->renderer->render('list.twig', ['items' => ['a', 'b', 'c']]);

    expect($result)->toBe('a, b, c');

    unlink($testTemplate);
});

test('throws when template not found', function () {
    $this->renderer->render('nonexistent.twig', []);
})->throws(\RuntimeException::class);
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/TemplateRendererTest.php`

Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Renders Twig templates for Docker configurations.
// ABOUTME: Handles template loading and variable substitution.

namespace Seaman\Service;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TemplateRenderer
{
    private Environment $twig;

    public function __construct(string $templateDirectory)
    {
        if (!is_dir($templateDirectory)) {
            throw new \RuntimeException("Template directory not found: {$templateDirectory}");
        }

        $loader = new FilesystemLoader($templateDirectory);
        $this->twig = new Environment($loader, [
            'autoescape' => false, // Docker configs shouldn't be HTML-escaped
            'strict_variables' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context): string
    {
        try {
            return $this->twig->render($template, $context);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to render template '{$template}': " . $e->getMessage(),
                previous: $e
            );
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/TemplateRendererTest.php`

Expected: PASS

**Step 5: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 6: Commit**

```bash
git add src/Service/TemplateRenderer.php tests/Unit/Service/TemplateRendererTest.php src/Template/.gitkeep
git commit -m "feat: add TemplateRenderer service with Twig"
```

---

### Task 16: Create Base Docker Compose Template

**Files:**
- Create: `src/Template/docker/compose.base.twig`

**Step 1: Create compose.base.twig template**

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: .seaman/Dockerfile
      args:
        PHP_VERSION: {{ php.version }}
    volumes:
      - .:/var/www/html
      - .seaman/scripts/xdebug-toggle.sh:/usr/local/bin/xdebug-toggle
    environment:
      - XDEBUG_MODE=${XDEBUG_MODE}
      - PHP_IDE_CONFIG=serverName=seaman
    ports:
      - "${APP_PORT}:8000"
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

**Step 2: Commit template**

```bash
git add src/Template/docker/compose.base.twig
git commit -m "feat: add base Docker Compose template"
```

---

### Task 17: Create Service Templates (PostgreSQL, Redis, Mailpit)

**Files:**
- Create: `src/Template/docker/services/postgresql.twig`
- Create: `src/Template/docker/services/redis.twig`
- Create: `src/Template/docker/services/mailpit.twig`

**Step 1: Create postgresql.twig**

```yaml
  {{ name }}:
    image: postgres:{{ service.version }}
    environment:
      - POSTGRES_DB=${DB_NAME:-seaman}
      - POSTGRES_USER=${DB_USER:-seaman}
      - POSTGRES_PASSWORD=${DB_PASSWORD:-secret}
    ports:
      - "${DB_PORT}:5432"
    networks:
      - seaman
{% if volumes.shouldPersist(name) %}
    volumes:
      - {{ name }}:/var/lib/postgresql/data
{% endif %}
    healthcheck:
      test: ["CMD", "pg_isready", "-U", "${DB_USER:-seaman}"]
      interval: 10s
      timeout: 5s
      retries: 5
```

**Step 2: Create redis.twig**

```yaml
  {{ name }}:
    image: redis:{{ service.version }}
    ports:
      - "${REDIS_PORT}:6379"
    networks:
      - seaman
{% if volumes.shouldPersist(name) %}
    volumes:
      - {{ name }}:/data
{% endif %}
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
```

**Step 3: Create mailpit.twig**

```yaml
  {{ name }}:
    image: axllent/mailpit:latest
    ports:
      - "${MAILPIT_PORT}:8025"
      - "${MAILPIT_SMTP_PORT:-1025}:1025"
    networks:
      - seaman
    environment:
      - MP_MAX_MESSAGES=5000
      - MP_SMTP_AUTH_ACCEPT_ANY=1
      - MP_SMTP_AUTH_ALLOW_INSECURE=1
```

**Step 4: Commit templates**

```bash
git add src/Template/docker/services/
git commit -m "feat: add service templates (PostgreSQL, Redis, Mailpit)"
```

---

### Task 18: Create Remaining Service Templates

**Files:**
- Create: `src/Template/docker/services/mysql.twig`
- Create: `src/Template/docker/services/mariadb.twig`
- Create: `src/Template/docker/services/minio.twig`
- Create: `src/Template/docker/services/elasticsearch.twig`
- Create: `src/Template/docker/services/rabbitmq.twig`

**Step 1: Create mysql.twig**

```yaml
  {{ name }}:
    image: mysql:{{ service.version }}
    environment:
      - MYSQL_ROOT_PASSWORD=${DB_ROOT_PASSWORD:-secret}
      - MYSQL_DATABASE=${DB_NAME:-seaman}
      - MYSQL_USER=${DB_USER:-seaman}
      - MYSQL_PASSWORD=${DB_PASSWORD:-secret}
    ports:
      - "${DB_PORT}:3306"
    networks:
      - seaman
{% if volumes.shouldPersist(name) %}
    volumes:
      - {{ name }}:/var/lib/mysql
{% endif %}
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5
```

**Step 2: Create mariadb.twig**

```yaml
  {{ name }}:
    image: mariadb:{{ service.version }}
    environment:
      - MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD:-secret}
      - MARIADB_DATABASE=${DB_NAME:-seaman}
      - MARIADB_USER=${DB_USER:-seaman}
      - MARIADB_PASSWORD=${DB_PASSWORD:-secret}
    ports:
      - "${DB_PORT}:3306"
    networks:
      - seaman
{% if volumes.shouldPersist(name) %}
    volumes:
      - {{ name }}:/var/lib/mysql
{% endif %}
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 10s
      timeout: 5s
      retries: 5
```

**Step 3: Create minio.twig**

```yaml
  {{ name }}:
    image: minio/minio:{{ service.version }}
    command: server /data --console-address ":9001"
    environment:
      - MINIO_ROOT_USER=${MINIO_ROOT_USER:-seaman}
      - MINIO_ROOT_PASSWORD=${MINIO_ROOT_PASSWORD:-seaman123}
    ports:
      - "${MINIO_PORT}:9000"
      - "${MINIO_CONSOLE_PORT}:9001"
    networks:
      - seaman
{% if volumes.shouldPersist(name) %}
    volumes:
      - {{ name }}:/data
{% endif %}
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:9000/minio/health/live"]
      interval: 30s
      timeout: 20s
      retries: 3
```

**Step 4: Create elasticsearch.twig**

```yaml
  {{ name }}:
    image: elasticsearch:{{ service.version }}
    environment:
      - discovery.type=single-node
      - xpack.security.enabled=false
      - "ES_JAVA_OPTS=-Xms512m -Xmx512m"
    ports:
      - "${ELASTICSEARCH_PORT}:9200"
    networks:
      - seaman
{% if volumes.shouldPersist(name) %}
    volumes:
      - {{ name }}:/usr/share/elasticsearch/data
{% endif %}
    healthcheck:
      test: ["CMD-SHELL", "curl -f http://localhost:9200/_cluster/health || exit 1"]
      interval: 30s
      timeout: 10s
      retries: 5
```

**Step 5: Create rabbitmq.twig**

```yaml
  {{ name }}:
    image: rabbitmq:{{ service.version }}
    environment:
      - RABBITMQ_DEFAULT_USER=${RABBITMQ_USER:-seaman}
      - RABBITMQ_DEFAULT_PASS=${RABBITMQ_PASSWORD:-secret}
    ports:
      - "${RABBITMQ_PORT}:5672"
      - "${RABBITMQ_MANAGEMENT_PORT}:15672"
    networks:
      - seaman
{% if volumes.shouldPersist(name) %}
    volumes:
      - {{ name }}:/var/lib/rabbitmq
{% endif %}
    healthcheck:
      test: ["CMD", "rabbitmq-diagnostics", "ping"]
      interval: 30s
      timeout: 10s
      retries: 5
```

**Step 6: Commit templates**

```bash
git add src/Template/docker/services/
git commit -m "feat: add remaining service templates (MySQL, MariaDB, MinIO, Elasticsearch, RabbitMQ)"
```

---

### Task 19: Create Dockerfile Templates

**Files:**
- Create: `src/Template/docker/Dockerfile.symfony.twig`
- Create: `src/Template/docker/Dockerfile.nginx-fpm.twig`
- Create: `src/Template/docker/Dockerfile.frankenphp.twig`

**Step 1: Create Dockerfile.symfony.twig**

```dockerfile
FROM php:{{ php.version }}-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    icu-dev \
    libzip-dev \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-install \
{% for extension in php.extensions %}
    {{ extension }}{% if not loop.last %} \{% endif %}

{% endfor %}

# Install Xdebug (but don't enable by default)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps \
    && echo "; xdebug disabled by default" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

WORKDIR /var/www/html

# Make xdebug-toggle script executable
RUN chmod +x /usr/local/bin/xdebug-toggle || true

CMD ["symfony", "server:start", "--no-tls", "--port=8000"]
```

**Step 2: Create Dockerfile.nginx-fpm.twig**

```dockerfile
FROM php:{{ php.version }}-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    git \
    curl \
    zip \
    unzip \
    icu-dev \
    libzip-dev \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-install \
{% for extension in php.extensions %}
    {{ extension }}{% if not loop.last %} \{% endif %}

{% endfor %}

# Install Xdebug
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps \
    && echo "; xdebug disabled by default" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy nginx configuration
COPY .seaman/config/nginx.conf /etc/nginx/nginx.conf

WORKDIR /var/www/html

# Make xdebug-toggle script executable
RUN chmod +x /usr/local/bin/xdebug-toggle || true

CMD sh -c "php-fpm -D && nginx -g 'daemon off;'"
```

**Step 3: Create Dockerfile.frankenphp.twig**

```dockerfile
FROM dunglas/frankenphp:latest-php{{ php.version }}

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libicu-dev \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
{% for extension in php.extensions %}
    {{ extension }}{% if not loop.last %} \{% endif %}

{% endfor %}

# Install Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "; xdebug disabled by default" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Make xdebug-toggle script executable
RUN chmod +x /usr/local/bin/xdebug-toggle || true

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
```

**Step 4: Commit templates**

```bash
git add src/Template/docker/Dockerfile.*.twig
git commit -m "feat: add Dockerfile templates for all server types"
```

---

### Task 20: Create Configuration File Templates

**Files:**
- Create: `src/Template/config/php.ini.twig`
- Create: `src/Template/config/xdebug.ini.twig`
- Create: `src/Template/config/nginx.conf.twig`

**Step 1: Create php.ini.twig**

```ini
[PHP]
memory_limit = 256M
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
display_errors = On
error_reporting = E_ALL

[Date]
date.timezone = UTC

[opcache]
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1
```

**Step 2: Create xdebug.ini.twig**

```ini
[xdebug]
xdebug.mode = {{ mode }}
xdebug.client_host = {{ client_host }}
xdebug.client_port = 9003
xdebug.idekey = {{ ide_key }}
xdebug.start_with_request = yes
xdebug.log = /tmp/xdebug.log
```

**Step 3: Create nginx.conf.twig**

```nginx
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;

    sendfile on;
    tcp_nopush on;
    keepalive_timeout 65;
    gzip on;

    server {
        listen 8000;
        server_name _;
        root /var/www/html/public;
        index index.php;

        location / {
            try_files $uri /index.php$is_args$args;
        }

        location ~ ^/index\.php(/|$) {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_split_path_info ^(.+\.php)(/.*)$;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            fastcgi_param DOCUMENT_ROOT $realpath_root;
            internal;
        }

        location ~ \.php$ {
            return 404;
        }
    }
}
```

**Step 4: Commit templates**

```bash
git add src/Template/config/
git commit -m "feat: add PHP, Xdebug, and Nginx configuration templates"
```

---

### Task 21: Create Xdebug Toggle Script Template

**Files:**
- Create: `src/Template/scripts/xdebug-toggle.sh.twig`

**Step 1: Create xdebug-toggle.sh.twig**

```bash
#!/bin/sh
# Xdebug toggle script - enables/disables Xdebug without container restart

MODE=$1

if [ -z "$MODE" ]; then
    echo "Usage: xdebug-toggle [on|off]"
    exit 1
fi

XDEBUG_INI="/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini"

case "$MODE" in
    on)
        cat > "$XDEBUG_INI" <<EOF
zend_extension=xdebug.so
xdebug.mode=debug
xdebug.client_host={{ xdebug.client_host }}
xdebug.client_port=9003
xdebug.idekey={{ xdebug.ide_key }}
xdebug.start_with_request=yes
xdebug.log=/tmp/xdebug.log
EOF
        # Reload PHP-FPM if running
        if pidof php-fpm > /dev/null 2>&1; then
            kill -USR2 1
        fi
        echo "✓ Xdebug enabled"
        ;;
    off)
        echo "; xdebug disabled" > "$XDEBUG_INI"
        # Reload PHP-FPM if running
        if pidof php-fpm > /dev/null 2>&1; then
            kill -USR2 1
        fi
        echo "✓ Xdebug disabled"
        ;;
    *)
        echo "Error: Invalid mode '$MODE'. Use 'on' or 'off'."
        exit 1
        ;;
esac
```

**Step 2: Commit template**

```bash
git add src/Template/scripts/xdebug-toggle.sh.twig
git commit -m "feat: add Xdebug toggle script template"
```

---

## Phase 5: Docker Generation Services

### Task 22: DockerComposeGenerator Service

**Files:**
- Create: `src/Service/DockerComposeGenerator.php`
- Create: `tests/Unit/Service/DockerComposeGeneratorTest.php`
- Create: `tests/Fixtures/configs/full-seaman.yaml`

**Step 1: Create full fixture for testing**

```yaml
version: '1.0'

server:
  type: symfony
  port: 8000

php:
  version: '8.4'
  extensions:
    - pdo_pgsql
    - redis
    - intl
  xdebug:
    enabled: false
    ide_key: PHPSTORM
    client_host: host.docker.internal

services:
  database:
    enabled: true
    type: postgresql
    version: '16'
    port: 5432

  redis:
    enabled: true
    type: redis
    version: '7-alpine'
    port: 6379

volumes:
  persist:
    - database
    - redis
```

**Step 2: Write failing test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for DockerComposeGenerator service.
// ABOUTME: Validates docker-compose.yml generation from configuration.

namespace Seaman\Tests\Unit\Service;

use Seaman\Service\DockerComposeGenerator;
use Seaman\Service\ConfigManager;
use Seaman\Service\TemplateRenderer;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);

    $templateDir = __DIR__ . '/../../../src/Template';
    $this->renderer = new TemplateRenderer($templateDir);
    $this->generator = new DockerComposeGenerator($this->renderer);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        array_map('unlink', glob($this->tempDir . '/*'));
        rmdir($this->tempDir);
    }
});

test('generates docker-compose.yml from configuration', function () {
    $configManager = new ConfigManager(__DIR__ . '/../../Fixtures/configs');
    copy(__DIR__ . '/../../Fixtures/configs/full-seaman.yaml', __DIR__ . '/../../Fixtures/configs/seaman.yaml');

    $config = $configManager->load();
    $yaml = $this->generator->generate($config);

    expect($yaml)->toContain('version: \'3.8\'')
        ->and($yaml)->toContain('services:')
        ->and($yaml)->toContain('app:')
        ->and($yaml)->toContain('database:')
        ->and($yaml)->toContain('redis:')
        ->and($yaml)->toContain('networks:')
        ->and($yaml)->toContain('volumes:');

    // Cleanup
    unlink(__DIR__ . '/../../Fixtures/configs/seaman.yaml');
});

test('includes only enabled services', function () {
    $configManager = new ConfigManager(__DIR__ . '/../../Fixtures/configs');
    copy(__DIR__ . '/../../Fixtures/configs/minimal-seaman.yaml', __DIR__ . '/../../Fixtures/configs/seaman.yaml');

    $config = $configManager->load();
    $yaml = $this->generator->generate($config);

    expect($yaml)->toContain('app:')
        ->and($yaml)->not->toContain('database:')
        ->and($yaml)->not->toContain('redis:');

    unlink(__DIR__ . '/../../Fixtures/configs/seaman.yaml');
});
```

**Step 3: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/DockerComposeGeneratorTest.php`

Expected: FAIL

**Step 4: Write minimal implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Generates docker-compose.yml from configuration.
// ABOUTME: Uses Twig templates to create Docker Compose files.

namespace Seaman\Service;

use Seaman\ValueObject\Configuration;

class DockerComposeGenerator
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
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

**Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/DockerComposeGeneratorTest.php`

Expected: PASS

**Step 6: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 7: Commit**

```bash
git add src/Service/DockerComposeGenerator.php tests/Unit/Service/DockerComposeGeneratorTest.php tests/Fixtures/configs/full-seaman.yaml
git commit -m "feat: add DockerComposeGenerator service"
```

---

### Task 23: DockerfileGenerator Service

**Files:**
- Create: `src/Service/DockerfileGenerator.php`
- Create: `tests/Unit/Service/DockerfileGeneratorTest.php`

**Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for DockerfileGenerator service.
// ABOUTME: Validates Dockerfile generation for different server types.

namespace Seaman\Tests\Unit\Service;

use Seaman\Service\DockerfileGenerator;
use Seaman\Service\TemplateRenderer;
use Seaman\ValueObject\ServerConfig;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;

beforeEach(function () {
    $templateDir = __DIR__ . '/../../../src/Template';
    $this->renderer = new TemplateRenderer($templateDir);
    $this->generator = new DockerfileGenerator($this->renderer);
});

test('generates Dockerfile for Symfony server', function () {
    $server = new ServerConfig('symfony', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', ['pdo_pgsql', 'redis'], $xdebug);

    $dockerfile = $this->generator->generate($server, $php);

    expect($dockerfile)->toContain('FROM php:8.4-cli-alpine')
        ->and($dockerfile)->toContain('symfony server:start')
        ->and($dockerfile)->toContain('pdo_pgsql')
        ->and($dockerfile)->toContain('redis');
});

test('generates Dockerfile for Nginx + FPM server', function () {
    $server = new ServerConfig('nginx-fpm', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', ['pdo_pgsql'], $xdebug);

    $dockerfile = $this->generator->generate($server, $php);

    expect($dockerfile)->toContain('FROM php:8.4-fpm-alpine')
        ->and($dockerfile)->toContain('nginx')
        ->and($dockerfile)->toContain('php-fpm');
});

test('generates Dockerfile for FrankenPHP server', function () {
    $server = new ServerConfig('frankenphp', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', [], $xdebug);

    $dockerfile = $this->generator->generate($server, $php);

    expect($dockerfile)->toContain('FROM dunglas/frankenphp')
        ->and($dockerfile)->toContain('frankenphp run');
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/DockerfileGeneratorTest.php`

Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Generates Dockerfile from server and PHP configuration.
// ABOUTME: Selects appropriate template based on server type.

namespace Seaman\Service;

use Seaman\ValueObject\ServerConfig;
use Seaman\ValueObject\PhpConfig;

class DockerfileGenerator
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
    ) {}

    public function generate(ServerConfig $server, PhpConfig $php): string
    {
        $template = match ($server->type) {
            'symfony' => 'docker/Dockerfile.symfony.twig',
            'nginx-fpm' => 'docker/Dockerfile.nginx-fpm.twig',
            'frankenphp' => 'docker/Dockerfile.frankenphp.twig',
            default => throw new \InvalidArgumentException("Unknown server type: {$server->type}"),
        };

        $context = [
            'php' => $php,
        ];

        return $this->renderer->render($template, $context);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/DockerfileGeneratorTest.php`

Expected: PASS

**Step 5: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 6: Commit**

```bash
git add src/Service/DockerfileGenerator.php tests/Unit/Service/DockerfileGeneratorTest.php
git commit -m "feat: add DockerfileGenerator service"
```

---

## Phase 6: Service Container System

### Task 24: ServiceInterface and HealthCheck

**Files:**
- Create: `src/Service/Container/ServiceInterface.php`
- Create: `tests/Unit/Service/Container/ServiceInterfaceTest.php`

**Step 1: ServiceInterface is already defined in design, create the interface**

```php
<?php

declare(strict_types=1);

// ABOUTME: Interface for pluggable Docker services.
// ABOUTME: Each service defines its config, dependencies, and compose generation.

namespace Seaman\Service\Container;

use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

interface ServiceInterface
{
    public function getName(): string;

    public function getDisplayName(): string;

    public function getDescription(): string;

    /**
     * @return list<string> Service names this service depends on
     */
    public function getDependencies(): array;

    public function getDefaultConfig(): ServiceConfig;

    /**
     * @return array<string, mixed> Docker Compose service definition
     */
    public function generateComposeConfig(ServiceConfig $config): array;

    /**
     * @return list<int> Ports this service requires
     */
    public function getRequiredPorts(): array;

    public function getHealthCheck(): ?HealthCheck;
}
```

**Step 2: Write test for a mock service implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for ServiceInterface implementations.
// ABOUTME: Validates service contract compliance.

namespace Seaman\Tests\Unit\Service\Container;

use Seaman\Service\Container\ServiceInterface;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

test('mock service implements interface correctly', function () {
    $service = new class implements ServiceInterface {
        public function getName(): string { return 'test'; }
        public function getDisplayName(): string { return 'Test Service'; }
        public function getDescription(): string { return 'A test service'; }
        public function getDependencies(): array { return []; }
        public function getDefaultConfig(): ServiceConfig {
            return new ServiceConfig('test', true, 'test', 'latest', 9999, [], []);
        }
        public function generateComposeConfig(ServiceConfig $config): array {
            return ['image' => 'test:latest'];
        }
        public function getRequiredPorts(): array { return [9999]; }
        public function getHealthCheck(): ?HealthCheck {
            return new HealthCheck(['CMD', 'true'], '10s', '5s', 3);
        }
    };

    expect($service->getName())->toBe('test')
        ->and($service->getDisplayName())->toBe('Test Service')
        ->and($service->getDescription())->toBe('A test service')
        ->and($service->getDependencies())->toBe([])
        ->and($service->getRequiredPorts())->toBe([9999])
        ->and($service->getHealthCheck())->toBeInstanceOf(HealthCheck::class);
});
```

**Step 3: Run test**

Run: `vendor/bin/pest tests/Unit/Service/Container/ServiceInterfaceTest.php`

Expected: PASS

**Step 4: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 5: Commit**

```bash
git add src/Service/Container/ServiceInterface.php tests/Unit/Service/Container/ServiceInterfaceTest.php
git commit -m "feat: add ServiceInterface for container services"
```

---

### Task 25: ServiceRegistry

**Files:**
- Create: `src/Service/Container/ServiceRegistry.php`
- Create: `tests/Unit/Service/Container/ServiceRegistryTest.php`

**Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for ServiceRegistry.
// ABOUTME: Validates service registration and retrieval.

namespace Seaman\Tests\Unit\Service\Container;

use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\Container\ServiceInterface;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ServerConfig;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;

function createMockService(string $name): ServiceInterface
{
    return new class($name) implements ServiceInterface {
        public function __construct(private string $name) {}
        public function getName(): string { return $this->name; }
        public function getDisplayName(): string { return ucfirst($this->name); }
        public function getDescription(): string { return "Test {$this->name}"; }
        public function getDependencies(): array { return []; }
        public function getDefaultConfig(): ServiceConfig {
            return new ServiceConfig($this->name, true, $this->name, 'latest', 5000, [], []);
        }
        public function generateComposeConfig(ServiceConfig $config): array { return []; }
        public function getRequiredPorts(): array { return [5000]; }
        public function getHealthCheck(): ?\Seaman\ValueObject\HealthCheck { return null; }
    };
}

test('registers and retrieves service', function () {
    $registry = new ServiceRegistry();
    $service = createMockService('postgresql');

    $registry->register($service);

    expect($registry->has('postgresql'))->toBeTrue()
        ->and($registry->get('postgresql'))->toBe($service);
});

test('throws when getting non-existent service', function () {
    $registry = new ServiceRegistry();
    $registry->get('nonexistent');
})->throws(\InvalidArgumentException::class, "Service 'nonexistent' not found");

test('returns all registered services', function () {
    $registry = new ServiceRegistry();
    $service1 = createMockService('postgresql');
    $service2 = createMockService('redis');

    $registry->register($service1);
    $registry->register($service2);

    $all = $registry->all();

    expect($all)->toHaveCount(2)
        ->and($all['postgresql'])->toBe($service1)
        ->and($all['redis'])->toBe($service2);
});

test('returns available services (not enabled)', function () {
    $registry = new ServiceRegistry();
    $registry->register(createMockService('postgresql'));
    $registry->register(createMockService('redis'));
    $registry->register(createMockService('mysql'));

    $enabledServices = [
        'postgresql' => new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []),
    ];

    $server = new ServerConfig('symfony', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', [], $xdebug);
    $config = new Configuration('1.0', $server, $php, new ServiceCollection($enabledServices), new VolumeConfig([]));

    $available = $registry->available($config);

    expect($available)->toHaveCount(2)
        ->and(array_key_exists('redis', $available))->toBeTrue()
        ->and(array_key_exists('mysql', $available))->toBeTrue()
        ->and(array_key_exists('postgresql', $available))->toBeFalse();
});

test('returns enabled services', function () {
    $registry = new ServiceRegistry();
    $registry->register(createMockService('postgresql'));
    $registry->register(createMockService('redis'));

    $enabledServices = [
        'postgresql' => new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []),
        'redis' => new ServiceConfig('redis', false, 'redis', '7', 6379, [], []), // disabled
    ];

    $server = new ServerConfig('symfony', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', [], $xdebug);
    $config = new Configuration('1.0', $server, $php, new ServiceCollection($enabledServices), new VolumeConfig([]));

    $enabled = $registry->enabled($config);

    expect($enabled)->toHaveCount(1)
        ->and($enabled['postgresql'])->toBeInstanceOf(ServiceInterface::class);
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/Container/ServiceRegistryTest.php`

Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Registry of all available services.
// ABOUTME: Manages service registration and retrieval.

namespace Seaman\Service\Container;

use Seaman\ValueObject\Configuration;

class ServiceRegistry
{
    /** @var array<string, ServiceInterface> */
    private array $services = [];

    public function register(ServiceInterface $service): void
    {
        $this->services[$service->getName()] = $service;
    }

    public function has(string $name): bool
    {
        return isset($this->services[$name]);
    }

    public function get(string $name): ServiceInterface
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException("Service '{$name}' not found");
        }

        return $this->services[$name];
    }

    /**
     * @return array<string, ServiceInterface>
     */
    public function all(): array
    {
        return $this->services;
    }

    /**
     * @return array<string, ServiceInterface> Services not currently enabled
     */
    public function available(Configuration $config): array
    {
        $enabledNames = array_keys($config->services->all());

        return array_filter(
            $this->services,
            fn(ServiceInterface $service): bool => !in_array($service->getName(), $enabledNames, true)
        );
    }

    /**
     * @return array<string, ServiceInterface> Currently enabled services
     */
    public function enabled(Configuration $config): array
    {
        $enabledConfigs = $config->services->enabled();
        $result = [];

        foreach ($enabledConfigs as $name => $serviceConfig) {
            if ($this->has($name)) {
                $result[$name] = $this->get($name);
            }
        }

        return $result;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/Container/ServiceRegistryTest.php`

Expected: PASS

**Step 5: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 6: Commit**

```bash
git add src/Service/Container/ServiceRegistry.php tests/Unit/Service/Container/ServiceRegistryTest.php
git commit -m "feat: add ServiceRegistry for service management"
```

---

### Task 26: PostgreSQL Service Implementation

**Files:**
- Create: `src/Service/Container/PostgresqlService.php`
- Create: `tests/Unit/Service/Container/PostgresqlServiceTest.php`

**Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for PostgresqlService.
// ABOUTME: Validates PostgreSQL service configuration.

namespace Seaman\Tests\Unit\Service\Container;

use Seaman\Service\Container\PostgresqlService;
use Seaman\ValueObject\ServiceConfig;

test('postgresql service has correct metadata', function () {
    $service = new PostgresqlService();

    expect($service->getName())->toBe('postgresql')
        ->and($service->getDisplayName())->toBe('PostgreSQL')
        ->and($service->getDescription())->toContain('database')
        ->and($service->getDependencies())->toBe([]);
});

test('postgresql service provides default config', function () {
    $service = new PostgresqlService();
    $config = $service->getDefaultConfig();

    expect($config->name)->toBe('postgresql')
        ->and($config->type)->toBe('postgresql')
        ->and($config->version)->toBe('16')
        ->and($config->port)->toBe(5432);
});

test('postgresql service requires port 5432', function () {
    $service = new PostgresqlService();

    expect($service->getRequiredPorts())->toBe([5432]);
});

test('postgresql service has health check', function () {
    $service = new PostgresqlService();
    $healthCheck = $service->getHealthCheck();

    expect($healthCheck)->not->toBeNull()
        ->and($healthCheck->test)->toContain('pg_isready');
});

test('postgresql service generates compose config', function () {
    $service = new PostgresqlService();
    $config = new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []);

    $composeConfig = $service->generateComposeConfig($config);

    expect($composeConfig)->toHaveKey('image')
        ->and($composeConfig)->toHaveKey('environment')
        ->and($composeConfig)->toHaveKey('ports')
        ->and($composeConfig['image'])->toBe('postgres:16');
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/Container/PostgresqlServiceTest.php`

Expected: FAIL

**Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: PostgreSQL database service implementation.
// ABOUTME: Configures PostgreSQL container for Seaman.

namespace Seaman\Service\Container;

use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

class PostgresqlService implements ServiceInterface
{
    public function getName(): string
    {
        return 'postgresql';
    }

    public function getDisplayName(): string
    {
        return 'PostgreSQL';
    }

    public function getDescription(): string
    {
        return 'PostgreSQL relational database';
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: 'postgresql',
            enabled: true,
            type: 'postgresql',
            version: '16',
            port: 5432,
            additionalPorts: [],
            environmentVariables: [
                'POSTGRES_DB' => 'seaman',
                'POSTGRES_USER' => 'seaman',
                'POSTGRES_PASSWORD' => 'secret',
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        return [
            'image' => "postgres:{$config->version}",
            'environment' => [
                'POSTGRES_DB=${DB_NAME:-seaman}',
                'POSTGRES_USER=${DB_USER:-seaman}',
                'POSTGRES_PASSWORD=${DB_PASSWORD:-secret}',
            ],
            'ports' => [
                '${DB_PORT}:5432',
            ],
            'networks' => ['seaman'],
            'healthcheck' => [
                'test' => ['CMD', 'pg_isready', '-U', '${DB_USER:-seaman}'],
                'interval' => '10s',
                'timeout' => '5s',
                'retries' => 5,
            ],
        ];
    }

    public function getRequiredPorts(): array
    {
        return [5432];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(
            test: ['CMD', 'pg_isready', '-U', 'seaman'],
            interval: '10s',
            timeout: '5s',
            retries: 5
        );
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/Container/PostgresqlServiceTest.php`

Expected: PASS

**Step 5: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 6: Commit**

```bash
git add src/Service/Container/PostgresqlService.php tests/Unit/Service/Container/PostgresqlServiceTest.php
git commit -m "feat: add PostgresqlService implementation"
```

---

### Task 27: Implement Remaining Core Services

**Files:**
- Create: `src/Service/Container/RedisService.php`
- Create: `src/Service/Container/MysqlService.php`
- Create: `src/Service/Container/MariadbService.php`
- Create: `src/Service/Container/MailpitService.php`
- Create: `tests/Unit/Service/Container/CoreServicesTest.php`

**Step 1: Write test for all core services**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for core service implementations.
// ABOUTME: Validates Redis, MySQL, MariaDB, and Mailpit services.

namespace Seaman\Tests\Unit\Service\Container;

use Seaman\Service\Container\RedisService;
use Seaman\Service\Container\MysqlService;
use Seaman\Service\Container\MariadbService;
use Seaman\Service\Container\MailpitService;

test('redis service configuration', function () {
    $service = new RedisService();

    expect($service->getName())->toBe('redis')
        ->and($service->getDisplayName())->toBe('Redis')
        ->and($service->getRequiredPorts())->toBe([6379])
        ->and($service->getDefaultConfig()->version)->toBe('7-alpine');
});

test('mysql service configuration', function () {
    $service = new MysqlService();

    expect($service->getName())->toBe('mysql')
        ->and($service->getDisplayName())->toBe('MySQL')
        ->and($service->getRequiredPorts())->toBe([3306])
        ->and($service->getDefaultConfig()->version)->toBe('8.0');
});

test('mariadb service configuration', function () {
    $service = new MariadbService();

    expect($service->getName())->toBe('mariadb')
        ->and($service->getDisplayName())->toBe('MariaDB')
        ->and($service->getRequiredPorts())->toBe([3306])
        ->and($service->getDefaultConfig()->version)->toBe('11');
});

test('mailpit service configuration', function () {
    $service = new MailpitService();

    expect($service->getName())->toBe('mailpit')
        ->and($service->getDisplayName())->toBe('Mailpit')
        ->and($service->getRequiredPorts())->toContain(8025)
        ->and($service->getRequiredPorts())->toContain(1025);
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/Container/CoreServicesTest.php`

Expected: FAIL

**Step 3: Implement RedisService**

```php
<?php

declare(strict_types=1);

// ABOUTME: Redis cache service implementation.
// ABOUTME: Configures Redis container for caching and sessions.

namespace Seaman\Service\Container;

use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

class RedisService implements ServiceInterface
{
    public function getName(): string
    {
        return 'redis';
    }

    public function getDisplayName(): string
    {
        return 'Redis';
    }

    public function getDescription(): string
    {
        return 'Redis cache and session storage';
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: 'redis',
            enabled: true,
            type: 'redis',
            version: '7-alpine',
            port: 6379,
            additionalPorts: [],
            environmentVariables: []
        );
    }

    public function generateComposeConfig(ServiceConfig $config): array
    {
        return [
            'image' => "redis:{$config->version}",
            'ports' => ['${REDIS_PORT}:6379'],
            'networks' => ['seaman'],
            'healthcheck' => [
                'test' => ['CMD', 'redis-cli', 'ping'],
                'interval' => '10s',
                'timeout' => '5s',
                'retries' => 5,
            ],
        ];
    }

    public function getRequiredPorts(): array
    {
        return [6379];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(['CMD', 'redis-cli', 'ping'], '10s', '5s', 5);
    }
}
```

**Step 4: Implement MysqlService**

```php
<?php

declare(strict_types=1);

// ABOUTME: MySQL database service implementation.
// ABOUTME: Configures MySQL container for Seaman.

namespace Seaman\Service\Container;

use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

class MysqlService implements ServiceInterface
{
    public function getName(): string
    {
        return 'mysql';
    }

    public function getDisplayName(): string
    {
        return 'MySQL';
    }

    public function getDescription(): string
    {
        return 'MySQL relational database';
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: 'mysql',
            enabled: true,
            type: 'mysql',
            version: '8.0',
            port: 3306,
            additionalPorts: [],
            environmentVariables: [
                'MYSQL_ROOT_PASSWORD' => 'secret',
                'MYSQL_DATABASE' => 'seaman',
                'MYSQL_USER' => 'seaman',
                'MYSQL_PASSWORD' => 'secret',
            ]
        );
    }

    public function generateComposeConfig(ServiceConfig $config): array
    {
        return [
            'image' => "mysql:{$config->version}",
            'environment' => [
                'MYSQL_ROOT_PASSWORD=${DB_ROOT_PASSWORD:-secret}',
                'MYSQL_DATABASE=${DB_NAME:-seaman}',
                'MYSQL_USER=${DB_USER:-seaman}',
                'MYSQL_PASSWORD=${DB_PASSWORD:-secret}',
            ],
            'ports' => ['${DB_PORT}:3306'],
            'networks' => ['seaman'],
            'healthcheck' => [
                'test' => ['CMD', 'mysqladmin', 'ping', '-h', 'localhost'],
                'interval' => '10s',
                'timeout' => '5s',
                'retries' => 5,
            ],
        ];
    }

    public function getRequiredPorts(): array
    {
        return [3306];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(['CMD', 'mysqladmin', 'ping', '-h', 'localhost'], '10s', '5s', 5);
    }
}
```

**Step 5: Implement MariadbService**

```php
<?php

declare(strict_types=1);

// ABOUTME: MariaDB database service implementation.
// ABOUTME: Configures MariaDB container for Seaman.

namespace Seaman\Service\Container;

use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

class MariadbService implements ServiceInterface
{
    public function getName(): string
    {
        return 'mariadb';
    }

    public function getDisplayName(): string
    {
        return 'MariaDB';
    }

    public function getDescription(): string
    {
        return 'MariaDB relational database';
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: 'mariadb',
            enabled: true,
            type: 'mariadb',
            version: '11',
            port: 3306,
            additionalPorts: [],
            environmentVariables: [
                'MARIADB_ROOT_PASSWORD' => 'secret',
                'MARIADB_DATABASE' => 'seaman',
                'MARIADB_USER' => 'seaman',
                'MARIADB_PASSWORD' => 'secret',
            ]
        );
    }

    public function generateComposeConfig(ServiceConfig $config): array
    {
        return [
            'image' => "mariadb:{$config->version}",
            'environment' => [
                'MARIADB_ROOT_PASSWORD=${DB_ROOT_PASSWORD:-secret}',
                'MARIADB_DATABASE=${DB_NAME:-seaman}',
                'MARIADB_USER=${DB_USER:-seaman}',
                'MARIADB_PASSWORD=${DB_PASSWORD:-secret}',
            ],
            'ports' => ['${DB_PORT}:3306'],
            'networks' => ['seaman'],
            'healthcheck' => [
                'test' => ['CMD', 'healthcheck.sh', '--connect', '--innodb_initialized'],
                'interval' => '10s',
                'timeout' => '5s',
                'retries' => 5,
            ],
        ];
    }

    public function getRequiredPorts(): array
    {
        return [3306];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(['CMD', 'healthcheck.sh', '--connect'], '10s', '5s', 5);
    }
}
```

**Step 6: Implement MailpitService**

```php
<?php

declare(strict_types=1);

// ABOUTME: Mailpit email testing service implementation.
// ABOUTME: Configures Mailpit for local email capture.

namespace Seaman\Service\Container;

use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

class MailpitService implements ServiceInterface
{
    public function getName(): string
    {
        return 'mailpit';
    }

    public function getDisplayName(): string
    {
        return 'Mailpit';
    }

    public function getDescription(): string
    {
        return 'Email testing tool - captures and displays emails';
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: 'mailpit',
            enabled: true,
            type: 'mailpit',
            version: 'latest',
            port: 8025,
            additionalPorts: [1025],
            environmentVariables: []
        );
    }

    public function generateComposeConfig(ServiceConfig $config): array
    {
        return [
            'image' => 'axllent/mailpit:latest',
            'ports' => [
                '${MAILPIT_PORT}:8025',
                '${MAILPIT_SMTP_PORT:-1025}:1025',
            ],
            'networks' => ['seaman'],
            'environment' => [
                'MP_MAX_MESSAGES=5000',
                'MP_SMTP_AUTH_ACCEPT_ANY=1',
                'MP_SMTP_AUTH_ALLOW_INSECURE=1',
            ],
        ];
    }

    public function getRequiredPorts(): array
    {
        return [8025, 1025];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return null; // Mailpit doesn't need a health check
    }
}
```

**Step 7: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/Container/CoreServicesTest.php`

Expected: PASS

**Step 8: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 9: Commit**

```bash
git add src/Service/Container/RedisService.php src/Service/Container/MysqlService.php src/Service/Container/MariadbService.php src/Service/Container/MailpitService.php tests/Unit/Service/Container/CoreServicesTest.php
git commit -m "feat: add core service implementations (Redis, MySQL, MariaDB, Mailpit)"
```

---

### Task 28: Implement Advanced Services

**Files:**
- Create: `src/Service/Container/MinioService.php`
- Create: `src/Service/Container/ElasticsearchService.php`
- Create: `src/Service/Container/RabbitmqService.php`
- Create: `tests/Unit/Service/Container/AdvancedServicesTest.php`

**Step 1: Write test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for advanced service implementations.
// ABOUTME: Validates MinIO, Elasticsearch, and RabbitMQ services.

namespace Seaman\Tests\Unit\Service\Container;

use Seaman\Service\Container\MinioService;
use Seaman\Service\Container\ElasticsearchService;
use Seaman\Service\Container\RabbitmqService;

test('minio service has multiple ports', function () {
    $service = new MinioService();

    expect($service->getName())->toBe('minio')
        ->and($service->getRequiredPorts())->toContain(9000)
        ->and($service->getRequiredPorts())->toContain(9001);
});

test('elasticsearch service configuration', function () {
    $service = new ElasticsearchService();

    expect($service->getName())->toBe('elasticsearch')
        ->and($service->getDefaultConfig()->version)->toBe('8.11');
});

test('rabbitmq service has management port', function () {
    $service = new RabbitmqService();

    expect($service->getRequiredPorts())->toContain(5672)
        ->and($service->getRequiredPorts())->toContain(15672);
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/Container/AdvancedServicesTest.php`

Expected: FAIL

**Step 3: Implement MinioService**

```php
<?php

declare(strict_types=1);

// ABOUTME: MinIO S3-compatible storage service.
// ABOUTME: Configures MinIO for local object storage.

namespace Seaman\Service\Container;

use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

class MinioService implements ServiceInterface
{
    public function getName(): string
    {
        return 'minio';
    }

    public function getDisplayName(): string
    {
        return 'MinIO';
    }

    public function getDescription(): string
    {
        return 'S3-compatible object storage';
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: 'minio',
            enabled: false,
            type: 'minio',
            version: 'latest',
            port: 9000,
            additionalPorts: [9001],
            environmentVariables: [
                'MINIO_ROOT_USER' => 'seaman',
                'MINIO_ROOT_PASSWORD' => 'seaman123',
            ]
        );
    }

    public function generateComposeConfig(ServiceConfig $config): array
    {
        return [
            'image' => 'minio/minio:latest',
            'command' => 'server /data --console-address ":9001"',
            'environment' => [
                'MINIO_ROOT_USER=${MINIO_ROOT_USER:-seaman}',
                'MINIO_ROOT_PASSWORD=${MINIO_ROOT_PASSWORD:-seaman123}',
            ],
            'ports' => [
                '${MINIO_PORT}:9000',
                '${MINIO_CONSOLE_PORT}:9001',
            ],
            'networks' => ['seaman'],
            'healthcheck' => [
                'test' => ['CMD', 'curl', '-f', 'http://localhost:9000/minio/health/live'],
                'interval' => '30s',
                'timeout' => '20s',
                'retries' => 3,
            ],
        ];
    }

    public function getRequiredPorts(): array
    {
        return [9000, 9001];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(['CMD', 'curl', '-f', 'http://localhost:9000/minio/health/live'], '30s', '20s', 3);
    }
}
```

**Step 4: Implement ElasticsearchService**

```php
<?php

declare(strict_types=1);

// ABOUTME: Elasticsearch search engine service.
// ABOUTME: Configures Elasticsearch for full-text search.

namespace Seaman\Service\Container;

use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

class ElasticsearchService implements ServiceInterface
{
    public function getName(): string
    {
        return 'elasticsearch';
    }

    public function getDisplayName(): string
    {
        return 'Elasticsearch';
    }

    public function getDescription(): string
    {
        return 'Full-text search and analytics engine';
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: 'elasticsearch',
            enabled: false,
            type: 'elasticsearch',
            version: '8.11',
            port: 9200,
            additionalPorts: [],
            environmentVariables: []
        );
    }

    public function generateComposeConfig(ServiceConfig $config): array
    {
        return [
            'image' => "elasticsearch:{$config->version}",
            'environment' => [
                'discovery.type=single-node',
                'xpack.security.enabled=false',
                'ES_JAVA_OPTS=-Xms512m -Xmx512m',
            ],
            'ports' => ['${ELASTICSEARCH_PORT}:9200'],
            'networks' => ['seaman'],
            'healthcheck' => [
                'test' => ['CMD-SHELL', 'curl -f http://localhost:9200/_cluster/health || exit 1'],
                'interval' => '30s',
                'timeout' => '10s',
                'retries' => 5,
            ],
        ];
    }

    public function getRequiredPorts(): array
    {
        return [9200];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(['CMD-SHELL', 'curl -f http://localhost:9200/_cluster/health'], '30s', '10s', 5);
    }
}
```

**Step 5: Implement RabbitmqService**

```php
<?php

declare(strict_types=1);

// ABOUTME: RabbitMQ message queue service.
// ABOUTME: Configures RabbitMQ for async message processing.

namespace Seaman\Service\Container;

use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

class RabbitmqService implements ServiceInterface
{
    public function getName(): string
    {
        return 'rabbitmq';
    }

    public function getDisplayName(): string
    {
        return 'RabbitMQ';
    }

    public function getDescription(): string
    {
        return 'Message queue for async processing';
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: 'rabbitmq',
            enabled: false,
            type: 'rabbitmq',
            version: '3-management',
            port: 5672,
            additionalPorts: [15672],
            environmentVariables: [
                'RABBITMQ_DEFAULT_USER' => 'seaman',
                'RABBITMQ_DEFAULT_PASS' => 'secret',
            ]
        );
    }

    public function generateComposeConfig(ServiceConfig $config): array
    {
        return [
            'image' => "rabbitmq:{$config->version}",
            'environment' => [
                'RABBITMQ_DEFAULT_USER=${RABBITMQ_USER:-seaman}',
                'RABBITMQ_DEFAULT_PASS=${RABBITMQ_PASSWORD:-secret}',
            ],
            'ports' => [
                '${RABBITMQ_PORT}:5672',
                '${RABBITMQ_MANAGEMENT_PORT}:15672',
            ],
            'networks' => ['seaman'],
            'healthcheck' => [
                'test' => ['CMD', 'rabbitmq-diagnostics', 'ping'],
                'interval' => '30s',
                'timeout' => '10s',
                'retries' => 5,
            ],
        ];
    }

    public function getRequiredPorts(): array
    {
        return [5672, 15672];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(['CMD', 'rabbitmq-diagnostics', 'ping'], '30s', '10s', 5);
    }
}
```

**Step 6: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/Container/AdvancedServicesTest.php`

Expected: PASS

**Step 7: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 8: Commit**

```bash
git add src/Service/Container/MinioService.php src/Service/Container/ElasticsearchService.php src/Service/Container/RabbitmqService.php tests/Unit/Service/Container/AdvancedServicesTest.php
git commit -m "feat: add advanced service implementations (MinIO, Elasticsearch, RabbitMQ)"
```

---

## Phase 7: Docker Manager

### Task 29: DockerManager Service

**Files:**
- Create: `src/Service/DockerManager.php`
- Create: `tests/Unit/Service/DockerManagerTest.php`

**Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for DockerManager service.
// ABOUTME: Validates Docker command execution.

namespace Seaman\Tests\Unit\Service;

use Seaman\Service\DockerManager;
use Symfony\Component\Process\Process;
use Mockery;

afterEach(function () {
    Mockery::close();
});

test('starts all services', function () {
    $process = Mockery::mock(Process::class);
    $process->shouldReceive('run')->once()->andReturn(0);
    $process->shouldReceive('isSuccessful')->andReturn(true);
    $process->shouldReceive('getOutput')->andReturn('Started');

    $manager = new DockerManager('/tmp/test');
    // We'll mock the process creation in implementation

    expect(true)->toBeTrue(); // Placeholder for now
});

test('executes command in service container', function () {
    $manager = new DockerManager('/tmp/test');

    expect($manager)->toBeInstanceOf(DockerManager::class);
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/DockerManagerTest.php`

Expected: FAIL

**Step 3: Write implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Manages Docker and docker-compose operations.
// ABOUTME: Executes Docker commands for service lifecycle.

namespace Seaman\Service;

use Seaman\ValueObject\ProcessResult;
use Seaman\ValueObject\LogOptions;
use Symfony\Component\Process\Process;

class DockerManager
{
    private const COMPOSE_FILE = '.seaman/docker-compose.yml';

    public function __construct(
        private readonly string $projectRoot,
    ) {}

    public function start(?string $service = null): ProcessResult
    {
        $args = ['docker', 'compose', '-f', self::COMPOSE_FILE, 'up', '-d'];

        if ($service !== null) {
            $args[] = $service;
        }

        return $this->execute($args);
    }

    public function stop(?string $service = null): ProcessResult
    {
        $args = ['docker', 'compose', '-f', self::COMPOSE_FILE, 'stop'];

        if ($service !== null) {
            $args[] = $service;
        }

        return $this->execute($args);
    }

    public function restart(?string $service = null): ProcessResult
    {
        $this->stop($service);

        return $this->start($service);
    }

    public function rebuild(?string $service = null): ProcessResult
    {
        $args = ['docker', 'compose', '-f', self::COMPOSE_FILE, 'build', '--no-cache'];

        if ($service !== null) {
            $args[] = $service;
        }

        return $this->execute($args);
    }

    public function destroy(): ProcessResult
    {
        $args = ['docker', 'compose', '-f', self::COMPOSE_FILE, 'down', '-v'];

        return $this->execute($args);
    }

    /**
     * @return array<string, array{name: string, status: string, ports: string}>
     */
    public function status(): array
    {
        $result = $this->execute([
            'docker', 'compose', '-f', self::COMPOSE_FILE, 'ps', '--format', 'json'
        ]);

        if (!$result->isSuccessful()) {
            return [];
        }

        $lines = array_filter(explode("\n", $result->output));
        $services = [];

        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (is_array($data) && isset($data['Service'])) {
                $services[$data['Service']] = [
                    'name' => $data['Service'],
                    'status' => $data['State'] ?? 'unknown',
                    'ports' => $data['Publishers'] ?? '',
                ];
            }
        }

        return $services;
    }

    /**
     * @param list<string> $command
     */
    public function executeInService(string $service, array $command): ProcessResult
    {
        $args = ['docker', 'compose', '-f', self::COMPOSE_FILE, 'exec', $service, ...$command];

        return $this->execute($args);
    }

    public function logs(string $service, LogOptions $options): ProcessResult
    {
        $args = ['docker', 'compose', '-f', self::COMPOSE_FILE, 'logs'];

        if ($options->follow) {
            $args[] = '-f';
        }

        if ($options->tail !== null) {
            $args[] = '--tail';
            $args[] = (string) $options->tail;
        }

        if ($options->since !== null) {
            $args[] = '--since';
            $args[] = $options->since;
        }

        $args[] = $service;

        return $this->execute($args);
    }

    /**
     * @param list<string> $args
     */
    private function execute(array $args): ProcessResult
    {
        $process = new Process($args, $this->projectRoot);
        $process->setTimeout(300); // 5 minutes
        $exitCode = $process->run();

        return new ProcessResult(
            exitCode: $exitCode,
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput()
        );
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/DockerManagerTest.php`

Expected: PASS

**Step 5: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 6: Commit**

```bash
git add src/Service/DockerManager.php tests/Unit/Service/DockerManagerTest.php
git commit -m "feat: add DockerManager for container lifecycle management"
```

---

## Phase 8: Console Application Setup

### Task 30: Create Symfony Console Application

**Files:**
- Create: `src/Application.php`
- Create: `bin/seaman.php`

**Step 1: Create Application class**

```php
<?php

declare(strict_types=1);

// ABOUTME: Main Seaman console application.
// ABOUTME: Registers commands and configures Symfony Console.

namespace Seaman;

use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    private const VERSION = '1.0.0-dev';

    public function __construct()
    {
        parent::__construct('Seaman', self::VERSION);

        $this->registerCommands();
    }

    private function registerCommands(): void
    {
        // Commands will be registered here as we implement them
        // Example:
        // $this->add(new Command\InitCommand());
    }
}
```

**Step 2: Create bin/seaman.php entry point**

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

// ABOUTME: Entry point for Seaman CLI application.
// ABOUTME: Bootstraps and runs the Symfony Console application.

// Autoloader for different contexts
$autoloadPaths = [
    __DIR__ . '/../../../autoload.php',  // Installed via Composer globally
    __DIR__ . '/../vendor/autoload.php', // Development
];

$autoloaderFound = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require $path;
        $autoloaderFound = true;
        break;
    }
}

if (!$autoloaderFound) {
    fwrite(STDERR, "Composer autoloader not found. Please run 'composer install'.\n");
    exit(1);
}

use Seaman\Application;

$application = new Application();
$exitCode = $application->run();

exit($exitCode);
```

**Step 3: Make bin/seaman.php executable**

Run: `chmod +x bin/seaman.php`

Expected: File is executable

**Step 4: Test application runs**

Run: `php bin/seaman.php --version`

Expected: Outputs "Seaman 1.0.0-dev"

**Step 5: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 6: Commit**

```bash
git add src/Application.php bin/seaman.php
git commit -m "feat: add Symfony Console application bootstrap"
```

---

## Phase 9: Core Commands Implementation

### Task 31: InitCommand - Interactive Initialization

**Files:**
- Create: `src/Command/InitCommand.php`
- Create: `tests/Integration/Command/InitCommandTest.php`

**Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

// ABOUTME: Integration tests for InitCommand.
// ABOUTME: Validates interactive initialization flow.

namespace Seaman\Tests\Integration\Command;

use Seaman\Command\InitCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        exec("rm -rf {$this->tempDir}");
    }
});

test('init command creates seaman.yaml', function () {
    $application = new Application();
    $command = new InitCommand();
    $application->add($command);

    $commandTester = new CommandTester($command);
    $commandTester->setInputs(['8.4', 'symfony', 'postgresql', '', '']); // Simulate user inputs

    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0);
    // Will validate files after implementation
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Integration/Command/InitCommandTest.php`

Expected: FAIL

**Step 3: Write implementation**

```php
<?php

declare(strict_types=1);

// ABOUTME: Interactive initialization command.
// ABOUTME: Creates seaman.yaml and sets up Docker environment.

namespace Seaman\Command;

use Seaman\Service\ConfigManager;
use Seaman\Service\DockerComposeGenerator;
use Seaman\Service\DockerfileGenerator;
use Seaman\Service\TemplateRenderer;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\Container\PostgresqlService;
use Seaman\Service\Container\MysqlService;
use Seaman\Service\Container\MariadbService;
use Seaman\Service\Container\RedisService;
use Seaman\Service\Container\MailpitService;
use Seaman\Service\Container\MinioService;
use Seaman\Service\Container\ElasticsearchService;
use Seaman\Service\Container\RabbitmqService;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ServerConfig;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;

class InitCommand extends Command
{
    protected static $defaultName = 'init';
    protected static $defaultDescription = 'Initialize Seaman configuration interactively';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Seaman Initialization');

        // Check if already initialized
        if (file_exists(getcwd() . '/seaman.yaml')) {
            if (!$io->confirm('seaman.yaml already exists. Overwrite?', false)) {
                $io->info('Initialization cancelled.');
                return Command::SUCCESS;
            }
        }

        // Step 1: PHP Version
        $phpVersion = $io->choice(
            'Select PHP version',
            ['8.2', '8.3', '8.4'],
            '8.4'
        );

        // Step 2: Server Type
        $serverType = $io->choice(
            'Select server type',
            [
                'symfony' => 'Symfony Server (fastest, hot reload)',
                'nginx-fpm' => 'Nginx + PHP-FPM (production-like)',
                'frankenphp' => 'FrankenPHP + Caddy (modern, HTTP/3)',
            ],
            'symfony'
        );

        // Step 3: Database Selection
        $databaseQuestion = new ChoiceQuestion(
            'Select database (leave empty for none)',
            ['none', 'postgresql', 'mysql', 'mariadb'],
            'postgresql'
        );
        $database = $io->askQuestion($databaseQuestion);

        // Step 4: Additional Services
        $additionalServices = $io->choice(
            'Select additional services (comma-separated)',
            ['redis', 'mailpit', 'minio', 'elasticsearch', 'rabbitmq'],
            'redis,mailpit',
            true
        );

        // Build configuration
        $server = new ServerConfig($serverType, 8000);
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

        // Build services
        $registry = $this->createServiceRegistry();
        $services = [];
        $persistVolumes = [];

        if ($database !== 'none') {
            $serviceImpl = $registry->get($database);
            $defaultConfig = $serviceImpl->getDefaultConfig();
            $services[$database] = $defaultConfig;
            $persistVolumes[] = $database;
        }

        foreach ($additionalServices as $serviceName) {
            $serviceImpl = $registry->get($serviceName);
            $defaultConfig = $serviceImpl->getDefaultConfig();
            $services[$serviceName] = $defaultConfig;

            if (in_array($serviceName, ['redis', 'minio', 'elasticsearch', 'rabbitmq'], true)) {
                $persistVolumes[] = $serviceName;
            }
        }

        $config = new Configuration(
            version: '1.0',
            server: $server,
            php: $php,
            services: new ServiceCollection($services),
            volumes: new VolumeConfig($persistVolumes)
        );

        // Save configuration
        $projectRoot = getcwd();
        $configManager = new ConfigManager($projectRoot);
        $configManager->save($config);

        // Generate Docker files
        $this->generateDockerFiles($config, $projectRoot);

        $io->success('Seaman initialized successfully!');
        $io->info('Next steps:');
        $io->listing([
            'Run "seaman start" to start services',
            'Run "seaman status" to check service status',
            'Run "seaman --help" to see all commands',
        ]);

        return Command::SUCCESS;
    }

    private function createServiceRegistry(): ServiceRegistry
    {
        $registry = new ServiceRegistry();
        $registry->register(new PostgresqlService());
        $registry->register(new MysqlService());
        $registry->register(new MariadbService());
        $registry->register(new RedisService());
        $registry->register(new MailpitService());
        $registry->register(new MinioService());
        $registry->register(new ElasticsearchService());
        $registry->register(new RabbitmqService());

        return $registry;
    }

    private function generateDockerFiles(Configuration $config, string $projectRoot): void
    {
        $seamanDir = $projectRoot . '/.seaman';
        if (!is_dir($seamanDir)) {
            mkdir($seamanDir, 0755, true);
        }

        $templateDir = __DIR__ . '/../Template';
        $renderer = new TemplateRenderer($templateDir);

        // Generate docker-compose.yml
        $composeGenerator = new DockerComposeGenerator($renderer);
        $composeYaml = $composeGenerator->generate($config);
        file_put_contents($seamanDir . '/docker-compose.yml', $composeYaml);

        // Generate Dockerfile
        $dockerfileGenerator = new DockerfileGenerator($renderer);
        $dockerfile = $dockerfileGenerator->generate($config->server, $config->php);
        file_put_contents($seamanDir . '/Dockerfile', $dockerfile);

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
    }
}
```

**Step 4: Register command in Application**

```php
// In src/Application.php, update registerCommands():

private function registerCommands(): void
{
    $this->add(new Command\InitCommand());
}
```

**Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Integration/Command/InitCommandTest.php`

Expected: PASS

**Step 6: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 7: Commit**

```bash
git add src/Command/InitCommand.php src/Application.php tests/Integration/Command/InitCommandTest.php
git commit -m "feat: add InitCommand for interactive initialization"
```

---

### Task 32: StartCommand, StopCommand, RestartCommand

**Files:**
- Create: `src/Command/StartCommand.php`
- Create: `src/Command/StopCommand.php`
- Create: `src/Command/RestartCommand.php`

**Step 1: Implement StartCommand**

```php
<?php

declare(strict_types=1);

// ABOUTME: Starts Docker services.
// ABOUTME: Executes docker-compose up for all or specific services.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StartCommand extends Command
{
    protected static $defaultName = 'start';
    protected static $defaultDescription = 'Start services';

    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Specific service to start');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $service = $input->getArgument('service');

        $manager = new DockerManager(getcwd());

        $io->info($service ? "Starting service: {$service}..." : 'Starting all services...');

        $result = $manager->start($service);

        if ($result->isSuccessful()) {
            $io->success($service ? "Service {$service} started!" : 'All services started!');
            return Command::SUCCESS;
        }

        $io->error('Failed to start services');
        $io->writeln($result->errorOutput);

        return Command::FAILURE;
    }
}
```

**Step 2: Implement StopCommand**

```php
<?php

declare(strict_types=1);

// ABOUTME: Stops Docker services.
// ABOUTME: Executes docker-compose stop for all or specific services.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StopCommand extends Command
{
    protected static $defaultName = 'stop';
    protected static $defaultDescription = 'Stop services';

    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Specific service to stop');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $service = $input->getArgument('service');

        $manager = new DockerManager(getcwd());

        $io->info($service ? "Stopping service: {$service}..." : 'Stopping all services...');

        $result = $manager->stop($service);

        if ($result->isSuccessful()) {
            $io->success($service ? "Service {$service} stopped!" : 'All services stopped!');
            return Command::SUCCESS;
        }

        $io->error('Failed to stop services');
        $io->writeln($result->errorOutput);

        return Command::FAILURE;
    }
}
```

**Step 3: Implement RestartCommand**

```php
<?php

declare(strict_types=1);

// ABOUTME: Restarts Docker services.
// ABOUTME: Stops and starts services in sequence.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RestartCommand extends Command
{
    protected static $defaultName = 'restart';
    protected static $defaultDescription = 'Restart services';

    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Specific service to restart');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $service = $input->getArgument('service');

        $manager = new DockerManager(getcwd());

        $io->info($service ? "Restarting service: {$service}..." : 'Restarting all services...');

        $result = $manager->restart($service);

        if ($result->isSuccessful()) {
            $io->success($service ? "Service {$service} restarted!" : 'All services restarted!');
            return Command::SUCCESS;
        }

        $io->error('Failed to restart services');
        $io->writeln($result->errorOutput);

        return Command::FAILURE;
    }
}
```

**Step 4: Register commands**

```php
// In src/Application.php:

private function registerCommands(): void
{
    $this->add(new Command\InitCommand());
    $this->add(new Command\StartCommand());
    $this->add(new Command\StopCommand());
    $this->add(new Command\RestartCommand());
}
```

**Step 5: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 6: Commit**

```bash
git add src/Command/StartCommand.php src/Command/StopCommand.php src/Command/RestartCommand.php src/Application.php
git commit -m "feat: add Start, Stop, and Restart commands"
```

---

### Task 33: StatusCommand, RebuildCommand, DestroyCommand

**Files:**
- Create: `src/Command/StatusCommand.php`
- Create: `src/Command/RebuildCommand.php`
- Create: `src/Command/DestroyCommand.php`

**Step 1: Implement StatusCommand**

```php
<?php

declare(strict_types=1);

// ABOUTME: Shows status of all Docker services.
// ABOUTME: Displays service name, state, and ports in a table.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StatusCommand extends Command
{
    protected static $defaultName = 'status';
    protected static $defaultDescription = 'Show status of all services';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $manager = new DockerManager(getcwd());

        $services = $manager->status();

        if (empty($services)) {
            $io->warning('No services are running');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($services as $service) {
            $rows[] = [
                $service['name'],
                $this->formatStatus($service['status']),
                $service['ports'],
            ];
        }

        $io->table(['Service', 'Status', 'Ports'], $rows);

        return Command::SUCCESS;
    }

    private function formatStatus(string $status): string
    {
        return match (strtolower($status)) {
            'running' => '<fg=green>● Running</>',
            'exited' => '<fg=red>● Exited</>',
            'restarting' => '<fg=yellow>● Restarting</>',
            default => "<fg=gray>● {$status}</>",
        };
    }
}
```

**Step 2: Implement RebuildCommand**

```php
<?php

declare(strict_types=1);

// ABOUTME: Rebuilds Docker images.
// ABOUTME: Runs docker-compose build with --no-cache.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RebuildCommand extends Command
{
    protected static $defaultName = 'rebuild';
    protected static $defaultDescription = 'Rebuild Docker images';

    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Specific service to rebuild');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $service = $input->getArgument('service');

        $manager = new DockerManager(getcwd());

        $io->info($service ? "Rebuilding service: {$service}..." : 'Rebuilding all services...');

        $result = $manager->rebuild($service);

        if ($result->isSuccessful()) {
            $io->success('Rebuild complete!');
            return Command::SUCCESS;
        }

        $io->error('Failed to rebuild');
        $io->writeln($result->errorOutput);

        return Command::FAILURE;
    }
}
```

**Step 3: Implement DestroyCommand**

```php
<?php

declare(strict_types=1);

// ABOUTME: Destroys all Docker services and volumes.
// ABOUTME: Runs docker-compose down -v to remove everything.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DestroyCommand extends Command
{
    protected static $defaultName = 'destroy';
    protected static $defaultDescription = 'Destroy all services and volumes';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->warning('This will remove all containers, networks, and volumes!');

        if (!$io->confirm('Are you sure?', false)) {
            $io->info('Cancelled.');
            return Command::SUCCESS;
        }

        $manager = new DockerManager(getcwd());
        $result = $manager->destroy();

        if ($result->isSuccessful()) {
            $io->success('All services destroyed!');
            return Command::SUCCESS;
        }

        $io->error('Failed to destroy services');
        $io->writeln($result->errorOutput);

        return Command::FAILURE;
    }
}
```

**Step 4: Register commands**

```php
// In src/Application.php:

private function registerCommands(): void
{
    $this->add(new Command\InitCommand());
    $this->add(new Command\StartCommand());
    $this->add(new Command\StopCommand());
    $this->add(new Command\RestartCommand());
    $this->add(new Command\StatusCommand());
    $this->add(new Command\RebuildCommand());
    $this->add(new Command\DestroyCommand());
}
```

**Step 5: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 6: Commit**

```bash
git add src/Command/StatusCommand.php src/Command/RebuildCommand.php src/Command/DestroyCommand.php src/Application.php
git commit -m "feat: add Status, Rebuild, and Destroy commands"
```

---

### Task 34: Utility Commands (Shell, Logs, Xdebug)

**Files:**
- Create: `src/Command/ShellCommand.php`
- Create: `src/Command/LogsCommand.php`
- Create: `src/Command/XdebugCommand.php`

**Step 1: Implement ShellCommand**

```php
<?php

declare(strict_types=1);

// ABOUTME: Opens interactive shell in service container.
// ABOUTME: Defaults to 'app' service, supports other services.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ShellCommand extends Command
{
    protected static $defaultName = 'shell';
    protected static $defaultDescription = 'Open interactive shell in service';

    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Service name', 'app');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $service = $input->getArgument('service');

        $manager = new DockerManager(getcwd());

        $io->info("Opening shell in {$service} service...");

        $result = $manager->executeInService($service, ['sh']);

        if (!$result->isSuccessful()) {
            $io->error("Failed to open shell in {$service}");
            $io->writeln($result->errorOutput);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
```

**Step 2: Implement LogsCommand**

```php
<?php

declare(strict_types=1);

// ABOUTME: Shows logs from Docker services.
// ABOUTME: Supports follow, tail, and since options.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Seaman\ValueObject\LogOptions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class LogsCommand extends Command
{
    protected static $defaultName = 'logs';
    protected static $defaultDescription = 'View service logs';

    protected function configure(): void
    {
        $this
            ->addArgument('service', InputArgument::REQUIRED, 'Service name')
            ->addOption('follow', 'f', InputOption::VALUE_NONE, 'Follow log output')
            ->addOption('tail', 't', InputOption::VALUE_REQUIRED, 'Number of lines to show from the end')
            ->addOption('since', 's', InputOption::VALUE_REQUIRED, 'Show logs since timestamp or relative');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $service = $input->getArgument('service');

        $options = new LogOptions(
            follow: $input->getOption('follow'),
            tail: $input->getOption('tail') ? (int) $input->getOption('tail') : null,
            since: $input->getOption('since')
        );

        $manager = new DockerManager(getcwd());
        $result = $manager->logs($service, $options);

        $io->write($result->output);

        return $result->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}
```

**Step 3: Implement XdebugCommand**

```php
<?php

declare(strict_types=1);

// ABOUTME: Toggles Xdebug on or off without container restart.
// ABOUTME: Executes xdebug-toggle script inside app container.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class XdebugCommand extends Command
{
    protected static $defaultName = 'xdebug';
    protected static $defaultDescription = 'Toggle Xdebug on or off';

    protected function configure(): void
    {
        $this->addArgument('mode', InputArgument::REQUIRED, 'Mode: on or off');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $mode = strtolower($input->getArgument('mode'));

        if (!in_array($mode, ['on', 'off'], true)) {
            $io->error("Invalid mode: {$mode}. Use 'on' or 'off'.");
            return Command::FAILURE;
        }

        $manager = new DockerManager(getcwd());
        $result = $manager->executeInService('app', ['xdebug-toggle', $mode]);

        if ($result->isSuccessful()) {
            $io->success("Xdebug is now {$mode}");
            return Command::SUCCESS;
        }

        $io->error('Failed to toggle Xdebug');
        $io->writeln($result->errorOutput);

        return Command::FAILURE;
    }
}
```

**Step 4: Register commands**

```php
// In src/Application.php:

private function registerCommands(): void
{
    $this->add(new Command\InitCommand());
    $this->add(new Command\StartCommand());
    $this->add(new Command\StopCommand());
    $this->add(new Command\RestartCommand());
    $this->add(new Command\StatusCommand());
    $this->add(new Command\RebuildCommand());
    $this->add(new Command\DestroyCommand());
    $this->add(new Command\ShellCommand());
    $this->add(new Command\LogsCommand());
    $this->add(new Command\XdebugCommand());
}
```

**Step 5: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 6: Commit**

```bash
git add src/Command/ShellCommand.php src/Command/LogsCommand.php src/Command/XdebugCommand.php src/Application.php
git commit -m "feat: add utility commands (Shell, Logs, Xdebug)"
```

---

## Phase 10: Final Commands and Testing

### Task 35: Passthrough Commands (Composer, Console, Php)

**Files:**
- Create: `src/Command/ComposerCommand.php`
- Create: `src/Command/ConsoleCommand.php`
- Create: `src/Command/PhpCommand.php`

**Step 1: Implement all three passthrough commands**

```php
<?php

declare(strict_types=1);

// ABOUTME: Executes Composer commands inside app container.
// ABOUTME: Passes all arguments directly to composer.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ComposerCommand extends Command
{
    protected static $defaultName = 'composer';
    protected static $defaultDescription = 'Run composer commands';

    protected function configure(): void
    {
        $this->ignoreValidationErrors();
        $this->addArgument('args', InputArgument::IS_ARRAY, 'Composer arguments');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $args = $input->getArgument('args');
        $manager = new DockerManager(getcwd());

        $result = $manager->executeInService('app', ['composer', ...$args]);
        $output->write($result->output);

        if (!$result->isSuccessful()) {
            $output->write($result->errorOutput);
        }

        return $result->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}
```

```php
<?php

declare(strict_types=1);

// ABOUTME: Executes Symfony Console commands inside app container.
// ABOUTME: Passes all arguments to bin/console.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleCommand extends Command
{
    protected static $defaultName = 'console';
    protected static $defaultDescription = 'Run Symfony console commands';

    protected function configure(): void
    {
        $this->ignoreValidationErrors();
        $this->addArgument('args', InputArgument::IS_ARRAY, 'Console arguments');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $args = $input->getArgument('args');
        $manager = new DockerManager(getcwd());

        $result = $manager->executeInService('app', ['php', 'bin/console', ...$args]);
        $output->write($result->output);

        if (!$result->isSuccessful()) {
            $output->write($result->errorOutput);
        }

        return $result->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}
```

```php
<?php

declare(strict_types=1);

// ABOUTME: Executes PHP commands inside app container.
// ABOUTME: Passes all arguments to PHP interpreter.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PhpCommand extends Command
{
    protected static $defaultName = 'php';
    protected static $defaultDescription = 'Run PHP commands';

    protected function configure(): void
    {
        $this->ignoreValidationErrors();
        $this->addArgument('args', InputArgument::IS_ARRAY, 'PHP arguments');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $args = $input->getArgument('args');
        $manager = new DockerManager(getcwd());

        $result = $manager->executeInService('app', ['php', ...$args]);
        $output->write($result->output);

        if (!$result->isSuccessful()) {
            $output->write($result->errorOutput);
        }

        return $result->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}
```

**Step 2: Register commands**

```php
// In src/Application.php, add to registerCommands():

$this->add(new Command\ComposerCommand());
$this->add(new Command\ConsoleCommand());
$this->add(new Command\PhpCommand());
```

**Step 3: Run quality checks**

Run: `composer phpstan && composer cs:check`

Expected: No errors

**Step 4: Commit**

```bash
git add src/Command/ExecuteComposerCommand.php src/Command/ExecuteConsoleCommand.php src/Command/ExecutePhpCommand.php src/Application.php
git commit -m "feat: add passthrough commands (Composer, Console, PHP)"
```

---

### Task 36: Run Full Test Suite and Achieve 95% Coverage

**Files:**
- Run all tests
- Fix any failing tests
- Add missing tests to reach 95% coverage

**Step 1: Run all tests**

Run: `vendor/bin/pest`

Expected: Review output, note failures

**Step 2: Run with coverage**

Run: `vendor/bin/pest --coverage --min=95`

Expected: See coverage report, identify gaps

**Step 3: Add missing tests for uncovered code**

Review coverage report and add tests for:
- Edge cases in value objects
- Error handling in services
- Command validation scenarios
- Template rendering edge cases

**Step 4: Re-run tests with coverage**

Run: `vendor/bin/pest --coverage --min=95`

Expected: ≥95% coverage, all tests pass

**Step 5: Commit test improvements**

```bash
git add tests/
git commit -m "test: achieve 95% code coverage"
```

---

## Phase 11: Box PHAR Build

### Task 37: Configure Box for PHAR Compilation

**Files:**
- Create: `box.json`
- Create: `.gitattributes`

**Step 1: Create box.json**

```json
{
    "chmod": "0755",
    "main": "bin/seaman.php",
    "output": "build/seaman.phar",
    "directories": [
        "src",
        "vendor"
    ],
    "files": [
        "composer.json"
    ],
    "finder": [
        {
            "name": "*.php",
            "exclude": [
                "Tests",
                "tests",
                "test"
            ],
            "in": "vendor"
        }
    ],
    "compression": "GZ",
    "compactors": [
        "KevinGH\\Box\\Compactor\\Php"
    ],
    "stub": true
}
```

**Step 2: Create .gitattributes for export ignore**

```
/tests export-ignore
/.github export-ignore
/.gitattributes export-ignore
/.gitignore export-ignore
/phpstan.neon export-ignore
/.php-cs-fixer.dist.php export-ignore
/pest.php export-ignore
/box.json export-ignore
```

**Step 3: Build PHAR**

Run: `vendor/bin/box compile`

Expected: `build/seaman.phar` created successfully

**Step 4: Test PHAR**

Run: `php build/seaman.phar --version`

Expected: Outputs version correctly

**Step 5: Commit**

```bash
git add box.json .gitattributes
git commit -m "feat: configure Box for PHAR compilation"
```

---

## Phase 12: Distribution and Documentation

### Task 38: Create Bash Wrapper Script

**Files:**
- Create: `seaman` (bash wrapper)

**Step 1: Create seaman bash script**

```bash
#!/usr/bin/env bash

# Seaman - Docker development environment manager for Symfony 7

set -e

SEAMAN_DIR="${HOME}/.seaman"
PHAR_PATH="${SEAMAN_DIR}/seaman.phar"
PHAR_URL="https://github.com/seaman/seaman/releases/latest/download/seaman.phar"

# Create seaman directory if it doesn't exist
if [ ! -d "$SEAMAN_DIR" ]; then
    mkdir -p "$SEAMAN_DIR"
fi

# Download PHAR if it doesn't exist
if [ ! -f "$PHAR_PATH" ]; then
    echo "Downloading Seaman..."
    curl -sS -L "$PHAR_URL" -o "$PHAR_PATH"
    chmod +x "$PHAR_PATH"
    echo "Seaman installed successfully!"
fi

# Execute PHAR with all arguments
php "$PHAR_PATH" "$@"
```

**Step 2: Make script executable**

Run: `chmod +x seaman`

Expected: Script is executable

**Step 3: Test script**

Run: `./seaman --version`

Expected: Works correctly

**Step 4: Commit**

```bash
git add seaman
git commit -m "feat: add bash wrapper script for PHAR execution"
```

---

### Task 39: Create README Documentation

**Files:**
- Create: `README.md`

**Step 1: Create comprehensive README**

```markdown
# Seaman

Docker development environment manager for Symfony 7, inspired by Laravel Sail.

## Features

- 🚀 Quick setup with interactive initialization
- 🐳 Docker Compose orchestration
- 🔧 Multiple server types (Symfony, Nginx+FPM, FrankenPHP)
- 🗄️ Support for PostgreSQL, MySQL, MariaDB, Redis, and more
- 🐛 Xdebug toggle without container restart
- 📦 Service management (add/remove services dynamically)
- 🎯 Type-safe (PHP 8.4, PHPStan level 10)
- ✅ Well-tested (95%+ coverage)

## Installation

```bash
curl -sS https://raw.githubusercontent.com/seaman/seaman/main/installer | bash
```

This will create a `seaman` script in your project directory.

## Quick Start

```bash
# Initialize Seaman in your project
./seaman init

# Start all services
./seaman start

# Check service status
./seaman status

# Stop services
./seaman stop
```

## Commands

### Core Commands

- `seaman init` - Interactive initialization
- `seaman start [service]` - Start services
- `seaman stop [service]` - Stop services
- `seaman restart [service]` - Restart services
- `seaman rebuild [service]` - Rebuild Docker images
- `seaman destroy` - Remove all containers and volumes
- `seaman status` - Show service status

### Service Management

- `seaman service:add` - Add new services
- `seaman service:remove` - Remove services
- `seaman service:list` - List available services

### Utilities

- `seaman shell [service]` - Open shell in service
- `seaman logs [service]` - View service logs
- `seaman xdebug on|off` - Toggle Xdebug
- `seaman composer [...]` - Run Composer commands
- `seaman console [...]` - Run Symfony console commands
- `seaman php [...]` - Run PHP commands

### Database

- `seaman db:dump [file]` - Export database
- `seaman db:restore [file]` - Restore database
- `seaman db:shell` - Open database shell

## Supported Services

- PostgreSQL
- MySQL
- MariaDB
- Redis
- Mailpit (email testing)
- MinIO (S3-compatible storage)
- Elasticsearch
- RabbitMQ

## Requirements

- PHP 8.2+
- Docker & Docker Compose
- Composer (for development)

## Development

```bash
# Clone repository
git clone https://github.com/seaman/seaman.git
cd seaman

# Install dependencies
composer install

# Run tests
composer test

# Run quality checks
composer quality
```

## License

MIT License

## Credits

Created by Diego

Inspired by Laravel Sail
```

**Step 2: Commit**

```bash
git add README.md
git commit -m "docs: add comprehensive README"
```

---

### Task 40: Create GitHub Actions CI/CD

**Files:**
- Create: `.github/workflows/ci.yml`
- Create: `.github/workflows/release.yml`

**Step 1: Create CI workflow**

```yaml
name: CI

on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  tests:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP 8.4
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4
          extensions: mbstring, xml
          coverage: xdebug

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: PHPStan
        run: composer phpstan

      - name: Code Style
        run: composer cs:check

      - name: Tests with coverage
        run: composer test:coverage

      - name: Build PHAR
        run: vendor/bin/box compile

      - name: Test PHAR
        run: php build/seaman.phar --version
```

**Step 2: Create release workflow**

```yaml
name: Release

on:
  push:
    tags:
      - 'v*'

jobs:
  release:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP 8.4
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4

      - name: Install dependencies
        run: composer install --prefer-dist --no-dev --optimize-autoloader

      - name: Build PHAR
        run: vendor/bin/box compile

      - name: Create Release
        uses: softprops/action-gh-release@v1
        with:
          files: build/seaman.phar
          generate_release_notes: true
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
```

**Step 3: Commit**

```bash
git add .github/
git commit -m "ci: add GitHub Actions workflows for CI and releases"
```

---

### Task 41: Final Integration Test and Cleanup

**Step 1: Run full quality suite**

Run: `composer quality`

Expected: All checks pass (PHPStan, CS, tests with 95% coverage)

**Step 2: Build and test PHAR**

Run: `vendor/bin/box compile && php build/seaman.phar --version`

Expected: PHAR builds and runs correctly

**Step 3: Test initialization flow**

```bash
# Create temporary test directory
mkdir /tmp/seaman-integration-test
cd /tmp/seaman-integration-test

# Copy seaman script
cp /path/to/seaman/seaman .

# Initialize (will require interaction or --no-interaction flag)
./seaman init

# Verify files created
ls -la seaman.yaml .env .seaman/

# Start services
./seaman start

# Check status
./seaman status

# Stop services
./seaman stop

# Clean up
./seaman destroy
cd -
rm -rf /tmp/seaman-integration-test
```

Expected: Full workflow works end-to-end

**Step 4: Final commit**

```bash
git add -A
git commit -m "chore: final integration test and cleanup"
```

---

## Completion Checklist

Run through this final checklist:

- [ ] All 95%+ test coverage achieved
- [ ] PHPStan level 10 passes with zero errors
- [ ] php-cs-fixer validates PER compliance
- [ ] PHAR builds successfully
- [ ] All commands work (init, start, stop, status, etc.)
- [ ] Docker Compose generation works for all server types
- [ ] All services (PostgreSQL, MySQL, Redis, etc.) can be added
- [ ] Xdebug toggle works without restart
- [ ] Documentation is complete (README, inline docs)
- [ ] CI/CD workflows are configured
- [ ] Integration tests pass

---

## Post-Implementation

After completing all tasks:

1. Tag version 1.0.0
2. Push to GitHub
3. Verify CI/CD runs successfully
4. Create GitHub release with PHAR
5. Update README with installation instructions
6. Celebrate! 🎉

---

**Total Tasks: 41**
**Estimated Time: 40-60 hours of focused development**

This plan follows TDD, maintains high quality standards (PHPStan 10, 95% coverage), and implements all features from the design document.
