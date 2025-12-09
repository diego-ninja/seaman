# Quickstart

Get a Symfony project running with Docker in under 2 minutes.

## Existing Project

If you already have a Symfony project:

```bash
cd your-symfony-project

# Initialize Seaman (interactive wizard)
seaman init

# Start the environment
seaman start

# Done! Your app is at http://localhost:8000
```

The wizard will ask you about:
- Database (PostgreSQL, MySQL, MariaDB, MongoDB, or none)
- Cache (Redis, Memcached, or none)
- Additional services (Mailpit, Elasticsearch, RabbitMQ, etc.)
- Xdebug settings

## New Project

To create a new Symfony project:

```bash
mkdir my-app && cd my-app

# Seaman will offer to create a new project
seaman init

# Choose project type:
# - Web Application (full-stack)
# - API Platform
# - Microservice
# - Skeleton

seaman start
```

> Requires [Symfony CLI](https://symfony.com/download) for project creation.

## What Gets Created

After `seaman init`:

```
your-project/
├── .seaman/
│   ├── seaman.yaml      # Your configuration (edit this)
│   ├── Dockerfile       # PHP container definition
│   └── scripts/
│       └── xdebug-toggle.sh
├── docker-compose.yml   # Generated (don't edit manually)
├── .env                 # Environment variables
└── ... your code ...
```

## Daily Workflow

```bash
# Start your day
seaman start

# Run Symfony commands
seaman console cache:clear
seaman console make:controller

# Run Composer
seaman composer require symfony/validator
seaman composer install

# Access database
seaman db:shell

# View logs
seaman logs app
seaman logs -f          # Follow mode

# Debug with Xdebug
seaman xdebug on
# ... set breakpoints in your IDE ...
seaman xdebug off

# End your day
seaman stop
```

## Adding/Removing Services

```bash
# Add a service interactively
seaman service:add

# Remove a service
seaman service:remove

# Or edit .seaman/seaman.yaml directly
# then regenerate:
seaman rebuild
```

## Accessing Services

| Service | URL/Port |
|---------|----------|
| Your app | http://localhost:8000 |
| Mailpit UI | http://localhost:8025 |
| Dozzle (logs) | http://localhost:8080 |
| RabbitMQ UI | http://localhost:15672 |
| Minio Console | http://localhost:9001 |

Database ports are exposed to localhost (check `.env` for exact ports).

## Common Commands

| Command | Description |
|---------|-------------|
| `seaman start` | Start all containers |
| `seaman stop` | Stop all containers |
| `seaman restart` | Restart containers |
| `seaman status` | Show container status |
| `seaman shell` | Open bash in app container |
| `seaman console <cmd>` | Run Symfony console |
| `seaman composer <cmd>` | Run Composer |
| `seaman db:shell` | Database CLI |
| `seaman db:dump` | Backup database |
| `seaman db:restore <file>` | Restore database |
| `seaman logs [service]` | View logs |
| `seaman xdebug on/off` | Toggle Xdebug |
| `seaman destroy` | Remove everything (including data) |

## Next Steps

- [Commands Reference](commands.md) — All commands in detail
- [Services](services.md) — Configure databases, caches, queues
- [Configuration](configuration.md) — The seaman.yaml format
- [DevContainers](devcontainers.md) — VS Code integration
- [Troubleshooting](troubleshooting.md) — Common issues
