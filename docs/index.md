# Seaman Documentation

Seaman generates and manages Docker development environments for Symfony projects.

## What Seaman Does

1. **Generates Docker configuration** from an interactive wizard
2. **Manages the environment** with simple commands (`start`, `stop`, `status`)
3. **Provides shortcuts** to run commands inside containers
4. **Handles common tasks** like Xdebug toggling and database backups

## How It Works

```
seaman init
    ↓
Creates:
  - .seaman/seaman.yaml    (your configuration)
  - .seaman/Dockerfile     (PHP container)
  - docker-compose.yml     (generated, don't edit manually)
  - .env                   (environment variables)
    ↓
seaman start
    ↓
Runs: docker-compose up -d
```

You can inspect and understand all generated files. They're standard Docker Compose.

## Documentation

| Topic | Description |
|-------|-------------|
| [Installation](installation.md) | Install Seaman on your system |
| [Getting Started](getting-started.md) | Initialize and run your first project |
| [Commands](commands.md) | Complete command reference |
| [Services](services.md) | Available databases, caches, and tools |
| [Configuration](configuration.md) | The seaman.yaml format |
| [DevContainers](devcontainers.md) | VS Code integration |
| [Troubleshooting](troubleshooting.md) | Common issues and solutions |
| [Development](development.md) | Building from source |

## Platform Support

| Platform | Architecture | Status |
|----------|--------------|--------|
| Linux | x86_64 | Tested |
| Linux | arm64 | Tested |
| macOS | Apple Silicon | Tested |
| macOS | Intel | Should work |
| Windows | WSL2 | Should work |
| Windows | Native | Not supported |

## Requirements

- **Docker Engine** or **Docker Desktop**
- **Docker Compose V2** (included with Docker Desktop)
- **Symfony CLI** (optional, for creating new projects)

## Quick Example

```bash
# Install
curl -sS https://raw.githubusercontent.com/diego-ninja/seaman/main/installer | bash

# Setup existing project
cd my-symfony-app
seaman init      # Interactive wizard
seaman start     # Start containers

# Work
seaman console cache:clear
seaman composer require doctrine/orm
seaman db:shell

# Debug
seaman xdebug on
# ... debug in your IDE ...
seaman xdebug off
```

## Links

- [GitHub Repository](https://github.com/diego-ninja/seaman)
- [Issue Tracker](https://github.com/diego-ninja/seaman/issues)
- [Releases](https://github.com/diego-ninja/seaman/releases)
