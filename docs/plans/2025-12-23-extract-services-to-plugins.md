# Extract Core Services to Bundled Plugins - Implementation Plan

> **Status: COMPLETED** - All phases implemented and tested.

**Goal:** Extract 16 services from core to bundled plugins in `plugins/` directory, keeping only App and Traefik in core.

**Architecture:** Create BundledPluginLoader to discover plugins from `plugins/` directory. Each service becomes a plugin with a Twig template. Priority: bundled < composer < local.

**Tech Stack:** PHP 8.4, Twig templates, PHPStan level 10, Pest tests.

## Implementation Summary

- **Phase 1-3:** Plugin infrastructure with BundledPluginLoader, PluginLoaderTrait, and PluginRegistry integration
- **Phase 4:** Removed 17 old service classes, ServiceRegistry auto-loads bundled plugins
- **Phase 5:** Added DatabaseOperations support for database plugins (MySQL, PostgreSQL, MariaDB, MongoDB, SQLite)
- **17 bundled plugins** created in `plugins/` directory
- **All tests pass** with 623+ assertions

---

## Phase 1: Infrastructure

### Task 1: Create BundledPluginLoader

**Files:**
- Create: `src/Plugin/Loader/BundledPluginLoader.php`
- Test: `tests/Unit/Plugin/Loader/BundledPluginLoaderTest.php`

**Step 1: Write the test file**

```php
<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Loader;

use Seaman\Plugin\Loader\BundledPluginLoader;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir() . '/seaman-bundled-test-' . uniqid();
    mkdir($this->testDir . '/redis/src', 0755, true);
});

afterEach(function () {
    if (isset($this->testDir) && is_dir($this->testDir)) {
        exec("rm -rf {$this->testDir}");
    }
});

test('returns empty array when plugins directory does not exist', function () {
    $loader = new BundledPluginLoader('/nonexistent/path');
    expect($loader->load())->toBe([]);
});

test('returns empty array when plugins directory is empty', function () {
    $loader = new BundledPluginLoader($this->testDir);
    expect($loader->load())->toBe([]);
});

test('discovers plugin from bundled directory', function () {
    $pluginCode = <<<'PHP'
<?php
declare(strict_types=1);
namespace Seaman\Plugin\Redis;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\Config\ConfigSchema;

#[AsSeamanPlugin(name: 'seaman/redis-plugin', version: '1.0.0', description: 'Redis')]
final class RedisPlugin implements PluginInterface
{
    public function getName(): string { return 'seaman/redis-plugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'Redis'; }
    public function configSchema(): ConfigSchema { return ConfigSchema::create(); }
    public function configure(array $values): void {}
}
PHP;
    file_put_contents($this->testDir . '/redis/src/RedisPlugin.php', $pluginCode);

    $loader = new BundledPluginLoader($this->testDir);
    $plugins = $loader->load();

    expect($plugins)->toHaveCount(1);
    expect($plugins[0]->getName())->toBe('seaman/redis-plugin');
});
```

**Step 2: Run test to verify it fails**

```bash
./vendor/bin/pest tests/Unit/Plugin/Loader/BundledPluginLoaderTest.php
```

Expected: FAIL - Class not found

**Step 3: Create BundledPluginLoader**

