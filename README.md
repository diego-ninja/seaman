<p align="center">
    <img alt="Seaman logo" src="/assets/seaman-logo-github.png"/>
</p>

# Seaman

**Docker development environments for Symfony, without the boilerplate.**

Seaman generates and manages Docker Compose configurations for Symfony projects. Instead of writing docker-compose.yml, Dockerfiles, and environment files from scratch, you run `seaman init` and get a working development environment in under a minute.

## What does Seaman do?

- **Generates Docker configuration** — Creates docker-compose.yml, Dockerfile, and .env tailored to your Symfony project
- **Manages services** — Add/remove databases, caches, queues, and dev tools with simple commands
- **Handles the tedious parts** — Healthchecks, networking, volume persistence, port conflicts
- **Provides shortcuts** — `seaman console`, `seaman composer`, `seaman db:shell` instead of `docker-compose exec...`
- **Toggles Xdebug** — Enable/disable without rebuilding containers
- **Generates DevContainers** — VS Code integration out of the box

## Why not just write docker-compose.yml?

You can. Seaman generates standard Docker Compose files you can inspect, modify, or replace.

Seaman is useful when you want:
- **Fast onboarding** — New team members run two commands instead of reading setup docs
- **Consistent defaults** — Same PHP config, healthchecks, and networking across projects
- **Less maintenance** — Update Seaman, regenerate configs, done
- **Interactive setup** — Choose services from a menu instead of copy-pasting YAML

Tradeoffs:
- Less flexibility than hand-written configs
- Another tool to learn
- Opinionated structure (`.seaman/` directory, generated files)

## Platform Support

| Platform | Architecture | Status |
|----------|--------------|--------|
| Linux | x86_64 | Tested |
| Linux | arm64 | Tested |
| macOS | Apple Silicon (M1/M2/M3) | Tested |
| macOS | Intel | Should work (untested) |
| Windows | WSL2 | Should work (untested) |
| Windows | Native | Not supported |

**Requirements:**
- Docker Engine or Docker Desktop
- Docker Compose V2

## 60-Second Quickstart

```bash
# Install Seaman
curl -sS https://raw.githubusercontent.com/diego-ninja/seaman/main/installer | bash

# Initialize your project (or create new one)
cd your-symfony-project
seaman init

# Start the environment
seaman start

# Your app is running at http://localhost:8000
```

## Installation

### Global (recommended)

```bash
curl -sS https://raw.githubusercontent.com/diego-ninja/seaman/main/installer | bash
```

Installs to `/usr/local/bin` or `~/.local/bin`.

### As Composer dependency

```bash
composer require --dev seaman/seaman
vendor/bin/seaman init
```

### Verify installation

```bash
seaman --version
```

## Basic Usage

```bash
seaman init              # Interactive setup
seaman start             # Start all services
seaman stop              # Stop all services
seaman status            # Show service status

seaman console cache:clear       # Run Symfony console
seaman composer require foo/bar  # Run Composer
seaman db:shell                  # Database CLI

seaman xdebug on         # Enable Xdebug
seaman xdebug off        # Disable Xdebug

seaman service:add       # Add a service
seaman service:remove    # Remove a service
```

## Available Services

**Databases:** PostgreSQL, MySQL, MariaDB, MongoDB, SQLite

**Cache:** Redis, Valkey, Memcached

**Queues:** RabbitMQ, Kafka

**Search:** Elasticsearch, OpenSearch

**Dev Tools:** Mailpit (email testing), Minio (S3-compatible storage), Dozzle (log viewer)

**Proxy:** Traefik (reverse proxy with automatic HTTPS)

## Documentation

Full documentation: [docs/](docs/index.md)

- [Installation](docs/installation.md)
- [Getting Started](docs/getting-started.md)
- [Commands Reference](docs/commands.md)
- [Services](docs/services.md)
- [Configuration](docs/configuration.md)
- [DevContainers](docs/devcontainers.md)

## Inspiration

Seaman is inspired by [Laravel Sail](https://github.com/laravel/sail) and [DDEV](https://github.com/ddev/ddev). Sail provides similar functionality for Laravel projects, while DDEV is a comprehensive local development environment for PHP projects. If you've used either, Seaman will feel familiar. If you haven't, don't worry — Seaman is standalone and doesn't require any prior knowledge of these tools.

## License

MIT License. See [LICENSE](LICENSE).

## Credits

Developed by [Diego Rin](https://diego.ninja).

- [Report bugs](https://github.com/diego-ninja/seaman/issues)
- [Request features](https://github.com/diego-ninja/seaman/issues)
- [Contribute](docs/development.md)
