# Commands Reference

## Environment Management

### init

Initialize Seaman in your project with interactive setup.

```bash
seaman init [options]
```

**Options:**
- `--with-devcontainer` - Generate DevContainer configuration during init

**Example:**
```bash
seaman init --with-devcontainer
```

### start

Start all services or a specific service.

```bash
seaman start [service]
```

**Examples:**
```bash
seaman start              # Start all services
seaman start postgresql   # Start only PostgreSQL
```

### stop

Stop all services or a specific service.

```bash
seaman stop [service]
```

**Examples:**
```bash
seaman stop              # Stop all services
seaman stop redis        # Stop only Redis
```

### restart

Restart all services or a specific service.

```bash
seaman restart [service]
```

**Examples:**
```bash
seaman restart           # Restart all services
seaman restart app       # Restart only the app container
```

### status

Show status of all services with health, ports, and uptime.

```bash
seaman status
```

Displays:
- Service name
- Container status (running/stopped)
- Health status
- Exposed ports
- Uptime

### rebuild

Rebuild Docker images from Dockerfile. Useful after changing the Dockerfile or PHP version.

```bash
seaman rebuild
```

### destroy

Remove all containers, networks, and volumes. **This is destructive and requires confirmation.**

```bash
seaman destroy
```

Removes:
- All containers
- Docker networks
- All volumes (including database data)

## Service Management

### service:list

List all configured services and their status.

```bash
seaman service:list
```

### service:add

Interactively add new services to your configuration.

```bash
seaman service:add
```

Prompts you to:
1. Select service type (database, cache, tool, etc.)
2. Choose specific service (PostgreSQL, Redis, Mailpit, etc.)
3. Configure service options (version, port, environment)
4. Regenerate docker-compose.yml

### service:remove

Interactively remove services from your configuration.

```bash
seaman service:remove
```

Prompts you to:
1. Select service to remove
2. Confirm removal
3. Regenerate docker-compose.yml

### configure

Interactively configure an enabled service.

```bash
seaman configure <service>
```

**Arguments:**
- `service` - Name of the enabled service to configure

**Examples:**
```bash
seaman configure postgresql   # Configure PostgreSQL settings
seaman configure redis        # Configure Redis settings
seaman configure rabbitmq     # Configure RabbitMQ settings
```

Opens an interactive form based on the service's configuration schema. For each field you can:
- Enter a new value
- Press Enter to keep the current value (shown as default)
- For password fields, input is hidden

After saving configuration, you're offered restart options:
- **Do nothing** - Save config without restarting
- **Restart this service** - Restart only the configured service
- **Restart entire stack** - Restart all services

Configuration is saved to `seaman.yaml` and the `.env` file is regenerated automatically.

## Development Tools

### shell

Open an interactive shell in a service container.

```bash
seaman shell [service]
```

**Default service**: `app` (PHP container)

**Examples:**
```bash
seaman shell              # Open shell in app container
seaman shell postgresql   # Open psql in PostgreSQL container
seaman shell redis        # Open redis-cli in Redis container
```

### logs

Display logs with options for follow, tail, and time filtering.

```bash
seaman logs [service] [options]
```

**Options:**
- `--follow, -f` - Follow log output in real-time
- `--tail=N` - Show last N lines
- `--since=TIME` - Show logs since timestamp or relative time

**Examples:**
```bash
seaman logs                          # Show all logs
seaman logs app                      # Show app container logs
seaman logs postgresql --follow      # Follow PostgreSQL logs
seaman logs app --tail=100           # Last 100 lines
seaman logs app --since=1h           # Logs from last hour
```

### xdebug

Toggle Xdebug on or off without restarting containers.

```bash
seaman xdebug [on|off]
```

**Examples:**
```bash
seaman xdebug on    # Enable Xdebug
seaman xdebug off   # Disable Xdebug
```

Uses the `scripts/xdebug-toggle.sh` script to enable/disable Xdebug by modifying PHP configuration.

## Database Commands

### db:shell

Open an interactive database shell for the configured database service.

```bash
seaman db:shell [options]
```

**Options:**
- `--service=NAME` - Specify which database service to connect to (if multiple)

**Examples:**
```bash
seaman db:shell                    # Connect to default database
seaman db:shell --service=mysql    # Connect to specific database
```

Automatically detects database type and opens appropriate client:
- PostgreSQL: `psql`
- MySQL/MariaDB: `mysql`
- MongoDB: `mongosh`
- SQLite: `sqlite3`

### db:dump

Create a database backup dump.

```bash
seaman db:dump [options]
```

**Options:**
- `--service=NAME` - Specify which database service to dump
- `--output=FILE` - Output file path (default: auto-generated with timestamp)