```php
<?php

// ABOUTME: Loads bundled plugins from the plugins/ directory within Seaman.
// ABOUTME: These plugins ship with Seaman and are always available.

declare(strict_types=1);

namespace Seaman\Plugin\Loader;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;

final readonly class BundledPluginLoader implements PluginLoaderInterface
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

        // Scan plugins/*/src/*Plugin.php
        $pattern = $this->pluginsDir . '/*/src/*Plugin.php';
        $files = glob($pattern);

        if ($files === false) {
            return [];
        }

        foreach ($files as $filePath) {
            $plugin = $this->loadPlugin($filePath);
            if ($plugin !== null) {
                $plugins[] = $plugin;
            }
        }

        return $plugins;
    }

    private function loadPlugin(string $filePath): ?PluginInterface
    {
        $className = $this->extractClassName($filePath);
        if ($className === null) {
            return null;
        }

        // For bundled plugins, classes should already be autoloaded
        // But require_once for safety during development
        require_once $filePath;

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

**Step 4: Run test to verify it passes**

```bash
./vendor/bin/pest tests/Unit/Plugin/Loader/BundledPluginLoaderTest.php
```

Expected: PASS

**Step 5: Run PHPStan**

```bash
./vendor/bin/phpstan analyse src/Plugin/Loader/BundledPluginLoader.php
```

**Step 6: Commit**

```bash
git add src/Plugin/Loader/BundledPluginLoader.php tests/Unit/Plugin/Loader/BundledPluginLoaderTest.php
git commit -m "feat(plugins): add BundledPluginLoader for built-in plugins"
```

---

### Task 2: Modify PluginRegistry to load bundled plugins

**Files:**
- Modify: `src/Plugin/PluginRegistry.php`
- Test: `tests/Unit/Plugin/PluginRegistryTest.php`

**Step 1: Update PluginRegistry::discover() to add bundled loader**

Modify `src/Plugin/PluginRegistry.php` - add bundled plugins as first priority:

```php
public static function discover(
    string $projectRoot,
    string $localPluginsDir,
    array $pluginConfig,
    ?string $bundledPluginsDir = null,
): self {
    $registry = new self();

    // 1. Load bundled plugins first (lowest priority, can be overridden)
    if ($bundledPluginsDir !== null && is_dir($bundledPluginsDir)) {
        $bundledLoader = new Loader\BundledPluginLoader($bundledPluginsDir);
        foreach ($bundledLoader->load() as $plugin) {
            $config = $pluginConfig[$plugin->getName()] ?? [];
            $registry->register($plugin, $config, 'bundled');
        }
    }

    // 2. Load Composer plugins (can override bundled)
    $composerLoader = new Loader\ComposerPluginLoader($projectRoot);
    foreach ($composerLoader->load() as $plugin) {
        $config = $pluginConfig[$plugin->getName()] ?? [];
        $registry->register($plugin, $config, 'composer');
    }

    // 3. Load local plugins (highest priority, can override all)
    $localLoader = new Loader\LocalPluginLoader($localPluginsDir);
    foreach ($localLoader->load() as $plugin) {
        $config = $pluginConfig[$plugin->getName()] ?? [];
        $registry->register($plugin, $config, 'local');
    }

    return $registry;
}
```

**Step 2: Update container.php to pass bundled plugins directory**

In `config/container.php`, modify the PluginRegistry factory:

```php
PluginRegistry::class => factory(
    function (ContainerInterface $c): PluginRegistry {
        $projectRoot = $c->get('projectRoot');
        $pluginConfig = [];

        // Determine bundled plugins directory
        // When running from PHAR, it's inside the archive
        // When running from source, it's at repo root
        $bundledPluginsDir = \Phar::running()
            ? \Phar::running() . '/plugins'
            : dirname(__DIR__) . '/plugins';

        // Load plugin config from YAML...
        $yamlPath = $projectRoot . '/.seaman/seaman.yaml';
        if (file_exists($yamlPath)) {
            // ... existing code ...
        }

        return PluginRegistry::discover(
            projectRoot: $projectRoot,
            localPluginsDir: $projectRoot . '/.seaman/plugins',
            pluginConfig: $pluginConfig,
            bundledPluginsDir: $bundledPluginsDir,
        );
    },
),
```

**Step 3: Run existing tests**

```bash
./vendor/bin/pest tests/Unit/Plugin/
```

**Step 4: Commit**

```bash
git add src/Plugin/PluginRegistry.php config/container.php
git commit -m "feat(plugins): integrate BundledPluginLoader in PluginRegistry"
```

---

### Task 3: Create plugins directory structure

**Step 1: Create base directories**

```bash
mkdir -p plugins
```

**Step 2: Commit empty structure**

```bash
touch plugins/.gitkeep
git add plugins/.gitkeep
git commit -m "chore: create plugins directory for bundled plugins"
```

---

## Phase 2: Create Bundled Plugins

### Task 4: Create Redis Plugin (template for all cache plugins)

**Files:**
- Create: `plugins/redis/src/RedisPlugin.php`
- Create: `plugins/redis/templates/redis.yaml.twig`
- Reference: `src/Service/Container/RedisService.php`

**Step 1: Create plugin directory**

```bash
mkdir -p plugins/redis/src plugins/redis/templates
```

**Step 2: Create RedisPlugin.php**

```php
<?php

