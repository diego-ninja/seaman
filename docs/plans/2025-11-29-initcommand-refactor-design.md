# InitCommand Refactoring Design

**Date:** 2025-11-29
**Status:** Validated
**Author:** Diego & Claude

## Overview

Refactor the `InitCommand` to complete the migration to Laravel Prompts and Termwind, simplify the configuration process, and add intelligent Symfony project detection and bootstrapping capabilities.

## Goals

1. Complete migration from SymfonyStyle to Laravel Prompts
2. Remove PHP version and extension selection (hardcode to PHP 8.4, all extensions in Dockerfile)
3. Detect execution mode (PHAR vs dev dependency)
4. Detect existing Symfony applications vs new projects
5. Offer to bootstrap new Symfony projects with multiple project types
6. Streamline configuration with smart defaults based on project type
7. Improve user experience with context-aware prompts and confirmation summary

## Key Design Decisions

### 1. Detection & Mode Logic

**Execution Mode Detection:**
- Detect PHAR vs dev dependency using `Phar::running()`
- Both modes offer identical functionality (no feature restrictions)
- Mode detection informs messaging but not capabilities

**Symfony Application Detection:**

Use flexible detection with multiple indicators (require 2-3 matches):
- `composer.json` exists with `symfony/framework-bundle` in require section
- `bin/console` file exists and is executable
- `config/` directory exists
- `src/Kernel.php` exists

**Detection Outcomes:**
- **Symfony found (2-3 indicators)** → Skip to Docker configuration
- **Partial match (1 indicator)** → Show warning, ask to bootstrap anyway
- **No Symfony** → Offer to create new project (default: yes)
- **User declines bootstrap** → Exit gracefully with guidance message

### 2. Symfony Project Bootstrap

**Project Type Selection:**

Four options via `select()`:

1. **Full Web Application** - Complete web app with Twig, Doctrine, Security, Forms
   - Command: `symfony new {name} --webapp`

2. **API Platform** - API-first with API Platform bundle, serialization, validation
   - Commands: `symfony new {name} --webapp && cd {name} && composer require api`

3. **Microservice** - Minimal Symfony with framework-bundle only
   - Command: `symfony new {name} --webapp=false`

4. **Skeleton** - Bare minimum framework-bundle
   - Command: `symfony new {name} --webapp=false`

**Project Name Handling:**
- Empty directory → Ask for project name, create subdirectory
- Directory with files but no Symfony → Confirm "Create in current directory?" (default: no)
- Project name becomes Docker container prefix (e.g., "myapp" → "myapp-app", "myapp-postgresql")

**Post-Bootstrap:**
- Change working directory to new project
- Continue with Docker configuration flow

### 3. Docker Configuration Flow

**Question Sequence:**

1. **Database Selection** - `select()` with 5 options:
   - PostgreSQL (default)
   - MySQL
   - MariaDB
   - SQLite (consolidated from sqlite/sqlite3)
   - None

2. **Additional Services** - `multiselect()` with smart pre-selection:
   - **Full Web Application** → redis, mailpit
   - **API Platform** → redis
   - **Microservice** → redis
   - **Skeleton** → none

   Available: redis, mailpit, minio, elasticsearch, rabbitmq

3. **Xdebug Toggle** - `confirm()` yes/no (default: no)
   - If yes → XdebugConfig(enabled: true, ideKey: "seaman", clientHost: "host.docker.internal")

**Configuration Summary:**

Before generating files, show formatted summary using Termwind:

```
Creating Seaman environment:

  Project: myapp (Full Web Application)
  Database: PostgreSQL
  Services: Redis, Mailpit
  Xdebug: Enabled

This will create:
  • seaman.yaml
  • docker-compose.yml
  • .seaman/ directory
  • Dockerfile (if not present)
  • Docker image: seaman/myapp:latest

Continue? (yes/no)
```

User must confirm to proceed. If declined, exit without changes.

### 4. File Generation & Docker Build

**Dockerfile Handling:**

- **New Symfony project (bootstrapped)** → Automatically copy Seaman's template Dockerfile to project root
- **Existing Symfony project:**
  - Dockerfile exists → Use it (copy to `.seaman/Dockerfile`)
  - Dockerfile missing → `confirm()`: "No Dockerfile found. Use Seaman's template Dockerfile?"
    - Yes → Copy Seaman's template Dockerfile to project root, proceed
    - No → Exit with message to add Dockerfile manually

**Configuration Object Creation:**

