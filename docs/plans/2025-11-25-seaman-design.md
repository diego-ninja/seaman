# Seaman - Docker Development Environment Manager for Symfony 7

**Date**: 2025-11-25
**Status**: Design Approved
**Author**: Diego & Claude

## Overview

Seaman is a Docker-based development environment manager for Symfony 7, inspired by Laravel Sail. It provides an interactive CLI tool to manage development services (databases, caches, queues, etc.) with a focus on developer experience and team consistency.

## Target Audience

- Individual developers working on Symfony projects locally
- Small to medium teams requiring consistent development environments
- Must be simple for personal use yet robust for team collaboration

## Core Philosophy

- **Interactive by default**: All commands should provide rich interactive experiences
- **Configurable**: Services can be added/removed dynamically after initialization
- **Type-safe**: PHPStan level 10, PHP 8.4 strict types throughout
- **Well-tested**: ≥95% test coverage, comprehensive edge case handling
- **YAGNI**: Only build what's needed, extensible architecture for future growth

---

## Architecture

### Distribution Model

**Hybrid approach: Bash wrapper + PHP PHAR**

```
User's Project/
├── seaman                    # Bash script (downloaded via curl installer)
└── [project files]

~/.seaman/
└── seaman.phar              # PHP application (auto-downloaded/updated)
```

**Flow:**
1. User runs `curl -sS https://seaman.dev/installer | bash` to install
2. This creates `./seaman` bash script in project root
3. Script downloads/updates `~/.seaman/seaman.phar`
4. All commands execute: `php ~/.seaman/seaman.phar "$@"`

**Benefits:**
- Simple distribution (one curl command)
- Automatic updates of PHAR
- Full power of PHP/Symfony Console for CLI
- Easy to test and maintain

### Technology Stack

**Application:**
- PHP 8.4 with strict types
- Symfony Console Component (CLI framework)
- Twig (template engine for Docker configs)
- Symfony YAML Component (config parsing)
- Box (PHAR compiler)

**Quality Tools:**
- Pest (testing framework)
- PHPStan (static analysis, level 10)
- php-cs-fixer (PER code style)
- Infection (mutation testing, optional)

**Target PHP Version:** 8.4 (use modern features: property hooks, asymmetric visibility, array_find, etc.)

---

## Project Structure

```
seaman/
├── bin/
│   └── seaman.php              # PHAR entry point
├── src/
│   ├── Application.php         # Symfony Console Application
│   ├── Command/
│   │   ├── InitCommand.php
│   │   ├── StartCommand.php
│   │   ├── StopCommand.php
│   │   ├── RestartCommand.php
│   │   ├── RebuildCommand.php
│   │   ├── DestroyCommand.php
│   │   ├── StatusCommand.php
│   │   ├── ShellCommand.php
│   │   ├── LogsCommand.php
│   │   ├── XdebugCommand.php
│   │   ├── ComposerCommand.php
│   │   ├── ConsoleCommand.php
│   │   ├── PhpCommand.php
│   │   ├── ServiceAddCommand.php
│   │   ├── ServiceRemoveCommand.php
│   │   ├── ServiceListCommand.php
│   │   ├── DbDumpCommand.php
│   │   ├── DbRestoreCommand.php
│   │   └── DbShellCommand.php
│   ├── Service/
│   │   ├── ConfigManager.php
│   │   ├── DockerManager.php
│   │   ├── DockerComposeGenerator.php
│   │   ├── DockerfileGenerator.php
│   │   ├── TemplateRenderer.php
│   │   └── Container/
│   │       ├── ServiceInterface.php
│   │       ├── ServiceRegistry.php
│   │       ├── PostgresqlService.php
│   │       ├── MysqlService.php
│   │       ├── MariadbService.php
│   │       ├── RedisService.php
│   │       ├── MailpitService.php
│   │       ├── MinioService.php
│   │       ├── ElasticsearchService.php
│   │       └── RabbitmqService.php
│   ├── ValueObject/
│   │   ├── Configuration.php
│   │   ├── ServerConfig.php
│   │   ├── PhpConfig.php
│   │   ├── ServiceConfig.php
│   │   ├── ServiceCollection.php
│   │   ├── VolumeConfig.php
│   │   ├── ProcessResult.php
│   │   ├── LogOptions.php
│   │   └── HealthCheck.php
│   └── Template/
│       ├── docker/
│       │   ├── Dockerfile.symfony.twig
│       │   ├── Dockerfile.nginx-fpm.twig
│       │   ├── Dockerfile.frankenphp.twig
│       │   ├── compose.base.twig
│       │   └── services/
│       │       ├── postgresql.twig
│       │       ├── mysql.twig
│       │       ├── mariadb.twig
│       │       ├── redis.twig
│       │       ├── mailpit.twig
│       │       ├── minio.twig
│       │       ├── elasticsearch.twig
│       │       └── rabbitmq.twig
│       ├── config/
│       │   ├── nginx.conf.twig
│       │   ├── php.ini.twig
│       │   └── xdebug.ini.twig
│       └── scripts/
│           └── xdebug-toggle.sh.twig
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Fixtures/
├── seaman                       # Bash wrapper script
├── box.json                     # Box configuration
├── composer.json
├── phpstan.neon
├── .php-cs-fixer.dist.php
└── pest.php
```