// ABOUTME: Redis cache service plugin for Seaman.
// ABOUTME: Provides Redis as a bundled service.

declare(strict_types=1);

namespace Seaman\Plugin\Redis;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/redis-plugin',
    version: '1.0.0',
    description: 'Redis cache and session storage',
)]
final class RedisPlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: '7-alpine')
            ->integer('port', default: 6379, min: 1, max: 65535);

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/redis-plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Redis cache and session storage';
    }

    public function configSchema(): ConfigSchema
    {
        return $this->schema;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function configure(array $values): void
    {
        $this->config = $this->schema->validate($values);
    }

    #[ProvidesService(name: 'redis', category: ServiceCategory::Cache)]
    public function redisService(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: 'redis',
            template: __DIR__ . '/../templates/redis.yaml.twig',
            displayName: 'Redis',
            description: 'Redis cache and session storage',
            icon: '🧵',
            category: ServiceCategory::Cache,
            ports: [(int) $this->config['port']],
            defaultConfig: [
                'version' => $this->config['version'],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD', 'redis-cli', 'ping'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
        );
    }
}
```

**Step 3: Create redis.yaml.twig template**

```twig
redis:
  image: redis:{{ config.version | default('7-alpine') }}
  ports:
    - "${REDIS_PORT:-6379}:6379"
  networks:
    - seaman
  healthcheck:
    test: ["CMD", "redis-cli", "ping"]
    interval: 10s
    timeout: 5s
    retries: 5
```

**Step 4: Add autoload to composer.json**

Add to `composer.json` autoload.psr-4:

```json
"Seaman\\Plugin\\Redis\\": "plugins/redis/src/"
```

**Step 5: Run composer dump-autoload**

```bash
composer dump-autoload
```

**Step 6: Run tests to verify plugin loads**

```bash
./vendor/bin/pest tests/Unit/Plugin/
```

**Step 7: Commit**

```bash
git add plugins/redis/ composer.json
git commit -m "feat(plugins): add Redis bundled plugin"
```

---

### Task 5: Create Valkey Plugin

**Files:**
- Create: `plugins/valkey/src/ValkeyPlugin.php`
- Create: `plugins/valkey/templates/valkey.yaml.twig`

**Step 1: Create plugin (similar to Redis)**

```bash
mkdir -p plugins/valkey/src plugins/valkey/templates
```

**Step 2: Create ValkeyPlugin.php**

```php
<?php

// ABOUTME: Valkey cache service plugin for Seaman.
// ABOUTME: Provides Valkey (Redis fork) as a bundled service.

declare(strict_types=1);

