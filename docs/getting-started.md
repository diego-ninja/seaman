# Getting Started

## Existing Symfony Project

If you already have a Symfony project, navigate to its directory and initialize Seaman:

```bash
cd your-symfony-project
seaman init
```

Seaman will detect your Symfony project and guide you through an interactive setup:

1. **Project Type Detection**: Automatically identifies if you have a Web App, API Platform, Microservice, or Skeleton
2. **PHP Version**: Auto-detected from your `composer.json`
3. **Database Selection**: Choose PostgreSQL, MySQL, MariaDB, MongoDB, or none
4. **Additional Services**: Select Redis, Mailpit, Elasticsearch, RabbitMQ, etc.
5. **Xdebug Configuration**: Choose IDE key and settings
6. **DevContainer**: Optionally generate VS Code DevContainer configuration

### What Gets Created

After initialization, Seaman creates:

- `.seaman/seaman.yaml` - Main configuration file
- `docker-compose.yml` - Generated Docker Compose file
- `.seaman/Dockerfile` - PHP container Dockerfile
- `.env` - Environment variables (if doesn't exist)
- `scripts/xdebug-toggle.sh` - Xdebug toggle script

## New Symfony Project

To create a new Symfony project with Seaman:

```bash
mkdir my-project && cd my-project
seaman init
```

Since no Symfony project is detected, Seaman will:

1. Offer to create a new Symfony project using the Symfony CLI
2. Ask you to select a project type:
   - **Web Application**: Full-stack with Twig, Doctrine, Security, Forms
   - **API Platform**: API-first with API Platform bundle
   - **Microservice**: Minimal framework setup
   - **Skeleton**: Bare minimum Symfony
3. Guide you through the rest of the setup process

> **Note**: Requires Symfony CLI to be installed. Install it from [symfony.com/download](https://symfony.com/download)

## Starting Your Environment

Once initialized, start your Docker environment:

```bash
seaman start
```

This will:
- Start all configured services (app, database, cache, etc.)
- Show startup progress with spinners
- Display service URLs and ports
- Confirm when environment is ready

Your Symfony application will be available at `http://localhost`

## Stopping Your Environment

Stop all services:

```bash
seaman stop
```

Or stop a specific service:

```bash
seaman stop postgresql
```

## Checking Status

View the status of all services:

```bash
seaman status
```

Shows:
- Service name and status (running/stopped)
- Health status
- Exposed ports
- Container uptime

## Next Steps

- [Configure services](services.md) - Add or remove services
- [Learn commands](commands.md) - Explore available commands
- [Configure Xdebug](configuration.md#xdebug) - Set up debugging
- [Use DevContainers](devcontainers.md) - VS Code integration