---

## Configuration System

### Hybrid Approach: `.env` + `seaman.yaml`

**`.env` (environment variables):**
- Generated and updated by seaman
- Contains runtime values (ports, passwords, toggles)
- Gitignored by default

```bash
APP_PORT=8000
DB_PORT=5432
DB_ROOT_PASSWORD=secret
REDIS_PORT=6379
MAILPIT_PORT=8025
XDEBUG_MODE=off
PHP_VERSION=8.4
```

**`seaman.yaml` (structural configuration):**
- Versioned in git
- Describes services, dependencies, structure
- References `.env` variables

```yaml
version: '1.0'

server:
  type: symfony # symfony | nginx-fpm | frankenphp
  port: ${APP_PORT}

services:
  database:
    enabled: true
    type: postgresql # postgresql | mysql | mariadb
    version: '16'
    port: ${DB_PORT}

  redis:
    enabled: true
    version: '7-alpine'
    port: ${REDIS_PORT}

  mailpit:
    enabled: true
    port: ${MAILPIT_PORT}

  minio:
    enabled: false
    port: 9000
    console_port: 9001

  elasticsearch:
    enabled: false
    version: '8.11'
    port: 9200

  rabbitmq:
    enabled: false
    port: 5672
    management_port: 15672

php:
  version: '8.4'
  extensions:
    - pdo_pgsql
    - redis
    - intl
    - opcache
  xdebug:
    enabled: false
    ide_key: PHPSTORM
    client_host: host.docker.internal

volumes:
  persist:
    - database
    - redis
```

### Configuration Classes

```php
<?php

declare(strict_types=1);

// ABOUTME: Immutable configuration root object.
// ABOUTME: Represents the complete seaman.yaml configuration.

namespace Seaman\ValueObject;

readonly class Configuration
{
    public function __construct(
        public ServerConfig $server,
        public PhpConfig $php,
        public ServiceCollection $services,
        public VolumeConfig $volumes,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

// ABOUTME: Manages configuration loading and saving.
// ABOUTME: Handles YAML parsing and .env file generation.

namespace Seaman\Service;

class ConfigManager
{
    public function __construct(
        private readonly string $projectRoot,
    ) {}

    public function load(): Configuration
    {
        // Parse seaman.yaml
        // Validate structure
        // Return Configuration object
    }

    public function save(Configuration $config): void
    {
        // Write seaman.yaml
        // Regenerate .env
    }

    public function merge(Configuration $base, array $overrides): Configuration
    {
        // Deep merge configurations
        // Used when adding/removing services
    }
}
```

---

## CLI Commands

### Core Commands