namespace Seaman\Plugin\Valkey;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/valkey-plugin',
    version: '1.0.0',
    description: 'Valkey cache and session storage (Redis fork)',
)]
final class ValkeyPlugin implements PluginInterface
{
    private ConfigSchema $schema;
    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: '8.0-alpine')
            ->integer('port', default: 6379, min: 1, max: 65535);
        $this->config = $this->schema->validate([]);
    }

    public function getName(): string { return 'seaman/valkey-plugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'Valkey cache and session storage (Redis fork)'; }
    public function configSchema(): ConfigSchema { return $this->schema; }
    public function configure(array $values): void { $this->config = $this->schema->validate($values); }

    #[ProvidesService(name: 'valkey', category: ServiceCategory::Cache)]
    public function valkeyService(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: 'valkey',
            template: __DIR__ . '/../templates/valkey.yaml.twig',
            displayName: 'Valkey',
            description: 'Valkey cache and session storage (Redis fork)',
            icon: '🧵',
            category: ServiceCategory::Cache,
            ports: [(int) $this->config['port']],
            defaultConfig: ['version' => $this->config['version']],
            healthCheck: new HealthCheck(
                test: ['CMD', 'valkey-cli', 'ping'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
        );
    }
}
```

**Step 3: Create valkey.yaml.twig**

```twig
valkey:
  image: valkey/valkey:{{ config.version | default('8.0-alpine') }}
  ports:
    - "${VALKEY_PORT:-6379}:6379"
  networks:
    - seaman
  healthcheck:
    test: ["CMD", "valkey-cli", "ping"]
    interval: 10s
    timeout: 5s
    retries: 5
```

**Step 4: Add autoload and commit**

```bash
# Add to composer.json: "Seaman\\Plugin\\Valkey\\": "plugins/valkey/src/"
composer dump-autoload
git add plugins/valkey/ composer.json
git commit -m "feat(plugins): add Valkey bundled plugin"
```

---

### Task 6: Create Memcached Plugin

**Files:**
- Create: `plugins/memcached/src/MemcachedPlugin.php`
- Create: `plugins/memcached/templates/memcached.yaml.twig`

Follow same pattern as Redis/Valkey. Key differences:
- Port: 11211
- Image: memcached:alpine
- No healthcheck command (use TCP check)

**Commit message:** `feat(plugins): add Memcached bundled plugin`

---

### Task 7: Create MySQL Plugin (template for database plugins)

**Files:**
- Create: `plugins/mysql/src/MySQLPlugin.php`
- Create: `plugins/mysql/templates/mysql.yaml.twig`

**Step 1: Create plugin with database-specific config**

```php
<?php

// ABOUTME: MySQL database service plugin for Seaman.
// ABOUTME: Provides MySQL as a bundled database service.

declare(strict_types=1);

namespace Seaman\Plugin\MySQL;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/mysql-plugin',
    version: '1.0.0',
    description: 'MySQL relational database',
)]
final class MySQLPlugin implements PluginInterface
{
    private ConfigSchema $schema;
    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: '8.0')
            ->integer('port', default: 3306, min: 1, max: 65535)
            ->string('database', default: 'seaman')
            ->string('user', default: 'seaman')
            ->string('password', default: 'seaman')
            ->string('root_password', default: 'root');
        $this->config = $this->schema->validate([]);
    }

    public function getName(): string { return 'seaman/mysql-plugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'MySQL relational database'; }
    public function configSchema(): ConfigSchema { return $this->schema; }
    public function configure(array $values): void { $this->config = $this->schema->validate($values); }

    #[ProvidesService(name: 'mysql', category: ServiceCategory::Database)]
    public function mysqlService(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: 'mysql',
            template: __DIR__ . '/../templates/mysql.yaml.twig',
            displayName: 'MySQL',
            description: 'MySQL relational database',
            icon: '🐬',
            category: ServiceCategory::Database,
            ports: [(int) $this->config['port']],
            defaultConfig: [
                'version' => $this->config['version'],
                'environment' => [
                    'MYSQL_DATABASE' => $this->config['database'],
                    'MYSQL_USER' => $this->config['user'],
                    'MYSQL_PASSWORD' => $this->config['password'],
                    'MYSQL_ROOT_PASSWORD' => $this->config['root_password'],
                ],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD', 'mysqladmin', 'ping', '-h', 'localhost'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
        );
    }
}
```

**Step 2: Create mysql.yaml.twig**

```twig
mysql:
  image: mysql:{{ config.version | default('8.0') }}
  environment:
    MYSQL_DATABASE: {{ config.environment.MYSQL_DATABASE | default('seaman') }}
    MYSQL_USER: {{ config.environment.MYSQL_USER | default('seaman') }}
    MYSQL_PASSWORD: {{ config.environment.MYSQL_PASSWORD | default('seaman') }}
    MYSQL_ROOT_PASSWORD: {{ config.environment.MYSQL_ROOT_PASSWORD | default('root') }}
  ports:
    - "${DB_PORT:-3306}:3306"
  volumes:
    - mysql_data:/var/lib/mysql
  networks:
    - seaman
  healthcheck:
    test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
    interval: 10s
    timeout: 5s
    retries: 5
