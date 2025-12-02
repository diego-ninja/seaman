# Seaman

Docker development environment manager for Symfony 7+, inspired by Laravel Sail. Seaman provides a sophisticated yet simple way to manage your Symfony development environment with Docker, offering intelligent project detection, service orchestration, and developer-friendly tooling.

## Features

### Core Functionality
- 🚀 **Interactive Initialization**: Detects existing Symfony projects or bootstraps new ones with guided setup
- 🐳 **Docker Compose Orchestration**: Manages complete Docker environments with a single configuration file
- 🎯 **Smart Project Detection**: Identifies Symfony projects using multiple indicators (composer.json, bin/console, structure)
- 📝 **YAML Configuration**: Simple, readable configuration in `.seaman/seaman.yaml`
- 🔄 **Auto-Generated Files**: Creates Docker Compose, Dockerfiles, and .env files automatically

### Development Experience
- 🐛 **Xdebug Toggle**: Enable/disable Xdebug without restarting containers
- 🖥️ **Interactive Shell**: Access container shells with a single command
- 📊 **Service Status**: View service health, ports, and uptime in formatted tables
- 📜 **Streaming Logs**: Follow service logs with tail, since, and filtering options
- 🎨 **Beautiful CLI**: Styled output with boxes, spinners, and colors

### Service Management
- 🗄️ **Database Support**: PostgreSQL (default), MySQL, MariaDB, MongoDB
- 🚀 **Cache Services**: Redis (default), Memcached
- 📨 **Development Tools**: Mailpit (email testing), Dozzle (log viewer), Minio (S3-compatible storage)
- 🔍 **Search & Analytics**: Elasticsearch
- 📮 **Message Queues**: RabbitMQ
- ➕ **Dynamic Services**: Add or remove services without manual Docker configuration

### Technical Excellence
- 🎯 **Type-Safe**: PHP 8.4 with strict types, PHPStan level 10
- ✅ **Well-Tested**: 95%+ test coverage with Pest
- 📦 **PHAR Distribution**: Single executable file for easy distribution
- 🎪 **Event System**: Extensible architecture with attribute-based listeners
- 🔧 **Multiple PHP Versions**: Support for PHP 8.3, 8.4, and 8.5

## Requirements

- Docker Desktop or Docker Engine
- Docker Compose V2
- PHP 8.3+ (for development)
- Symfony CLI (optional, for project bootstrapping)

## Installation

```bash
curl -sS https://raw.githubusercontent.com/seaman/seaman/main/installer | bash
```

This installs the `seaman` command globally, making it available from anywhere in your system.

## Quick Start

### Initialize an Existing Symfony Project

```bash
cd your-symfony-project
seaman init
```

Seaman will detect your Symfony project and guide you through:
1. Selecting your project type (Web App, API Platform, Microservice)
2. Choosing your PHP version (auto-detected from composer.json)
3. Selecting a database (PostgreSQL, MySQL, MariaDB, MongoDB, or none)
4. Adding additional services (Redis, Mailpit, Elasticsearch, etc.)
5. Configuring Xdebug settings

### Create a New Symfony Project

```bash
mkdir my-project && cd my-project
seaman init
```

If no Symfony project is detected, Seaman will offer to create one for you using the Symfony CLI.

### Start Your Environment

```bash
seaman start
```

Your Symfony application will be available at `http://localhost`. Services will be accessible on their configured ports.

### Stop Your Environment

```bash
seaman stop
```

## Configuration

Seaman uses a single YAML configuration file located at `.seaman/seaman.yaml`:

```yaml
version: "1.0"
php:
  version: "8.4"
  xdebug:
    enabled: false
    ide_key: "PHPSTORM"
    client_host: "host.docker.internal"
services:
  postgresql:
    enabled: true
    type: "postgresql"
    version: "16"
    port: 5432
    environment:
      POSTGRES_DB: "myapp"
      POSTGRES_USER: "myapp"
      POSTGRES_PASSWORD: "secret"
  redis:
    enabled: true
    type: "redis"
    version: "7-alpine"
    port: 6379
volumes:
  persist:
    - "postgresql"
    - "redis"
```

### Generated Files

When you run `seaman init`, the following files are created:

- **`.seaman/seaman.yaml`**: Main configuration file
- **`docker-compose.yml`**: Docker Compose configuration (generated from seaman.yaml)
- **`.seaman/Dockerfile`**: PHP container Dockerfile with your selected PHP version
- **`.env`**: Environment variables for your Symfony application
- **`scripts/xdebug-toggle.sh`**: Script to toggle Xdebug without restarting

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

## Available Commands

### Environment Management

| Command | Description |
|---------|-------------|
| `seaman init` | Initialize Seaman in your project with interactive setup |
| `seaman start [service]` | Start all services or a specific service |
| `seaman stop [service]` | Stop all services or a specific service |
| `seaman restart [service]` | Restart all services or a specific service |
| `seaman status` | Show status of all services with health, ports, and uptime |
| `seaman rebuild` | Rebuild Docker images from Dockerfile |
| `seaman destroy` | Remove all containers, networks, and volumes (requires confirmation) |