```bash
seaman init              # Interactive initialization
seaman start [service]   # Start services
seaman stop [service]    # Stop services
seaman restart [service] # Restart services
seaman rebuild [service] # Rebuild Docker images
seaman destroy           # Remove all containers/volumes/networks
seaman status            # Show status of all services
```

### Service Management

```bash
seaman service:add       # Interactively add services
seaman service:remove    # Remove services
seaman service:list      # List available and enabled services
```

### Utilities

```bash
seaman shell [service]   # Interactive shell (default: app)
seaman logs [service]    # View logs with filtering options
seaman xdebug on|off     # Toggle Xdebug without restart
seaman composer [...]    # Execute composer in container
seaman console [...]     # Execute bin/console in container
seaman php [...]         # Execute PHP in container
```

### Database Management

```bash
seaman db:dump [file]    # Export database
seaman db:restore [file] # Restore from dump
seaman db:shell          # Open database client shell
```

### Interactive Features

All commands use:
- **SymfonyStyle** for consistent, beautiful output
- **QuestionHelper** for interactive prompts
  - Multiple choice questions
  - Checkboxes for multi-select
  - Confirmation dialogs
  - Input validation with clear error messages
- **Progress bars** for long operations (image pulls, builds)
- **Tables** for status displays
- **Color coding** for status indicators

---

## Docker Orchestration

### Dynamic Generation

**Key principle:** `docker-compose.yml` is **generated**, not versioned.

**Flow:**
1. User runs `seaman init` or `seaman service:add`
2. `seaman.yaml` is updated
3. `DockerComposeGenerator` reads `seaman.yaml`
4. Generates `.seaman/docker-compose.yml` from templates
5. Generates `.seaman/Dockerfile` based on server type
6. Generates supporting configs (nginx.conf, php.ini, etc.)

### Generated Directory Structure

```
.seaman/                     # Gitignored
├── docker-compose.yml       # Generated from seaman.yaml
├── Dockerfile              # Generated based on server type
├── config/
│   ├── nginx.conf          # If using nginx-fpm
│   ├── php.ini             # PHP configuration
│   └── xdebug.ini          # Xdebug configuration
└── scripts/
    └── xdebug-toggle.sh    # Script to toggle Xdebug without restart
```

### Template System

Uses **Twig** for all templates, embedded in PHAR.

**Example: `compose.base.twig`**
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
    depends_on:
{% for service in services.enabled %}
      - {{ service.name }}
{% endfor %}
    networks:
      - seaman

{% for service in services.enabled %}
{{ include('services/' ~ service.type ~ '.twig', { service: service }) }}
{% endfor %}

networks:
  seaman:
    driver: bridge

volumes:
{% for volume in volumes.persist %}
  {{ volume }}:
{% endfor %}
```

### Server Types

**1. Symfony Server (default)**
- Uses `symfony/cli` binary
- Fastest development iteration
- Hot reload support
- Minimal overhead

**2. Nginx + PHP-FPM**
- Production-like setup
- Better for testing production configs
- Separate nginx.conf generation

**3. FrankenPHP + Caddy**
- Modern HTTP/3 support
- Automatic HTTPS
- Built-in worker mode for performance testing

### Xdebug Toggle Implementation

**Without container restart:**

```bash
# .seaman/scripts/xdebug-toggle.sh
#!/bin/bash
MODE=$1

if [ "$MODE" = "on" ]; then
    echo "zend_extension=xdebug.so" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
    echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
    # ... other xdebug config
    kill -USR2 1  # Reload PHP-FPM without restart
    echo "Xdebug enabled"
elif [ "$MODE" = "off" ]; then
    echo "; xdebug disabled" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
    kill -USR2 1
    echo "Xdebug disabled"
fi
```

**Command implementation:**
```php
// seaman xdebug on
$dockerManager->execute('app', ['xdebug-toggle', 'on']);
```

---

## Service Management

### Service Architecture

Each service implements `ServiceInterface`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Interface for pluggable Docker services.
// ABOUTME: Each service defines its config, dependencies, and compose generation.

namespace Seaman\Service\Container;

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

### Service Registry

```php
<?php