```

**Commit message:** `feat(plugins): add MySQL bundled plugin`

---

### Tasks 8-19: Create remaining plugins

Follow the same pattern for each:

| Task | Plugin | Category | Port | Image |
|------|--------|----------|------|-------|
| 8 | PostgreSQL | Database | 5432 | postgres:16-alpine |
| 9 | MariaDB | Database | 3306 | mariadb:11 |
| 10 | MongoDB | Database | 27017 | mongo:7 |
| 11 | SQLite | Database | 0 | (no container, config only) |
| 12 | Elasticsearch | Search | 9200 | elasticsearch:8.x |
| 13 | OpenSearch | Search | 9200 | opensearchproject/opensearch:2 |
| 14 | RabbitMQ | Queue | 5672 | rabbitmq:3-management-alpine |
| 15 | Kafka | Queue | 9092 | bitnami/kafka:latest |
| 16 | Mercure | Realtime | 3000 | dunglas/mercure |
| 17 | Soketi | Realtime | 6001 | quay.io/soketi/soketi |
| 18 | Mailpit | Tool | 8025 | axllent/mailpit |
| 19 | MinIO | Tool | 9000 | minio/minio |
| 20 | Dozzle | Tool | 9080 | amir20/dozzle |

For each task:
1. Create `plugins/<name>/src/<Name>Plugin.php`
2. Create `plugins/<name>/templates/<name>.yaml.twig`
3. Add autoload to composer.json
4. Commit: `feat(plugins): add <Name> bundled plugin`

---

## Phase 3: Update Autoloading

### Task 21: Update composer.json with all plugin autoloads

**Step 1: Add all plugin namespaces**

```json
{
    "autoload": {
        "psr-4": {
            "Seaman\\": "src/",
            "Seaman\\Plugin\\Redis\\": "plugins/redis/src/",
            "Seaman\\Plugin\\Valkey\\": "plugins/valkey/src/",
            "Seaman\\Plugin\\Memcached\\": "plugins/memcached/src/",
            "Seaman\\Plugin\\MySQL\\": "plugins/mysql/src/",
            "Seaman\\Plugin\\PostgreSQL\\": "plugins/postgresql/src/",
            "Seaman\\Plugin\\MariaDB\\": "plugins/mariadb/src/",
            "Seaman\\Plugin\\MongoDB\\": "plugins/mongodb/src/",
            "Seaman\\Plugin\\SQLite\\": "plugins/sqlite/src/",
            "Seaman\\Plugin\\Elasticsearch\\": "plugins/elasticsearch/src/",
            "Seaman\\Plugin\\OpenSearch\\": "plugins/opensearch/src/",
            "Seaman\\Plugin\\RabbitMQ\\": "plugins/rabbitmq/src/",
            "Seaman\\Plugin\\Kafka\\": "plugins/kafka/src/",
            "Seaman\\Plugin\\Mercure\\": "plugins/mercure/src/",
            "Seaman\\Plugin\\Soketi\\": "plugins/soketi/src/",
            "Seaman\\Plugin\\Mailpit\\": "plugins/mailpit/src/",
            "Seaman\\Plugin\\MinIO\\": "plugins/minio/src/",
            "Seaman\\Plugin\\Dozzle\\": "plugins/dozzle/src/"
        }
    }
}
```

**Step 2: Regenerate autoload**

```bash
composer dump-autoload
```

**Step 3: Commit**

```bash
git add composer.json
git commit -m "chore: add autoload for all bundled plugins"
```

---

## Phase 4: Simplify Core

### Task 22: Simplify Service enum

**Files:**
- Modify: `src/Enum/Service.php`

**Step 1: Remove migrated cases and simplify methods**

```php
<?php

declare(strict_types=1);

// ABOUTME: Enum representing core Docker services.
// ABOUTME: Most services are now provided by plugins.

namespace Seaman\Enum;

enum Service: string
{
    case App = 'app';
    case Traefik = 'traefik';
    case Custom = 'custom';
    case None = 'none';

    public function description(): string
    {
        return match ($this) {
            self::App => 'Symfony 7+ application',
            self::Traefik => 'Traefik reverse proxy with HTTPS support',
            self::Custom => 'Plugin-provided service',
            self::None => 'No service',
        };
    }

    public function port(): int
    {
        return match ($this) {
            self::App => 8000,
            self::Traefik => 443,
            self::Custom, self::None => 0,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::App => '📦',
            self::Traefik => '🔀',
            default => '⚙️',
        };
    }

