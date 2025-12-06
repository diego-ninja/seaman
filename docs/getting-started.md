# Getting Started

## Existing Symfony Project

```bash
cd your-symfony-project
seaman init
seaman start
```

The interactive wizard will ask about:
- Database (PostgreSQL, MySQL, MariaDB, MongoDB, SQLite, or none)
- Cache (Redis, Valkey, Memcached, or none)
- Additional services (Mailpit, Elasticsearch, RabbitMQ, etc.)
- Xdebug settings

Your app will be available at http://localhost:8000

## New Symfony Project

```bash
mkdir my-project && cd my-project
seaman init
```

When no Symfony project is detected, Seaman offers to create one using Symfony CLI.

Choose a project type:
- **Web Application** — Full-stack with Twig, Doctrine, Security, Forms
- **API Platform** — API-first with API Platform bundle
- **Microservice** — Minimal framework setup
- **Skeleton** — Bare minimum Symfony

> Requires [Symfony CLI](https://symfony.com/download) for project creation.

## Generated Files

After `seaman init`:

```
your-project/
├── .seaman/
│   ├── seaman.yaml      # Configuration (edit this)
│   ├── Dockerfile       # PHP container
│   └── scripts/
│       └── xdebug-toggle.sh
├── docker-compose.yml   # Generated (regenerated on changes)
├── .env                 # Environment variables
└── ... your code ...
```

Edit `.seaman/seaman.yaml` to change configuration, then run `seaman rebuild` to regenerate Docker files.

## Basic Commands

```bash
seaman start             # Start all services
seaman stop              # Stop all services
seaman restart           # Restart all services
seaman status            # Show service status
```

## Running Commands in Containers

```bash
seaman console <cmd>     # Symfony console
seaman composer <cmd>    # Composer
seaman php <cmd>         # PHP CLI
seaman shell             # Bash shell in app container
```

Examples:
```bash
seaman console cache:clear
seaman console make:controller UserController
seaman composer require symfony/validator
seaman php -v
```

## Database Access

```bash
seaman db:shell                    # Open database CLI
seaman db:dump                     # Backup database
seaman db:dump --output=backup.sql # Custom filename
seaman db:restore backup.sql       # Restore backup
```

## Xdebug

```bash
seaman xdebug on    # Enable
seaman xdebug off   # Disable
```

Configure your IDE to listen on port 9003 with IDE key `PHPSTORM` (or your configured key).

## Next Steps

- [Quickstart](quickstart.md) — Complete walkthrough
- [Commands](commands.md) — All commands
- [Services](services.md) — Add databases, caches, tools
- [Configuration](configuration.md) — The seaman.yaml format
- [Troubleshooting](troubleshooting.md) — Common issues
