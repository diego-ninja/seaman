# Plugin System

Seaman's plugin system allows you to extend functionality by adding custom Docker services, CLI commands, lifecycle hooks, and template overrides. Plugins can be distributed via Composer packages or installed locally in your project.

## Bundled Plugins

Seaman ships with 17 bundled plugins that provide all built-in services. These are located in the `plugins/` directory and are automatically loaded. Bundled plugins have the lowest priority, meaning Composer or local plugins can override them.

**Database Services:**
- MySQL, PostgreSQL, MariaDB, MongoDB, SQLite

**Cache Services:**
- Redis, Valkey, Memcached

**Queue Services:**
- RabbitMQ, Kafka

**Search Services:**
- Elasticsearch, OpenSearch

**Dev Tools:**
- Mailpit, Minio, Dozzle, Mercure, Soketi

All database plugins support `db:dump`, `db:restore`, and `db:shell` commands.

## Table of Contents

- [For Users](#for-users)
  - [Installing Plugins](#installing-plugins)
  - [Configuring Plugins](#configuring-plugins)
  - [Managing Plugins](#managing-plugins)
- [For Developers](#for-developers)
  - [Creating a Plugin](#creating-a-plugin)
  - [Plugin Structure](#plugin-structure)
  - [Extension Points](#extension-points)
  - [Configuration Schema](#configuration-schema)
  - [Complete Example](#complete-example)

---

## For Users

### Installing Plugins

Seaman supports two methods for installing plugins:

#### 1. Composer Packages (Recommended)

Install plugins as Composer dependencies:

```bash
composer require vendor/seaman-plugin-name
```

Composer plugins are automatically discovered if they include the `seaman-plugin` type in their `composer.json`:

```json
{
    "name": "vendor/seaman-plugin-name",
    "type": "seaman-plugin",
    "autoload": {
        "psr-4": {
            "Vendor\\SeamanPluginName\\": "src/"
        }
    }
}
```

#### 2. Local Plugins

For project-specific or development plugins, place them in the `.seaman/plugins/` directory:

```
your-project/
├── .seaman/
│   ├── seaman.yaml
│   └── plugins/
│       └── my-custom-plugin/
│           ├── composer.json
│           └── src/
│               └── MyPlugin.php
```

Local plugins require a `composer.json` with PSR-4 autoloading configuration.

### Configuring Plugins

Configure plugins in your `seaman.yaml` file under the `plugins` section:

```yaml
# .seaman/seaman.yaml
project:
  name: my-project
  domain: my-project.local

plugins:
  vendor/my-plugin:
    enabled: true
    timeout: 30
    debug: false

  local/my-custom-plugin:
    enabled: true
    api_key: "your-api-key"
```

Each plugin defines its own configuration options. See the plugin's documentation for available settings.

### Managing Plugins

Seaman provides commands to manage plugins:

#### List All Plugins

```bash
seaman plugin:list
```

Shows all discovered plugins with their status (enabled/disabled), version, and source (Composer/local).

#### View Plugin Details

```bash
seaman plugin:info vendor/plugin-name
```

Displays detailed information about a plugin including:
- Version and description
- Available services
- Provided commands
- Lifecycle hooks
- Template overrides
- Configuration options

#### Create a New Plugin

```bash
seaman plugin:create my-plugin
```

Generates a plugin skeleton in `.seaman/plugins/my-plugin/` with the basic structure and example code.

---

## For Developers

### Creating a Plugin

A Seaman plugin is a PHP class that implements `PluginInterface` or uses the `#[AsSeamanPlugin]` attribute.

#### Minimal Plugin

```php
<?php

declare(strict_types=1);

namespace MyVendor\MyPlugin;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;

#[AsSeamanPlugin(
    name: 'my-vendor/my-plugin',
    version: '1.0.0',
    description: 'My custom Seaman plugin',
)]
final class MyPlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'my-vendor/my-plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'My custom Seaman plugin';
    }
}
```

### Plugin Structure

A typical plugin directory structure:

```
my-plugin/
├── composer.json
├── src/
│   ├── MyPlugin.php           # Main plugin class
│   └── Command/
│       └── MyCommand.php      # Custom commands
├── templates/
│   └── services/
│       └── my-service.yaml.twig
└── README.md
```

#### composer.json

```json
{
    "name": "my-vendor/seaman-my-plugin",
    "type": "seaman-plugin",
    "description": "My custom Seaman plugin",
    "require": {
        "php": "^8.4"
    },
    "autoload": {
        "psr-4": {
            "MyVendor\\MyPlugin\\": "src/"
        }
    },
    "extra": {
        "seaman": {
            "plugin-class": "MyVendor\\MyPlugin\\MyPlugin"
        }
    }
}
```

### Extension Points

Plugins can extend Seaman through four extension points:

#### 1. Custom Docker Services

Add new Docker services using the `#[ProvidesService]` attribute:

```php
<?php

declare(strict_types=1);

namespace MyVendor\MyPlugin;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'my-vendor/my-plugin',
    version: '1.0.0',
    description: 'Provides ClickHouse database service',
)]
final class MyPlugin implements PluginInterface
{
    #[ProvidesService(name: 'clickhouse', category: ServiceCategory::Database)]
    public function clickhouseService(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: 'clickhouse',
            template: __DIR__ . '/../templates/clickhouse.yaml.twig',
            displayName: 'ClickHouse',
            description: 'Fast open-source column-oriented database',
            icon: '🏠',
            category: ServiceCategory::Database,
            ports: [8123, 9000],
            internalPorts: [9009],
            defaultConfig: [
                'version' => '24.3',
                'memory_limit' => '2G',
            ],
            healthCheck: new HealthCheck(
                test: ['CMD', 'clickhouse-client', '--query=SELECT 1'],
                interval: '10s',
                timeout: '5s',
                retries: 3,
            ),
        );
    }

    // ... PluginInterface methods
}
```

**ServiceDefinition Properties:**

| Property | Type | Description |
|----------|------|-------------|
| `name` | `string` | Unique service identifier |
| `template` | `string` | Path to Twig template for docker-compose |
| `displayName` | `?string` | Human-readable name |
| `description` | `string` | Service description |
| `icon` | `string` | Emoji icon for display |
| `category` | `ServiceCategory` | Service category for grouping |
| `ports` | `list<int>` | External ports to expose |
| `internalPorts` | `list<int>` | Internal-only ports |
| `defaultConfig` | `array<string, mixed>` | Default configuration values |
| `dependencies` | `list<string>` | Service dependencies |
| `healthCheck` | `?HealthCheck` | Health check configuration |
| `databaseOperations` | `?DatabaseOperations` | Database dump/restore/shell commands |

#### Database Operations

For database services, you can provide `databaseOperations` to enable `db:dump`, `db:restore`, and `db:shell` commands:

```php
use Seaman\Plugin\DatabaseOperations;

return new ServiceDefinition(
    name: 'mydb',
    template: __DIR__ . '/../templates/mydb.yaml.twig',
    // ... other properties
    databaseOperations: new DatabaseOperations(
        dumpCommand: static fn($config) => [
            'mydump',
            '-u', $config->environmentVariables['DB_USER'] ?? 'root',
            '-p' . ($config->environmentVariables['DB_PASSWORD'] ?? ''),
            $config->environmentVariables['DB_NAME'] ?? 'mydb',
        ],
        restoreCommand: static fn($config) => [
            'myrestore',
            '-u', $config->environmentVariables['DB_USER'] ?? 'root',
            '-p' . ($config->environmentVariables['DB_PASSWORD'] ?? ''),
            $config->environmentVariables['DB_NAME'] ?? 'mydb',
        ],
        shellCommand: static fn($config) => [
            'myshell',
            '-u', $config->environmentVariables['DB_USER'] ?? 'root',
        ],
    ),
);
```

Each closure receives the `ServiceConfig` and should return a `list<string>` of command arguments.

**Service Categories:**

- `ServiceCategory::Database` - Database services
- `ServiceCategory::Cache` - Caching services
- `ServiceCategory::Queue` - Message queue services
- `ServiceCategory::Search` - Search engines
- `ServiceCategory::DevTools` - Development tools
- `ServiceCategory::Proxy` - Proxy/gateway services
- `ServiceCategory::Misc` - Other services

#### 2. Custom CLI Commands

Add new CLI commands using the `#[ProvidesCommand]` attribute:

```php
<?php

declare(strict_types=1);

namespace MyVendor\MyPlugin;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesCommand;
use Seaman\Plugin\PluginInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsSeamanPlugin(
    name: 'my-vendor/my-plugin',
    version: '1.0.0',
    description: 'Provides custom backup command',
)]
final class MyPlugin implements PluginInterface
{
    #[ProvidesCommand]
    public function backupCommand(): Command
    {
        return new class extends Command {
            protected static $defaultName = 'backup:database';
            protected static $defaultDescription = 'Backup all databases';

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                $output->writeln('Backing up databases...');
                // Backup logic here
                return Command::SUCCESS;
            }
        };
    }

    // ... PluginInterface methods
}
```

#### 3. Lifecycle Hooks

React to Seaman lifecycle events using the `#[OnLifecycle]` attribute:

```php
<?php

declare(strict_types=1);

namespace MyVendor\MyPlugin;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\OnLifecycle;
use Seaman\Plugin\LifecycleEvent;
use Seaman\Plugin\LifecycleEventData;
use Seaman\Plugin\PluginInterface;

#[AsSeamanPlugin(
    name: 'my-vendor/my-plugin',
    version: '1.0.0',
    description: 'Lifecycle hooks example',
)]
final class MyPlugin implements PluginInterface
{
    #[OnLifecycle(event: LifecycleEvent::AfterStart->value, priority: 10)]
    public function onAfterStart(LifecycleEventData $data): void
    {
        // Runs after containers start
        echo "Project started: {$data->projectRoot}\n";
    }

    #[OnLifecycle(event: LifecycleEvent::BeforeDestroy->value, priority: 100)]
    public function onBeforeDestroy(LifecycleEventData $data): void
    {
        // Runs before containers are destroyed
        // Higher priority = runs first
        echo "Cleaning up before destroy...\n";
    }

    // ... PluginInterface methods
}
```

**Available Lifecycle Events:**

| Event | When |
|-------|------|
| `before:init` | Before project initialization |
| `after:init` | After project initialization |
| `before:start` | Before containers start |
| `after:start` | After containers start |
| `before:stop` | Before containers stop |
| `after:stop` | After containers stop |
| `before:rebuild` | Before containers rebuild |
| `after:rebuild` | After containers rebuild |
| `before:destroy` | Before containers are destroyed |
| `after:destroy` | After containers are destroyed |

**LifecycleEventData Properties:**

| Property | Type | Description |
|----------|------|-------------|
| `event` | `string` | Event name (e.g., `after:start`) |
| `projectRoot` | `string` | Absolute path to project root |
| `service` | `?string` | Service name (if applicable) |

**Priority:**

- Higher priority values run first
- Default priority is `0`
- Use priority to control execution order between multiple handlers

#### 4. Template Overrides

Override Seaman's default templates using the `#[OverridesTemplate]` attribute:

```php
<?php

declare(strict_types=1);

namespace MyVendor\MyPlugin;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\OverridesTemplate;
use Seaman\Plugin\PluginInterface;

#[AsSeamanPlugin(
    name: 'my-vendor/my-plugin',
    version: '1.0.0',
    description: 'Custom MySQL template',
)]
final class MyPlugin implements PluginInterface
{
    #[OverridesTemplate(template: 'services/mysql.yaml.twig')]
    public function customMysqlTemplate(): string
    {
        return __DIR__ . '/../templates/custom-mysql.yaml.twig';
    }

    // ... PluginInterface methods
}
```

This allows you to customize how services are configured in the generated `docker-compose.yaml`.

### Configuration Schema

Define a configuration schema for your plugin to enable validation and defaults:

```php
<?php

declare(strict_types=1);

namespace MyVendor\MyPlugin;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\Config\PluginConfig;
use Seaman\Plugin\PluginInterface;

#[AsSeamanPlugin(
    name: 'my-vendor/my-plugin',
    version: '1.0.0',
    description: 'Plugin with configuration',
)]
final class MyPlugin implements PluginInterface
{
    private PluginConfig $config;

    public function __construct()
    {
        $this->config = new PluginConfig(
            ConfigSchema::create()
                ->integer('timeout', default: 30, min: 1, max: 300)
                ->string('api_key', default: null, nullable: true)
                ->boolean('debug', default: false)
        );
    }

    /**
     * Called by Seaman to inject user configuration.
     *
     * @param array<string, mixed> $values
     */
    public function configure(array $values): void
    {
        $this->config->load($values);
    }

    public function getTimeout(): int
    {
        return $this->config->get('timeout');
    }

    public function getApiKey(): ?string
    {
        return $this->config->get('api_key');
    }

    public function isDebugEnabled(): bool
    {
        return $this->config->get('debug');
    }

    // ... PluginInterface methods
}
```

**ConfigSchema Methods:**

| Method | Description |
|--------|-------------|
| `integer(name, default, min?, max?)` | Integer with optional range |
| `string(name, default?, nullable?)` | String with optional null |
| `boolean(name, default?)` | Boolean value |

**UI Metadata Methods:**

After defining a field, chain these methods to add UI metadata for `seaman configure`:

| Method | Description |
|--------|-------------|
| `label(string)` | Human-readable field label |
| `description(string)` | Help text for the field |
| `secret()` | Mark as password field (hidden input) |
| `enum(array)` | Restrict to allowed values (shown as select) |

**Example with UI Metadata:**

```php
$this->schema = ConfigSchema::create()
    ->string('version', default: '8.0')
        ->label('MySQL version')
        ->description('Docker image tag to use')
        ->enum(['5.7', '8.0', '8.4', 'latest'])
    ->integer('port', default: 3306, min: 1, max: 65535)
        ->label('Port')
        ->description('Host port to expose MySQL on')
    ->string('password', default: 'secret')
        ->label('Database password')
        ->description('Password for the database user')
        ->secret();
```

This enables interactive configuration via `seaman configure <service>` where users see labeled fields, descriptions as hints, password masking, and dropdown selection for enum fields.

User configuration in `seaman.yaml`:

```yaml
plugins:
  my-vendor/my-plugin:
    timeout: 60
    api_key: "secret-key"
    debug: true
```

### Complete Example

Here's a complete plugin that provides a ClickHouse service, a backup command, and lifecycle hooks:

```php
<?php

declare(strict_types=1);

namespace MyVendor\ClickHousePlugin;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\OnLifecycle;
use Seaman\Plugin\Attribute\ProvidesCommand;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\Config\PluginConfig;
use Seaman\Plugin\LifecycleEvent;
use Seaman\Plugin\LifecycleEventData;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsSeamanPlugin(
    name: 'my-vendor/clickhouse-plugin',
    version: '1.0.0',
    description: 'ClickHouse database service for Seaman',
    requires: ['seaman/core:^1.0'],
)]
final class ClickHousePlugin implements PluginInterface
{
    private PluginConfig $config;

    public function __construct()
    {
        $this->config = new PluginConfig(
            ConfigSchema::create()
                ->string('version', default: '24.3')
                ->string('memory_limit', default: '2G')
                ->boolean('enable_backups', default: true)
        );
    }

    public function getName(): string
    {
        return 'my-vendor/clickhouse-plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'ClickHouse database service for Seaman';
    }

    /**
     * @param array<string, mixed> $values
     */
    public function configure(array $values): void
    {
        $this->config->load($values);
    }

    #[ProvidesService(name: 'clickhouse', category: ServiceCategory::Database)]
    public function clickhouseService(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: 'clickhouse',
            template: __DIR__ . '/../templates/clickhouse.yaml.twig',
            displayName: 'ClickHouse',
            description: 'Fast column-oriented OLAP database',
            icon: '🏠',
            category: ServiceCategory::Database,
            ports: [8123, 9000],
            defaultConfig: [
                'version' => $this->config->get('version'),
                'memory_limit' => $this->config->get('memory_limit'),
            ],
            healthCheck: new HealthCheck(
                test: ['CMD', 'clickhouse-client', '--query=SELECT 1'],
                interval: '10s',
                timeout: '5s',
                retries: 3,
            ),
        );
    }

    #[ProvidesCommand]
    public function backupCommand(): Command
    {
        return new class extends Command {
            protected static $defaultName = 'clickhouse:backup';
            protected static $defaultDescription = 'Backup ClickHouse databases';

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                $output->writeln('<info>Creating ClickHouse backup...</info>');
                // Backup implementation
                $output->writeln('<info>Backup completed successfully!</info>');
                return Command::SUCCESS;
            }
        };
    }

    #[OnLifecycle(event: LifecycleEvent::AfterStart->value)]
    public function ensureDatabase(LifecycleEventData $data): void
    {
        // Create default database after containers start
        if ($data->service === 'clickhouse' || $data->service === null) {
            // Initialize database if needed
        }
    }

    #[OnLifecycle(event: LifecycleEvent::BeforeDestroy->value, priority: 50)]
    public function backupBeforeDestroy(LifecycleEventData $data): void
    {
        if ($this->config->get('enable_backups')) {
            // Auto-backup before destruction
        }
    }
}
```

**Template Example** (`templates/clickhouse.yaml.twig`):

```twig
clickhouse:
  image: clickhouse/clickhouse-server:{{ config.version }}
  container_name: {{ project.name }}-clickhouse
  ports:
    - "{{ service.port }}:8123"
    - "9000:9000"
  environment:
    CLICKHOUSE_DB: {{ project.name }}
    CLICKHOUSE_USER: default
    CLICKHOUSE_PASSWORD: ""
  volumes:
    - clickhouse_data:/var/lib/clickhouse
  deploy:
    resources:
      limits:
        memory: {{ config.memory_limit }}
{% if service.healthCheck %}
  healthcheck:
    test: ["CMD", "{{ service.healthCheck.command }}"]
    interval: {{ service.healthCheck.interval }}
    timeout: {{ service.healthCheck.timeout }}
    retries: {{ service.healthCheck.retries }}
{% endif %}
  labels:
    - "traefik.enable=false"
  networks:
    - {{ project.name }}-network
```

---

## Best Practices

1. **Use semantic versioning** for your plugin versions
2. **Validate configuration** using ConfigSchema to catch errors early
3. **Provide meaningful descriptions** for services and commands
4. **Use appropriate service categories** for better organization
5. **Set reasonable priorities** for lifecycle hooks to avoid conflicts
6. **Document your plugin** with a README.md explaining configuration options
7. **Test your plugin** with different project configurations
8. **Handle errors gracefully** in lifecycle hooks to avoid breaking Seaman operations

## Troubleshooting

### Plugin Not Discovered

- Ensure `composer.json` has `"type": "seaman-plugin"`
- Check that the plugin class is autoloadable
- Verify the `#[AsSeamanPlugin]` attribute is present
- Run `composer dump-autoload` after changes

### Configuration Not Applied

- Check that the plugin implements a `configure(array $values)` method
- Verify the configuration keys match your schema
- Check `seaman.yaml` for syntax errors

### Service Not Available

- Ensure the `#[ProvidesService]` attribute is on a public method
- Verify the method returns a `ServiceDefinition` instance
- Check that the template file exists at the specified path

### Command Not Registered

- Ensure the `#[ProvidesCommand]` attribute is on a public method
- Verify the method returns a `Command` instance
- Check that the command has a valid name (`$defaultName`)
