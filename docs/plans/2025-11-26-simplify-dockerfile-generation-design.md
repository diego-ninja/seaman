# Simplify Dockerfile Generation - Design Document

**Date:** 2025-11-26
**Status:** Approved

## Overview

Remove FrankenPHP support and template-based Dockerfile generation. Use a single static Dockerfile from the project root as the source of truth, build and tag it as `seaman/seaman:latest` during initialization and rebuilds.

## Goals

1. Eliminate unnecessary complexity of Dockerfile templates
2. Remove server type selection (Symfony Server only)
3. Build and tag Docker image during `seaman init` and `seaman rebuild`
4. Use pre-built image in docker-compose.yml while keeping build fallback

## Architecture Changes

### File Removals

- `src/Service/DockerfileGenerator.php` - No longer needed
- `src/Template/docker/Dockerfile.symfony.twig` - Replaced by root Dockerfile
- `src/Template/docker/Dockerfile.frankenphp.twig` - FrankenPHP support removed
- `src/ValueObject/ServerConfig.php` - No server choice needed

### File Modifications

#### Configuration Value Object
```php
// Before:
class Configuration {
    public readonly ServerConfig $server;
    // ...
}

// After:
class Configuration {
    public function __construct(
        public readonly string $version,
        public readonly PhpConfig $php,
        public readonly ServiceCollection $services,
        public readonly VolumeConfig $volumes,
    ) {}
}
```

#### docker-compose.yml Template
```yaml
services:
  app:
    image: seaman/seaman:latest    # Use pre-built tagged image
    build:                          # Fallback for development
      context: .
      dockerfile: .seaman/Dockerfile
      args:
        WWWGROUP: ${WWWGROUP:-1000}
    ports:
      - "${APP_PORT:-8000}:8000"   # Port from env var, default 8000
    # ... rest unchanged
```

### New Service: DockerImageBuilder

Create `src/Service/DockerImageBuilder.php` to encapsulate image building logic shared between InitCommand and RebuildCommand:

```php
readonly class DockerImageBuilder
{
    public function __construct(private string $projectRoot) {}

    public function build(): ProcessResult
    {
        $wwwgroup = (string) posix_getgid();
        $command = [
            'docker', 'build',
            '-t', 'seaman/seaman:latest',
            '-f', '.seaman/Dockerfile',
            '--build-arg', "WWWGROUP={$wwwgroup}",
            '.'
        ];

        $process = new Process($command, $this->projectRoot, timeout: 300.0);
        $process->run();

        return new ProcessResult(
            exitCode: $process->getExitCode() ?? 1,
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
        );
    }
}
```

## Command Changes

### InitCommand

**Remove:**
- Server type selection (lines 64-73)
- ServerConfig instantiation (line 94)
- DockerfileGenerator usage

**Add:**
- Copy root `Dockerfile` to `.seaman/Dockerfile`
- Build and tag image before completing initialization

**Workflow:**
1. Gather user input (PHP version, database, services)
2. Build Configuration object (without server)
3. Save seaman.yaml
4. Copy root Dockerfile to `.seaman/Dockerfile`
5. Generate docker-compose.yml
6. Generate xdebug-toggle script
7. **Build and tag Docker image**
8. Display success message

### RebuildCommand

**Remove:**
- Service argument (always rebuild entire stack)
- `DockerManager::rebuild()` usage (uses docker-compose build)

**Replace with:**
1. Build image from `.seaman/Dockerfile` and tag as `seaman/seaman:latest`
2. Stop all services via DockerManager
3. Start all services via DockerManager

**Workflow:**
```php
1. Check seaman.yaml exists
2. Build Docker image using DockerImageBuilder
3. Stop services (DockerManager::stop())
4. Start services (DockerManager::start())
5. Display success/failure
```

## Build and Tag Strategy

**Image naming:** `seaman/seaman:latest`

**Build arguments:**
- `WWWGROUP`: Current user's group ID via `posix_getgid()`

**When image is built:**
- During `seaman init` (one-time setup)
- During `seaman rebuild` (manual rebuild on demand)

**Dockerfile location:**
- Source: Root `Dockerfile` (project template, version controlled)
- Working copy: `.seaman/Dockerfile` (copied during init, used for builds)

## Testing Strategy

### Unit Tests
- Configuration instantiation without ServerConfig
- DockerImageBuilder success/failure cases
- DockerComposeGenerator template rendering

### Integration Tests
1. Run `seaman init` in test project
   - Verify `.seaman/Dockerfile` exists
   - Verify `docker images` shows `seaman/seaman:latest`
   - Verify docker-compose.yml contains image reference
2. Run `seaman rebuild`
   - Verify image is rebuilt
   - Verify services restart successfully

### Quality Checks
1. PHPStan level 10 - all issues resolved
2. php-cs-fixer - code style correct
3. Test coverage ≥ 95%
4. composer validate

## Migration Notes

**Breaking changes:**
- Existing projects with `frankenphp` server type will need to re-run `seaman init`
- `ServerConfig` removed from Configuration - may affect custom extensions

**No migration path needed:** `seaman init` overwrites configuration anyway, so users naturally migrate by re-initializing.

## Implementation Checklist

- [ ] Create DockerImageBuilder service
- [ ] Modify Configuration to remove ServerConfig
- [ ] Update InitCommand (remove server selection, add build step)
- [ ] Update RebuildCommand (add build step, remove service arg)
- [ ] Update DockerComposeGenerator (remove server from context)
- [ ] Update docker-compose template (use image reference)
- [ ] Delete DockerfileGenerator
- [ ] Delete ServerConfig
- [ ] Delete Dockerfile templates
- [ ] Write tests for DockerImageBuilder
- [ ] Update existing tests
- [ ] Run PHPStan
- [ ] Run php-cs-fixer
- [ ] Verify test coverage ≥ 95%
- [ ] Test init workflow end-to-end
- [ ] Test rebuild workflow end-to-end
- [ ] Commit changes
