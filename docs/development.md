# Development

Guide for building Seaman from source and contributing to the project.

## Requirements

- PHP 8.4+
- Composer
- Docker Desktop or Docker Engine
- Git

## Building from Source

### Clone Repository

```bash
git clone https://github.com/diego-ninja/seaman.git
cd seaman
```

### Install Dependencies

```bash
composer install
```

This installs:
- Symfony components (Console, Process, YAML)
- Laravel Prompts (interactive CLI)
- Twig (template engine)
- PHPStan (static analysis)
- Pest (testing framework)
- php-cs-fixer (code style)

### Run Seaman

During development, run Seaman directly:

```bash
php bin/seaman <command>
```

Or use the binary:

```bash
bin/seaman <command>
```

## Testing

Seaman uses Pest for testing with 95%+ code coverage requirement.

### Run All Tests

```bash
vendor/bin/pest
```

Or using composer script:

```bash
composer test
```

### Run with Coverage

```bash
vendor/bin/pest --coverage
```

Enforce 95% minimum coverage:

```bash
composer test:coverage
```

### Run Specific Tests

```bash
vendor/bin/pest tests/Unit/Service/ConfigManagerTest.php
vendor/bin/pest --filter=testServiceConfiguration
```

### Architecture Tests

Seaman includes architecture tests to ensure code quality:

```php
arch('commands')
    ->expect('Seaman\Command')
    ->toExtend('Symfony\Component\Console\Command\Command')
    ->toHaveSuffix('Command');

arch('services')
    ->expect('Seaman\Service')
    ->toBeClasses()
    ->toHaveMethod('__construct');
```

## Code Quality

### PHPStan (Level 10)

Run static analysis at strictest level:

```bash
vendor/bin/phpstan analyse
```

Or using composer:

```bash
composer phpstan
```

**Level 10 Requirements**:
- All parameters typed
- All return types declared
- All properties typed
- No mixed types (unless absolutely necessary)
- Complete generic annotations
- Null safety checks

### Code Style (PER)

Check code style:

```bash
vendor/bin/php-cs-fixer fix --dry-run --diff
```

Fix code style:

```bash
vendor/bin/php-cs-fixer fix
```

Or using composer:

```bash
composer cs:check  # Check only
composer cs:fix    # Fix
```

### Run All Quality Checks

```bash
composer quality
```

Runs:
1. PHPStan analysis
2. Code style check
3. Tests with coverage

## Building PHAR

### Using Build Command

```bash
php bin/seaman build
```

Or using composer:

```bash
composer build
```

This creates `build/seaman.phar` using Box.

### Box Configuration

PHAR is built with `box.json`:

```json
{
  "main": "bin/seaman",
  "output": "build/seaman.phar",
  "directories": ["src", "helpers"],
  "files": ["config/listeners.php"],
  "compression": "GZ",
  "compactors": [
    "KevinGH\\Box\\Compactor\\Php"
  ]
}
```

### Testing PHAR

Test the built PHAR:

```bash
php build/seaman.phar --version
php build/seaman.phar init
```

## Project Structure

```
seaman/
├── src/
│   ├── Command/              # CLI commands
│   │   ├── Database/         # Database commands (db:shell, db:dump, etc.)
│   │   └── Plugin/           # Plugin commands (plugin:list, plugin:install, etc.)
│   ├── Enum/                 # Enums (ServiceCategory, etc.)
│   ├── Plugin/               # Plugin system
│   │   ├── Attribute/        # PHP attributes (#[AsSeamanPlugin], etc.)
│   │   ├── Config/           # Plugin configuration (ConfigSchema)
│   │   ├── Export/           # Plugin export functionality
│   │   ├── Extractor/        # Service/template extraction from plugins
│   │   └── Loader/           # Plugin loaders (Bundled, Composer, Local)
│   ├── Service/              # Business logic services
│   │   ├── Container/        # Service registry and discovery
│   │   ├── Detector/         # Project detection (Symfony, PHP version)
│   │   └── Generator/        # Docker Compose generation
│   ├── Template/             # Twig templates for Docker files
│   ├── UI/                   # Terminal UI helpers
│   ├── ValueObject/          # Value objects (Configuration, HealthCheck, etc.)
│   └── Application.php       # Main application
├── plugins/                  # Bundled plugins (shipped with Seaman)
│   ├── mysql/
│   ├── postgresql/
│   ├── redis/
│   └── ...
├── tests/
│   ├── Unit/                 # Unit tests
│   └── Integration/          # Integration tests
├── config/
│   └── container.php         # PHP-DI container configuration
├── docker/                   # Docker templates (Dockerfile, scripts)
├── assets/                   # Static assets (logos, etc.)
├── bin/
│   └── seaman                # Entry point
├── box.json                  # Box PHAR configuration
└── composer.json
```