declare(strict_types=1);

// ABOUTME: Registry of all available services.
// ABOUTME: Manages service registration and retrieval.

namespace Seaman\Service\Container;

class ServiceRegistry
{
    /** @var array<string, ServiceInterface> */
    private array $services = [];

    public function register(ServiceInterface $service): void
    {
        $this->services[$service->getName()] = $service;
    }

    public function get(string $name): ServiceInterface
    {
        if (!isset($this->services[$name])) {
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
     * @return list<ServiceInterface> Services not currently enabled
     */
    public function available(Configuration $config): array
    {
        // Filter out enabled services
    }

    /**
     * @return list<ServiceInterface> Currently enabled services
     */
    public function enabled(Configuration $config): array
    {
        // Return only enabled services
    }
}
```

### Supported Services

| Service | Description | Default Version |
|---------|-------------|----------------|
| PostgreSQL | Relational database | 16 |
| MySQL | Relational database | 8.0 |
| MariaDB | Relational database | 11 |
| Redis | Cache & session storage | 7-alpine |
| Mailpit | Email testing | latest |
| MinIO | S3-compatible object storage | latest |
| Elasticsearch | Search engine | 8.11 |
| RabbitMQ | Message queue | 3-management |

### Adding Services Flow

```php
// Command/ServiceAddCommand.php
protected function execute(InputInterface $input, OutputInterface $output): int
{
    $io = new SymfonyStyle($input, $output);

    // 1. Load current configuration
    $config = $this->configManager->load();

    // 2. Get available services
    $available = $this->registry->available($config);

    if (empty($available)) {
        $io->info('All services are already enabled.');
        return Command::SUCCESS;
    }

    // 3. Interactive selection
    $choices = [];
    foreach ($available as $service) {
        $choices[$service->getName()] = sprintf(
            '%s - %s',
            $service->getDisplayName(),
            $service->getDescription()
        );
    }

    $selected = $io->choice(
        'Which services would you like to add?',
        $choices,
        multiple: true
    );

    // 4. Configure each service
    foreach ($selected as $serviceName) {
        $service = $this->registry->get($serviceName);
        $serviceConfig = $this->promptServiceConfig($io, $service);

        // 5. Validate dependencies
        $this->validateDependencies($io, $service, $config);

        // 6. Validate port conflicts
        $this->validatePorts($io, $service, $config);

        // 7. Add to configuration
        $config->services->add($serviceName, $serviceConfig);
    }

    // 8. Save configuration
    $this->configManager->save($config);

    // 9. Regenerate Docker files
    $this->regenerateDockerFiles($config);

    // 10. Ask to start new services
    if ($io->confirm('Start new services now?', true)) {
        foreach ($selected as $serviceName) {
            $this->dockerManager->start($serviceName);
        }
    }

    return Command::SUCCESS;
}
```

### Validations

**Port Conflicts:**
```php
private function validatePorts(
    SymfonyStyle $io,
    ServiceInterface $service,
    Configuration $config
): void {
    $requiredPorts = $service->getRequiredPorts();
    $usedPorts = $this->getUsedPorts($config);

    foreach ($requiredPorts as $port) {
        if (in_array($port, $usedPorts, true)) {
            $io->warning("Port {$port} is already in use.");
            // Prompt for alternative port
        }

        // Check if port is available on host
        if ($this->isPortInUse($port)) {
            $io->error("Port {$port} is in use on your system.");
            // Prompt for alternative
        }
    }
}
```

**Dependency Resolution:**
```php
private function validateDependencies(
    SymfonyStyle $io,
    ServiceInterface $service,
    Configuration $config
): void {
    $dependencies = $service->getDependencies();
    $enabled = array_keys($config->services->enabled());

    foreach ($dependencies as $dependency) {
        if (!in_array($dependency, $enabled, true)) {
            $io->warning(
                "{$service->getDisplayName()} requires {$dependency} which is not enabled."
            );

            if ($io->confirm("Would you like to add {$dependency} as well?", true)) {
                // Recursively add dependency
                $this->addService($io, $dependency, $config);
            } else {
                throw new \RuntimeException("Cannot add service without its dependencies.");
            }
        }
    }
}
```

---

## Testing Strategy

### Framework: Pest

Modern, expressive testing with Pest.

### Test Structure

```
tests/
├── Unit/                               # 70% of coverage
│   ├── Service/
│   │   ├── ConfigManagerTest.php
│   │   ├── DockerComposeGeneratorTest.php
│   │   ├── DockerfileGeneratorTest.php
│   │   ├── ServiceRegistryTest.php
│   │   └── TemplateRendererTest.php
│   ├── ValueObject/
│   │   ├── ConfigurationTest.php
│   │   ├── ServiceConfigTest.php
│   │   └── ServerConfigTest.php
│   └── Container/
│       ├── PostgresqlServiceTest.php
│       ├── RedisServiceTest.php
│       └── ...
├── Integration/                        # 25% of coverage
│   ├── Command/
│   │   ├── InitCommandTest.php
│   │   ├── StartCommandTest.php
│   │   ├── ServiceAddCommandTest.php
│   │   └── ServiceRemoveCommandTest.php
│   └── Service/
│       └── DockerManagerTest.php
└── Fixtures/
    ├── configs/
    │   ├── minimal-seaman.yaml
    │   ├── full-seaman.yaml
    │   └── invalid-seaman.yaml
    └── expected/
        ├── docker-compose-symfony.yml
        ├── docker-compose-nginx-fpm.yml
        └── docker-compose-frankenphp.yml
```

### Test Coverage Requirements

**Minimum: 95% overall coverage**

**Unit Tests (70%):**
- ConfigManager: parsing, validation, saving, merging
- Generators: correct output for all server types and service combinations
- ServiceRegistry: registration, retrieval, filtering
- Value Objects: immutability, validation, edge cases
- Template rendering: correct substitution, escaping
- Service classes: dependency calculation, config generation

**Integration Tests (25%):**
- Command execution end-to-end using `CommandTester`
- Full flow: init → add service → start → logs → stop
- Validation of generated files against fixtures
- Docker interaction (with real Docker in CI, mocked locally)

**Snapshot Testing:**
- Compare generated docker-compose.yml against known-good fixtures
- Alert on unintended changes to generated files

### Mocking Strategy

**Mock Docker in Unit Tests:**
```php
use Symfony\Component\Process\Process;

test('start command executes docker-compose up', function () {
    $process = Mockery::mock(Process::class);
    $process->shouldReceive('run')->once();
    $process->shouldReceive('isSuccessful')->andReturn(true);

    $dockerManager = new DockerManager($process);
    $dockerManager->start();

    expect($process)->toHaveReceived('run');
});
```

**Real Docker in Integration Tests:**
- Spin up actual containers in CI
- Test real service connectivity
- Verify health checks work

### Quality Gates

**PHPStan Level 10:**
```bash
vendor/bin/phpstan analyse src tests --level=10
```

**php-cs-fixer (PER):**
```bash
vendor/bin/php-cs-fixer fix --dry-run --diff
```

**Pest with Coverage:**
```bash
vendor/bin/pest --coverage --min=95
```

**Mutation Testing (Optional):**
```bash
vendor/bin/infection --min-msi=80
```

### CI/CD Pipeline (GitHub Actions)

```yaml
name: CI

on: [push, pull_request]

jobs:
  tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP 8.4
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4
          extensions: mbstring, xml, redis
          coverage: xdebug

      - name: Install dependencies
        run: composer install --prefer-dist

      - name: PHPStan
        run: vendor/bin/phpstan analyse --error-format=github

      - name: Code Style
        run: vendor/bin/php-cs-fixer fix --dry-run --diff

      - name: Tests
        run: vendor/bin/pest --coverage --min=95

      - name: Build PHAR
        run: vendor/bin/box compile

      - name: Integration Tests (with Docker)
        run: |
          docker --version
          ./seaman init --no-interaction
          ./seaman start
          ./seaman status
          ./seaman stop

  release:
    needs: tests
    if: startsWith(github.ref, 'refs/tags/')
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Build PHAR
        run: vendor/bin/box compile
      - name: Create Release
        uses: softprops/action-gh-release@v1
        with:
          files: build/seaman.phar
```

---

## Implementation Phases

### Phase 1: Core Foundation
- Project setup (composer, PHPStan, php-cs-fixer, Pest)
- Value Objects (Configuration, ServerConfig, ServiceConfig)
- ConfigManager (load, save, validate)
- TemplateRenderer (Twig setup)

### Phase 2: Docker Generation
- DockerfileGenerator (all 3 server types)
- DockerComposeGenerator
- Template files for services
- DockerManager (execute docker-compose commands)

### Phase 3: Core Commands
- InitCommand (interactive setup)
- StartCommand, StopCommand, RestartCommand
- RebuildCommand, DestroyCommand
- StatusCommand

### Phase 4: Service Management
- ServiceInterface + Registry
- Implement all service classes
- ServiceAddCommand, ServiceRemoveCommand
- Service validation logic

### Phase 5: Utility Commands
- ShellCommand
- LogsCommand
- XdebugCommand
- ComposerCommand, ConsoleCommand, PhpCommand

### Phase 6: Database Commands
- DbDumpCommand
- DbRestoreCommand
- DbShellCommand

### Phase 7: Distribution
- Box configuration
- Build PHAR
- Bash wrapper script
- Installer script (curl | bash)
- Auto-update mechanism

### Phase 8: Polish
- Comprehensive testing (hit 95% coverage)
- Documentation
- CI/CD setup
- Release automation

---

## Success Criteria

- [ ] PHPStan level 10 passes with zero errors
- [ ] php-cs-fixer validates PER compliance
- [ ] Test coverage ≥ 95%
- [ ] All service types work correctly (Symfony, Nginx+FPM, FrankenPHP)
- [ ] All supported services can be added/removed dynamically
- [ ] Xdebug toggle works without restart
- [ ] Generated docker-compose.yml is valid and functional
- [ ] PHAR builds successfully and executes
- [ ] Bash wrapper downloads and updates PHAR correctly
- [ ] Interactive prompts work smoothly
- [ ] Commands provide helpful error messages
- [ ] Documentation is complete and accurate

---

## Future Enhancements (Out of Scope for v1)

- Multi-project management (run multiple Symfony apps simultaneously)
- Custom service plugins (user-defined services)
- Performance profiling tools integration (Blackfire, Tideways)
- Cloud deployment helpers (export to Kubernetes manifests)
- Team synchronization (share configurations across team)
- GUI dashboard (web interface for service management)
- Plugin system for extending functionality

---

## Questions & Decisions Log

**Q: Why not versioned docker-compose.yml?**
A: Dynamic generation allows flexible service management and keeps the git history clean. The source of truth is seaman.yaml.

**Q: Why PHAR instead of global Composer installation?**
A: PHARs are self-contained, fast to load, and easy to auto-update. No dependency conflicts with project.

**Q: Why Pest over PHPUnit?**
A: Modern syntax, better developer experience, built-in coverage tools. Both are valid, Pest is more ergonomic.

**Q: Why support 3 server types?**
A: Different use cases: Symfony server for quick dev, Nginx+FPM for production parity, FrankenPHP for modern features.

**Q: How to handle breaking changes in seaman.yaml?**
A: Version field in YAML. Migration commands for major version bumps.

---

## Conclusion

Seaman provides a robust, type-safe, well-tested foundation for managing Symfony development environments. The architecture is extensible, the CLI is interactive and user-friendly, and the quality standards are high (PHPStan 10, 95% coverage).

The design prioritizes developer experience while maintaining team consistency and production parity.