### Service Management

| Command | Description |
|---------|-------------|
| `service:add` | Interactively add new services to your configuration |
| `service:remove` | Interactively remove services from your configuration |
| `service:list` | List all configured services |
| `devcontainer:generate` | Generate DevContainer configuration for VS Code |

### Development Tools

| Command | Description |
|---------|-------------|
| `seaman shell [service]` | Open an interactive shell in a service (default: app) |
| `seaman logs [service]` | Display logs with options for follow, tail, and time filtering |
| `seaman xdebug [on\|off]` | Toggle Xdebug without restarting containers |

### Execution Shortcuts

| Command | Description |
|---------|-------------|
| `seaman composer [args]` | Run Composer commands in the app container |
| `seaman console [args]` | Run Symfony console commands in the app container |
| `seaman php [args]` | Execute PHP code or scripts in the app container |

### Examples

```bash
# View service status
seaman status

# Access app container shell
seaman shell

# Follow logs for a specific service
seaman logs postgresql --follow

# Run Composer install
seaman composer install

# Execute Symfony console commands
seaman console cache:clear
seaman console make:controller

# Toggle Xdebug on
seaman xdebug on

# Add a new service
service:add

# Rebuild after changing Dockerfile
seaman rebuild
```

## Supported Services

### Databases

| Service | Default Version | Port | Notes |
|---------|----------------|------|-------|
| PostgreSQL | 16 | 5432 | Default database choice |
| MySQL | 8.0 | 3306 | |
| MariaDB | 11.0 | 3306 | |
| MongoDB | 7.0 | 27017 | NoSQL option |

### Cache & Session

| Service | Default Version | Port | Notes |
|---------|----------------|------|-------|
| Redis | 7-alpine | 6379 | Default for most project types |
| Memcached | latest | 11211 | |

### Development Tools

| Service | Default Version | Port | Notes |
|---------|----------------|------|-------|
| Mailpit | latest | 8025 (web), 1025 (SMTP) | Email testing, captures all SMTP |
| Dozzle | latest | 8080 | Real-time log viewer for all containers |
| Minio | latest | 9000 (API), 9001 (console) | S3-compatible object storage |

### Search & Analytics

| Service | Default Version | Port | Notes |
|---------|----------------|------|-------|
| Elasticsearch | 8.11 | 9200 | Full-text search |

### Message Queues

| Service | Default Version | Port | Notes |
|---------|----------------|------|-------|
| RabbitMQ | 3.13-management | 5672 (AMQP), 15672 (management) | Message broker with management UI |

### Service Auto-Selection

Based on your project type, Seaman suggests default services:

- **Web Application**: Redis, Mailpit
- **API Platform**: Redis
- **Microservice**: Redis
- **Skeleton**: No defaults

You can customize this selection during initialization or add/remove services later.

## PHP Versions and Xdebug

### Supported PHP Versions

Seaman supports multiple PHP versions with automatic detection from your `composer.json`:

- PHP 8.3
- PHP 8.4 (default)
- PHP 8.5

The PHP version is detected from your `composer.json` `require` section during initialization.

### Xdebug Configuration

Xdebug can be toggled on/off without restarting containers:

```bash
# Enable Xdebug
seaman xdebug on

# Disable Xdebug
seaman xdebug off
```

Configuration options in `seaman.yaml`:

```yaml
php:
  xdebug:
    enabled: false
    ide_key: "PHPSTORM"              # Your IDE key
    client_host: "host.docker.internal"  # Docker Desktop host
```

Default settings work with PhpStorm and other JetBrains IDEs. For other IDEs, adjust the `ide_key` accordingly.

## Project Types

Seaman supports different project types with tailored configurations:

- **Web Application**: Full-stack Symfony with Twig, Doctrine, Security, Forms
- **API Platform**: API-first with API Platform bundle
- **Microservice**: Minimal framework setup
- **Skeleton**: Bare minimum Symfony

Each project type comes with sensible defaults for services and configuration.

## Development

### Building from Source

```bash
git clone https://github.com/diego-ninja/seaman.git
cd seaman
composer install
```

### Running Tests

```bash
vendor/bin/pest
```

### Code Quality

```bash
# PHPStan (Level 10)
vendor/bin/phpstan analyse

# Code Style (PER)
vendor/bin/php-cs-fixer fix

# Build PHAR
seaman build
```

### Contributing

Seaman follows strict quality standards:

- PHP 8.4 with strict types
- PHPStan level 10 (strictest)
- 95%+ test coverage
- PER code style
- Test-driven development

## Architecture

Seaman is built with:

- **Symfony Console**: CLI framework
- **Twig**: Template rendering for Docker configs
- **Laravel Prompts**: Interactive CLI with beautiful UI
- **Event System**: Extensible architecture with attribute-based listeners
- **Docker Manager**: Process orchestration with Symfony Process
- **Type Safety**: Full type declarations with PHPStan level 10

## License

MIT License

## Credits

Created by [Diego Rin](https://github.com/diego-ninja) - Inspired by Laravel Sail's developer experience
