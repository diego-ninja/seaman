# DevContainers Support for Seaman

**Date:** 2025-12-02
**Status:** Approved
**Author:** Claude & Diego

## Overview

Add DevContainers support to Seaman, enabling developers to use VS Code's "Reopen in Container" feature for a fully configured development environment. This feature will be optional during initialization and can be added to existing projects later.

## Goals

- Provide seamless VS Code Dev Container integration
- Reuse existing Docker infrastructure (docker-compose.yml)
- Auto-configure development tools and extensions
- Keep implementation simple and maintainable
- Follow Seaman's existing patterns and architecture

## Integration Strategy

### Optional Generation

DevContainers support is **optional** and can be enabled in two ways:

**1. With flag (non-interactive):**
```bash
seaman init --with-devcontainer
```
Automatically generates devcontainer files without prompting.

**2. Interactive mode:**
```bash
seaman init
```
After configuring services, prompts: "Do you want to generate DevContainer configuration for VS Code?"
- Yes → generates devcontainer files
- No → skips generation

**3. Manual generation (existing projects):**
```bash
seaman devcontainer:generate
```
Creates or regenerates devcontainer files for projects that already have seaman.yaml.

### Docker Integration

DevContainers will **reference the existing docker-compose.yml** using the `dockerComposeFile` property:

- No duplication of service definitions
- Single source of truth for Docker configuration
- Developers get access to all services automatically (database, Redis, etc.)
- Changes to seaman.yaml flow through docker-compose.yml to devcontainer
- Targets the `app` service as the development container

### Developer Experience

When developers open the project in VS Code and click "Reopen in Container":
1. VS Code reads `.devcontainer/devcontainer.json`
2. Starts all services via docker-compose.yml
3. Attaches to the `app` container
4. Installs configured VS Code extensions
5. Applies workspace settings
6. Runs post-create command (composer install)
7. Developer can start coding immediately

## DevContainer Configuration Structure

### File: `.devcontainer/devcontainer.json`

**Core Configuration:**
- `name`: Project name (from directory or seaman.yaml)
- `dockerComposeFile`: `"../docker-compose.yml"` (references existing compose file)
- `service`: `"app"` (the PHP container)
- `workspaceFolder`: `"/var/www/html"` (standard Symfony location)
- `shutdownAction`: `"stopCompose"` (stops all services when closing)

**Pre-installed VS Code Extensions:**

Base extensions (always included):
- `bmewburn.vscode-intelephense-client` - PHP IntelliSense
- `xdebug.php-debug` - PHP debugging with Xdebug
- `junstyle.php-cs-fixer` - Code style formatting (PER)
- `swordev.phpstan` - Static analysis (PHPStan)

Service-specific extensions (conditional):
- **PostgreSQL/MySQL/MariaDB** → `cweijan.vscode-database-client2` (database GUI)
- **MongoDB** → `mongodb.mongodb-vscode` (MongoDB support)
- **Redis** → `cisco.redis-xplorer` (Redis explorer)
- **Elasticsearch** → `ria.elastic` (Elasticsearch client)
- **Twig templates** → `whatwedo.twig` (Twig syntax highlighting)
- **API Platform project** → `42crunch.vscode-openapi` (OpenAPI/Swagger tools)

**VS Code Settings:**
- Xdebug configuration matching seaman.yaml xdebug settings
- PHPStan path: `vendor/bin/phpstan`
- php-cs-fixer path: `vendor/bin/php-cs-fixer`
- PHP executable path inside container
- File associations for Symfony files

**Post-create Command:**
```json
"postCreateCommand": "composer install"
```
Ensures dependencies are installed when container first starts.

### File: `.devcontainer/README.md`

Documentation explaining:
- What DevContainers are
- How to use "Reopen in Container" in VS Code
- What extensions are pre-installed
- How to customize the configuration
- Troubleshooting common issues

## Implementation Components

### Template System

**New templates:**
- `templates/devcontainer.json.twig` - Main devcontainer configuration
- `templates/devcontainer.readme.md.twig` - Documentation for developers

**Template variables:**
- `php_version` - From seaman.yaml php.version
- `xdebug_config` - From seaman.yaml php.xdebug
- `project_name` - From directory name or config
- `extensions` - Dynamically built array based on enabled services
- `enabled_services` - Array of enabled service names