### Key Directories

- **plugins/**: Bundled plugins that ship with Seaman. Each subdirectory is a plugin with `src/` containing the plugin class.
- **src/Plugin/**: Core plugin system infrastructure (loaders, attributes, extractors).
- **src/Service/Container/**: Service registry that discovers services from plugins.

## Architecture

### Command Pattern

All commands extend `AbstractSeamanCommand`:

```php
<?php

declare(strict_types=1);

namespace Seaman\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExampleCommand extends AbstractSeamanCommand
{
    protected function configure(): void
    {
        $this->setName('example')
             ->setDescription('Example command');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Command logic
        return Command::SUCCESS;
    }
}
```

### Service Pattern

Services are instantiated in `Application.php` and injected into commands:

```php
$configManager = new ConfigManager($projectRoot, $registry);
$command = new ServiceListCommand($configManager, $registry);
```

### Event System

Events use attribute-based listener registration:

```php
<?php

namespace Seaman\Listener;

use Seaman\Event\CommandExecutedEvent;
use Seaman\EventListener\ListensTo;

#[ListensTo(CommandExecutedEvent::class, priority: 10)]
class LogCommandListener
{
    public function __invoke(CommandExecutedEvent $event): void
    {
        // Handle event
    }
}
```

Listeners are auto-discovered in development and precompiled for PHAR.

### Template System

Docker files are generated from Twig templates:

```twig
{# templates/docker-compose.yml.twig #}
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: .seaman/Dockerfile
    volumes:
      - .:/var/www/html

  {% for name, service in services %}
  {{ name }}:
    image: {{ service.image }}
    ports:
      {% for port in service.ports %}
      - "{{ port }}:{{ port }}"
      {% endfor %}
  {% endfor %}
```

## Contributing Guidelines

### Code Standards

1. **PHP 8.4 Features**: Use modern PHP (property hooks, asymmetric visibility, etc.)
2. **Strict Types**: All files must have `declare(strict_types=1);`
3. **Type Safety**: PHPStan level 10 compliance
4. **Test Coverage**: 95%+ coverage for all new code
5. **Code Style**: PER (PHP Evolving Recommendation)
6. **Documentation**: PHPDoc for all public methods

### ABOUTME Comments

All PHP files must start with ABOUTME comments:

```php
<?php

// ABOUTME: Manages Docker service configuration and lifecycle.
// ABOUTME: Handles service registry and Docker Compose generation.

declare(strict_types=1);
```

### Test-Driven Development

Follow TDD workflow:

1. Write failing test
2. Run test to confirm failure
3. Write minimal code to pass
4. Run test to confirm success
5. Refactor if needed

### Git Workflow

1. Fork repository
2. Create feature branch: `git checkout -b feature/my-feature`
3. Make changes with atomic commits
4. Run quality checks: `composer quality`
5. Push branch: `git push origin feature/my-feature`
6. Create Pull Request

### Commit Messages

Follow conventional commits:

```
feat: add MongoDB support to db:shell command
fix: resolve port conflict in service initialization
docs: update configuration examples
test: add coverage for DockerManager
refactor: simplify service registry lookup
```

### Pull Request Checklist

- [ ] Tests pass: `composer test`
- [ ] PHPStan clean: `composer phpstan`
- [ ] Code style fixed: `composer cs:fix`
- [ ] Coverage ≥ 95%: `composer test:coverage`
- [ ] ABOUTME comments added
- [ ] Documentation updated
- [ ] PHAR builds: `composer build`

## Debugging

### Enable Xdebug

When developing Seaman itself:

1. Install Xdebug in your PHP environment
2. Configure your IDE
3. Set breakpoints
4. Run commands:
   ```bash
   php bin/seaman init
   ```

### Verbose Output

Use `-v`, `-vv`, or `-vvv` for debug output:

```bash
php bin/seaman start -vvv
```

### Docker Debugging

Check generated Docker Compose:

```bash
docker compose config
```

View service logs:

```bash
docker compose logs service_name
```

## Release Process

1. Update version in `Application.php`
2. Update CHANGELOG.md
3. Run quality checks: `composer quality`
4. Build PHAR: `composer build`
5. Test PHAR thoroughly
6. Create Git tag: `git tag v1.0.0`
7. Push tag: `git push --tags`
8. Create GitHub release with PHAR attached

## Getting Help

- [GitHub Issues](https://github.com/diego-ninja/seaman/issues) - Bug reports and features
- [Discussions](https://github.com/diego-ninja/seaman/discussions) - Questions and ideas
- [Contributing Guide](CONTRIBUTING.md) - Detailed contribution guidelines

## License

MIT License - See LICENSE file for details