```php
$php = new PhpConfig(
    version: '8.4',  // Hardcoded, no selection
    extensions: [],   // Empty, all in Dockerfile
    xdebug: $xdebugConfig
);

$config = new Configuration(
    version: '1.0',
    php: $php,
    services: new ServiceCollection($services),
    volumes: new VolumeConfig($persistVolumes)
);
```

**File Generation Sequence:**

1. Create `.seaman/` directory (0755 permissions)
2. Generate `seaman.yaml` via `ConfigManager->save()`
3. Copy Dockerfile to `.seaman/Dockerfile`
4. Generate `docker-compose.yml` via `DockerComposeGenerator`
5. Generate `scripts/xdebug-toggle.sh` in project root and `.seaman/scripts/`
6. Set xdebug-toggle.sh as executable (0755)

**Docker Image Build:**

- Use `DockerImageBuilder` to build image
- Stream build output to terminal
- Show success message or error output on failure

### 5. Success Output & Next Steps

**Success Message:**

```
✓ Seaman initialized successfully!

Next steps:
  1. Run 'seaman start' to start your containers
  2. Run 'seaman status' to check service status
  3. Your application will be available at http://localhost:8000

Useful commands:
  • seaman shell - Access container shell
  • seaman logs - View container logs
  • seaman composer - Run Composer commands
  • seaman console - Run Symfony console commands
  • seaman --help - See all available commands
```

**Error Handling:**

Clear messages for common failures:
- **Symfony CLI not found** → "Symfony CLI is required. Install from: https://symfony.com/download"
- **Docker not running** → "Docker is not running. Please start Docker and try again."
- **Permission denied** → "Permission denied creating files. Check directory permissions."
- **Port conflicts** → Suggest changing ports in seaman.yaml

**Return Codes:**
- `Command::SUCCESS` (0) - Successful initialization or user cancellation
- `Command::FAILURE` (1) - Any error during process

## Implementation Notes

### Removed Features
- PHP version selection (hardcoded to 8.4)
- PHP extension selection (all extensions in Dockerfile)
- Duplicate SymfonyStyle code (lines 75-178 in current implementation)
- Manual IDE key configuration for Xdebug

### New Dependencies
- Symfony CLI (external requirement for project creation)
- Seaman's template Dockerfile as embedded resource

### File Structure Changes
- Template Dockerfile must be accessible from command context
- Design supports both PHAR (embedded) and dev (filesystem) access

### Backwards Compatibility
- Existing `seaman.yaml` files remain compatible
- No changes to Configuration value objects
- No changes to DockerComposeGenerator or DockerImageBuilder

## Testing Considerations

### Test Coverage Required

1. **Symfony Detection:**
   - Empty directory
   - Partial Symfony setup (1 indicator)
   - Valid Symfony project (2-3 indicators)
   - Full Symfony project (all indicators)

2. **Execution Mode:**
   - PHAR mode behavior
   - Dev dependency mode behavior

3. **Project Bootstrap:**
   - Each project type (Web App, API Platform, Microservice, Skeleton)
   - Project name handling in various directory states
   - Symfony CLI error scenarios

4. **Dockerfile Handling:**
   - Existing Dockerfile (use it)
   - Missing Dockerfile with user accepting template
   - Missing Dockerfile with user declining template
   - Template copy for new projects

5. **Configuration Flow:**
   - Each database option
   - Service pre-selection for each project type
   - Xdebug enabled/disabled
   - User declining confirmation summary

6. **Error Cases:**
   - Symfony CLI not installed
   - Docker not running
   - Permission errors
   - Build failures

### Integration Tests
- End-to-end flow for new project creation
- End-to-end flow for existing project Docker setup
- Verify all generated files are valid (YAML syntax, executable permissions)

## Success Criteria

1. InitCommand uses only Laravel Prompts (no SymfonyStyle)
2. Smart Symfony detection with 2-3 indicators
3. Successful project bootstrap for all four project types
4. Smart service pre-selection based on project type
5. Dockerfile handling works for both new and existing projects
6. Configuration summary displays before file generation
7. All tests pass with ≥95% coverage
8. PHPStan level 10 compliance maintained
9. Generated files match expected structure
10. User experience is smooth with clear messaging

## Future Enhancements (Out of Scope)

- Auto-detect IDE for Xdebug configuration
- Support for non-Symfony PHP projects
- Custom service configuration during init
- Project template selection (e.g., API Platform with specific bundles)
- Validation of Symfony CLI version compatibility
