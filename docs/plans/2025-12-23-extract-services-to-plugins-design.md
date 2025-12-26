# Extract Core Services to Bundled Plugins - Design

## Goal

Reduce core size and unify service definition pattern by extracting 16 services from core to bundled plugins.

## Decisions

- **Keep in core:** App, Traefik (essential infrastructure)
- **Extract to plugins:** All other services (16 total)
- **Plugin location:** `plugins/` directory at repo root
- **Loading mechanism:** New `BundledPluginLoader` with priority: bundled < composer < local
- **No composer.json per plugin:** Bundled plugins use main Seaman autoload

## Architecture

### Directory Structure

```
seaman/
├── src/                          # Core (reduced)
│   ├── Service/Container/
│   │   ├── AppService.php        # Stays
│   │   ├── TraefikService.php    # Stays
│   │   └── ServiceRegistry.php   # Modified
│   ├── Plugin/
│   │   └── Loader/
│   │       ├── BundledPluginLoader.php   # NEW
│   │       ├── ComposerPluginLoader.php
│   │       └── LocalPluginLoader.php
├── plugins/                      # NEW - Bundled plugins
│   ├── redis/
│   │   ├── src/RedisPlugin.php
│   │   └── templates/redis.yaml.twig
│   ├── mysql/
│   ├── postgresql/
│   └── ... (16 plugins total)
```

### Services to Extract

| Category | Services |
|----------|----------|
| Databases | MySQL, PostgreSQL, MariaDB, MongoDB, SQLite |
| Cache | Redis, Valkey, Memcached |
| Search | Elasticsearch, OpenSearch |
| Queues | RabbitMQ, Kafka |
| Realtime | Mercure, Soketi |
| Tools | Mailpit, MinIO, Dozzle |

### Plugin Structure (per plugin)

```
plugins/redis/
├── src/
│   └── RedisPlugin.php
└── templates/
    └── redis.yaml.twig
```

**RedisPlugin.php example:**
```php
<?php

declare(strict_types=1);

namespace Seaman\Plugin\Redis;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
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
            ports: [6379],
            healthCheck: new HealthCheck(
                test: ['CMD', 'redis-cli', 'ping'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
        );
    }

    // ... PluginInterface methods
}
```

### BundledPluginLoader

```php
<?php

declare(strict_types=1);

namespace Seaman\Plugin\Loader;

final class BundledPluginLoader implements PluginLoaderInterface
{
    public function __construct(
        private readonly string $bundledPluginsDir,
    ) {}

    /**
     * @param array<string, array<string, mixed>> $pluginConfig
     * @return list<LoadedPlugin>
     */
    public function discover(array $pluginConfig = []): array
    {
        $plugins = [];

        foreach (glob($this->bundledPluginsDir . '/*/src/*Plugin.php') as $file) {
            $className = $this->resolveClassName($file);
            $plugin = new $className();

            $config = $pluginConfig[$plugin->getName()] ?? [];
            $plugin->configure($config);

            $plugins[] = new LoadedPlugin(
                instance: $plugin,
                source: 'bundled',
            );
        }

        return $plugins;
    }
}
```

### Loading Priority

1. **Bundled** (`plugins/`) - Base services, always available
2. **Composer** (`vendor/`) - Installed packages
3. **Local** (`.seaman/plugins/`) - User customizations (can override bundled)

### Enum Simplification

**Before:**
```php
enum Service: string
{
    case App = 'app';
    case Traefik = 'traefik';
    case MySQL = 'mysql';
    case PostgreSQL = 'postgresql';
    case Redis = 'redis';
    // ... 16 more cases
    case Custom = 'custom';
}
```

**After:**
```php
enum Service: string
{
    case App = 'app';
    case Traefik = 'traefik';
    case Custom = 'custom';
}
```

Methods `port()`, `icon()`, `description()` removed - that info now lives in `ServiceDefinition`.

### Files to Remove

- `src/Service/Container/*Service.php` (16 files, except App and Traefik)
- `src/Service/Container/ServiceDiscovery.php`
- `src/Service/Container/AbstractService.php` (or keep for App/Traefik only)

## Migration Strategy

### Execution Order

1. Create `BundledPluginLoader` and modify `PluginRegistry`
2. Create 16 plugins in `plugins/` (one by one)
3. Update `composer.json` with autoload for each plugin
4. Simplify `Service` enum (remove migrated cases)
5. Remove obsolete classes
6. Update affected tests

### For Each Service

```
RedisService.php → plugins/redis/src/RedisPlugin.php
                 → plugins/redis/templates/redis.yaml.twig
```

Logic from `generateComposeConfig()` becomes Twig template.

### Backward Compatibility

- `seaman.yaml` works the same:
  ```yaml
  services:
    redis:
      enabled: true
      port: 6380
  ```
- Commands (`service:add redis`) work the same
- Only internal implementation changes

### Main Risk

Twig templates must generate exactly the same Docker Compose as current classes. Regression tests are key.

## Display in plugin:list

```
Installed plugins:

  📦 seaman/redis-plugin v1.0.0 - bundled
  📦 seaman/mysql-plugin v1.0.0 - bundled
  📦 vendor/custom-plugin v2.0.0 - composer
  📁 my/local-plugin v1.0.0 - local
```