**Rendering:**
- Use existing `TemplateRenderer` service
- Same pattern as docker-compose.yml generation
- Pass `Configuration` object to template

### Command Structure

**New Command: `DevContainerGenerateCommand`**

Location: `src/Command/DevContainerGenerateCommand.php`

```php
class DevContainerGenerateCommand extends AbstractSeamanCommand
{
    protected function configure(): void
    {
        $this->setName('devcontainer:generate')
             ->setDescription('Generate DevContainer configuration for VS Code');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 1. Validate seaman.yaml and docker-compose.yml exist
        // 2. Read configuration
        // 3. Build extension list based on services
        // 4. Check for existing devcontainer files
        // 5. Generate .devcontainer/devcontainer.json
        // 6. Generate .devcontainer/README.md
        // 7. Display success message with instructions
    }
}
```

**Integration with InitCommand:**

Modify `src/Command/InitCommand.php`:

1. Add `--with-devcontainer` option
2. After generating docker-compose.yml:
   - If flag present → generate devcontainer files
   - If interactive mode → ask "Generate DevContainer configuration?"
3. Call devcontainer generation logic (extract to service for reuse)

### Service Layer

**New Service: `DevContainerGenerator`**

Location: `src/Service/DevContainerGenerator.php`

Responsibilities:
- Build dynamic extension list based on Configuration
- Render devcontainer.json template
- Render README.md template
- Create .devcontainer directory
- Write files with proper permissions
- Handle backups of existing files

```php
class DevContainerGenerator
{
    public function __construct(
        private TemplateRenderer $renderer,
        private ConfigManager $configManager
    ) {}

    public function generate(): void
    {
        // Extension selection logic
        // Template rendering
        // File writing
    }

    private function buildExtensions(Configuration $config): array
    {
        // Dynamic extension selection based on services
    }
}
```

### File Locations

**Generated files:**
- `.devcontainer/devcontainer.json` - Main configuration
- `.devcontainer/README.md` - Documentation

**Template files:**
- `templates/devcontainer.json.twig`
- `templates/devcontainer.readme.md.twig`

**Source files:**
- `src/Command/DevContainerGenerateCommand.php`
- `src/Service/DevContainerGenerator.php`

## Dynamic Extension Selection

Extensions are selected based on enabled services in `seaman.yaml`:

**Detection Logic:**
```php
private function buildExtensions(Configuration $config): array
{
    $extensions = [
        'bmewburn.vscode-intelephense-client',
        'xdebug.php-debug',
        'junstyle.php-cs-fixer',
        'swordev.phpstan',
    ];

    $services = $config->services();

    // Database extensions
    if ($services->hasAnyDatabase()) {
        $extensions[] = 'cweijan.vscode-database-client2';
    }

    if ($services->has('mongodb')) {
        $extensions[] = 'mongodb.mongodb-vscode';
    }

    // Cache/queue extensions
    if ($services->has('redis')) {
        $extensions[] = 'cisco.redis-xplorer';
    }

    // Search extensions
    if ($services->has('elasticsearch')) {
        $extensions[] = 'ria.elastic';
    }

    // Template extensions
    if ($this->hasTwigTemplates()) {
        $extensions[] = 'whatwedo.twig';
    }

    // API Platform
    if ($config->projectType() === ProjectType::API_PLATFORM) {
        $extensions[] = '42crunch.vscode-openapi';
    }

    return $extensions;
}
```

This ensures developers get **only relevant extensions**, keeping the container lean and focused.

## Error Handling and Edge Cases

### Pre-generation Validation

**Missing configuration files:**
```php
if (!file_exists('.seaman/seaman.yaml')) {
    throw new SeamanException('seaman.yaml not found. Run `seaman init` first.');
}

if (!file_exists('docker-compose.yml')) {
    throw new SeamanException('docker-compose.yml not found. Run `seaman init` first.');
}
```

### Existing DevContainer Files

When running `devcontainer:generate` and files already exist:

```php
if (file_exists('.devcontainer/devcontainer.json')) {
    $overwrite = confirm('DevContainer configuration already exists. Overwrite?');

    if (!$overwrite) {
        $this->info('DevContainer generation cancelled.');
        return Command::SUCCESS;
    }

    // Backup existing file
    copy(
        '.devcontainer/devcontainer.json',
        '.devcontainer/devcontainer.json.backup'
    );
}
```

### Directory Creation

```php
if (!is_dir('.devcontainer')) {
    mkdir('.devcontainer', 0755, true);
}
```

### Missing PHP Version

Defensive handling if PHP version somehow missing:
```php
$phpVersion = $config->php()->version() ?? PhpVersion::PHP_84;
```

### Xdebug Configuration

If Xdebug not configured, use sensible defaults:
```php
$xdebugConfig = $config->php()->xdebug() ?? new XdebugConfig(
    enabled: false,
    ideKey: 'VSCODE',
    clientHost: 'host.docker.internal'
);
```

### Success Messages

After successful generation:
```php
$this->success('DevContainer configuration created in .devcontainer/');
$this->info('');
$this->info('Next steps:');
$this->info('  1. Open this project in VS Code');
$this->info('  2. Click "Reopen in Container" when prompted');
$this->info('  3. Wait for container to build and extensions to install');
$this->info('  4. Start coding!');
```

## Testing Strategy

### Unit Tests

**DevContainerGeneratorTest:**
- Test extension selection logic for each service
- Test template variable building
- Test file generation with mocked filesystem
- Test backup creation for existing files
- Test error handling for missing files

**DevContainerGenerateCommandTest:**
- Test command execution success flow
- Test validation errors (missing seaman.yaml)
- Test overwrite prompt behavior
- Test output messages

### Integration Tests

**DevContainer Generation Flow:**
1. Run `seaman init --with-devcontainer` in test project
2. Verify `.devcontainer/devcontainer.json` exists
3. Verify JSON is valid and contains expected structure
4. Verify extensions match enabled services
5. Verify README.md is generated

**InitCommand Integration:**
- Test `--with-devcontainer` flag generates files
- Test interactive mode asks question
- Test skipping when user says no

### Manual Testing

**Real DevContainer Testing:**
1. Generate devcontainer in test Symfony project
2. Open in VS Code
3. Click "Reopen in Container"
4. Verify all extensions install correctly
5. Verify Xdebug works
6. Verify access to database and other services
7. Verify composer install runs successfully

## Documentation Updates

### README.md Updates

Add new section after "Configuration":

```markdown
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
```

### Available Commands Table

Add new row:
```markdown
| `seaman devcontainer:generate` | Generate DevContainer configuration for VS Code |
```

## Implementation Checklist

- [ ] Create `DevContainerGenerator` service
- [ ] Create `DevContainerGenerateCommand` command
- [ ] Create `templates/devcontainer.json.twig` template
- [ ] Create `templates/devcontainer.readme.md.twig` template
- [ ] Add `--with-devcontainer` option to `InitCommand`
- [ ] Add interactive prompt to `InitCommand`
- [ ] Write unit tests for `DevContainerGenerator`
- [ ] Write unit tests for `DevContainerGenerateCommand`
- [ ] Write integration tests
- [ ] Update README.md with DevContainers section
- [ ] Manual testing with real VS Code Dev Container
- [ ] Run PHPStan level 10
- [ ] Run php-cs-fixer
- [ ] Verify 95%+ test coverage
- [ ] Commit and push

## Success Criteria

✅ Running `seaman init --with-devcontainer` generates working devcontainer files
✅ Interactive mode asks about devcontainer generation
✅ `seaman devcontainer:generate` works for existing projects
✅ VS Code can successfully "Reopen in Container"
✅ All extensions install and work correctly
✅ Xdebug works for step debugging
✅ Access to all services (database, Redis, etc.) from container
✅ composer install runs successfully post-create
✅ PHPStan level 10 passes
✅ Test coverage ≥ 95%
✅ Documentation is clear and complete

## Notes

- DevContainers is a VS Code feature; other IDEs (PHPStorm, etc.) may not support it
- This implementation focuses on VS Code as it's the primary editor with DevContainers support
- The configuration can be adapted by users for other tools if needed
- We maintain backward compatibility - existing projects without devcontainers continue to work unchanged