    public function isRequired(): bool
    {
        return $this === self::Traefik;
    }
}
```

**Step 2: Run PHPStan to find broken references**

```bash
./vendor/bin/phpstan analyse
```

Fix any references to removed enum cases.

**Step 3: Commit**

```bash
git add src/Enum/Service.php
git commit -m "refactor: simplify Service enum - services now from plugins"
```

---

### Task 23: Update ServiceRegistry to not use ServiceDiscovery

**Files:**
- Modify: `src/Service/Container/ServiceRegistry.php`

**Step 1: Remove ServiceDiscovery usage**

The `create()` method should only register App and Traefik, rest come from plugins:

```php
public static function create(): ServiceRegistry
{
    $registry = new self();

    // Only register core services
    $registry->register(new AppService());
    $registry->register(new TraefikService());

    return $registry;
}
```

**Step 2: Commit**

```bash
git add src/Service/Container/ServiceRegistry.php
git commit -m "refactor: ServiceRegistry only loads core services"
```

---

### Task 24: Remove obsolete service classes

**Step 1: Delete service files**

```bash
rm src/Service/Container/RedisService.php
rm src/Service/Container/ValkeyService.php
rm src/Service/Container/MemcachedService.php
rm src/Service/Container/MysqlService.php
rm src/Service/Container/PostgresqlService.php
rm src/Service/Container/MariadbService.php
rm src/Service/Container/MongodbService.php
rm src/Service/Container/SqliteService.php
rm src/Service/Container/ElasticsearchService.php
rm src/Service/Container/OpenSearchService.php
rm src/Service/Container/RabbitmqService.php
rm src/Service/Container/KafkaService.php
rm src/Service/Container/MercureService.php
rm src/Service/Container/SoketiService.php
rm src/Service/Container/MailpitService.php
rm src/Service/Container/MinioService.php
rm src/Service/Container/DozzleService.php
rm src/Service/Container/ServiceDiscovery.php
```

**Step 2: Commit**

```bash
git add -A
git commit -m "refactor: remove obsolete service classes (now plugins)"
```

---

### Task 25: Remove or simplify AbstractService

**Files:**
- Modify or delete: `src/Service/Container/AbstractService.php`

If App and Traefik still need it, keep it. Otherwise remove.

**Commit:** `refactor: simplify AbstractService for core services only`

---

## Phase 5: Update Tests

### Task 26: Update service-related tests

**Step 1: Find affected tests**

```bash
grep -r "Service::" tests/ | grep -v "Service::App\|Service::Traefik\|Service::Custom"
```

**Step 2: Update each test to use plugin services or remove outdated tests**

**Step 3: Run full test suite**

```bash
./vendor/bin/pest
```

**Step 4: Commit**

```bash
git add tests/
git commit -m "test: update tests for plugin-based services"
```

---

### Task 27: Run full QA check

**Step 1: PHPStan**

```bash
./vendor/bin/phpstan analyse
```

**Step 2: PHP CS Fixer**

```bash
./vendor/bin/php-cs-fixer fix
```

**Step 3: Tests**

```bash
./vendor/bin/pest
```

**Step 4: Final commit**

```bash
git add -A
git commit -m "chore: fix remaining issues after service extraction"
```

---

## Phase 6: Documentation

### Task 28: Update documentation

**Files:**
- Modify: `docs/plugins.md` - mention bundled plugins
- Modify: `README.md` - if it references services

**Commit:** `docs: update documentation for bundled plugins`

---

## Summary

| Phase | Tasks | Description |
|-------|-------|-------------|
| 1 | 1-3 | Infrastructure (BundledPluginLoader, PluginRegistry) |
| 2 | 4-20 | Create 16 bundled plugins |
| 3 | 21 | Update autoloading |
| 4 | 22-25 | Simplify core (enum, registry, remove classes) |
| 5 | 26-27 | Update tests |
| 6 | 28 | Documentation |

**Total: ~28 tasks**

Each plugin creation (Tasks 4-20) follows the same pattern:
1. Create directory structure
2. Create Plugin class with `#[ProvidesService]`
3. Create Twig template
4. Add autoload
5. Commit