**Examples:**
```bash
seaman db:dump                           # Dump default database
seaman db:dump --output=backup.sql       # Custom output file
seaman db:dump --service=postgresql      # Dump specific database
```

Creates dumps using:
- PostgreSQL: `pg_dump`
- MySQL/MariaDB: `mysqldump`
- MongoDB: `mongodump`
- SQLite: File copy

### db:restore

Restore a database from a backup dump.

```bash
seaman db:restore <file> [options]
```

**Options:**
- `--service=NAME` - Specify which database service to restore to

**Examples:**
```bash
seaman db:restore backup.sql                    # Restore to default database
seaman db:restore backup.sql --service=mysql    # Restore to specific database
```

## Execution Shortcuts

### composer

Run Composer commands in the app container.

```bash
seaman composer [args]
```

**Examples:**
```bash
seaman composer install
seaman composer require symfony/validator
seaman composer update
seaman composer dump-autoload
```

### console

Run Symfony console commands in the app container.

```bash
seaman console [args]
```

**Examples:**
```bash
seaman console cache:clear
seaman console make:controller UserController
seaman console doctrine:migrations:migrate
seaman console debug:router
```

### php

Execute PHP code or scripts in the app container.

```bash
seaman php [args]
```

**Examples:**
```bash
seaman php -v
seaman php script.php
seaman php -r "echo phpversion();"
```

## Plugin Management

### plugin:list

List all discovered plugins with their status.

```bash
seaman plugin:list
```

Displays:
- Plugin name and version
- Status (enabled/disabled)
- Source (Composer/local)
- Number of services, commands, and hooks provided

### plugin:info

Show detailed information about a specific plugin.

```bash
seaman plugin:info <plugin-name>
```

**Examples:**
```bash
seaman plugin:info vendor/my-plugin
seaman plugin:info local/custom-plugin
```

Displays:
- Plugin metadata (name, version, description)
- Provided services
- Registered commands
- Lifecycle hooks
- Template overrides
- Configuration options

### plugin:create

Create a new plugin skeleton in `.seaman/plugins/`.

```bash
seaman plugin:create <name>
```

**Example:**
```bash
seaman plugin:create my-custom-plugin
```

Creates:
- `.seaman/plugins/my-custom-plugin/src/MyCustomPlugin.php`

See [Plugins documentation](plugins.md) for more details on plugin development.

### plugin:export

Export a local plugin to a distributable Composer package.

```bash
seaman plugin:export [plugin-name] [--output=DIR] [--vendor=NAME]
```

**Arguments:**
- `plugin-name` - Name of the local plugin to export (optional)

**Options:**
- `--output=DIR` - Output directory for the exported package
- `--vendor=NAME` - Vendor name for the Composer package

**Examples:**
```bash
# Interactive mode (select plugin from list)
seaman plugin:export

# Export specific plugin with interactive vendor prompt
seaman plugin:export my-plugin

# Export with custom vendor name
seaman plugin:export my-plugin --vendor=diego

# Export to custom directory
seaman plugin:export my-plugin --output=/tmp/exports/my-plugin

# Full specification
seaman plugin:export my-plugin --vendor=diego --output=./packages/my-plugin
```

**What it does:**

1. Validates the plugin structure (requires `src/` directory with `#[AsSeamanPlugin]` attribute)
2. Copies `src/` and `templates/` directories to the output location
3. Transforms all PHP namespaces from `Seaman\LocalPlugins\PluginName` to `Vendor\PluginName`
4. Generates a complete `composer.json` file with:
   - Package metadata from plugin attributes
   - PSR-4 autoloading configuration
   - `seaman-plugin` type for automatic discovery
   - Dependency requirements
5. Displays publishing instructions

**Default values:**
- `plugin-name`: Interactive selection from available local plugins
- `--output`: `./exports/<plugin-name>/`
- `--vendor`: Interactive prompt (suggests value from git config)

See [Exporting Plugins](plugins.md#exporting-plugins) for detailed documentation on the export process and publishing workflow.

## DevContainer

### devcontainer:generate

Generate DevContainer configuration for VS Code.

```bash
seaman devcontainer:generate
```

Creates:
- `.devcontainer/devcontainer.json` - Main configuration
- `.devcontainer/README.md` - Usage instructions

See [DevContainers documentation](devcontainers.md) for details.

## Build (Development Only)

### build

Build PHAR executable. Only available when running from source (not from PHAR).

```bash
seaman build
```

Creates `build/seaman.phar` with:
- All application code
- Precompiled event listeners
- Optimized autoloader
- Compressed with Box
