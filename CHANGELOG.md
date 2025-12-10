# Changelog

All notable changes to Seaman will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-12-11

### Added

- **Docker Compose Management**: Generate and manage docker-compose.yml for PHP projects
- **Service Registry**: Support for 20+ services including MySQL, PostgreSQL, Redis, RabbitMQ, and more
- **Reverse Proxy**: Traefik integration with automatic HTTPS and DNS configuration
- **DNS Configuration**: Multiple DNS providers support (hosts file, dnsmasq, NetworkManager, systemd-resolved)
- **Commands**:
  - `seaman:init` - Initialize Seaman in a project with interactive wizard
  - `seaman:rebuild` - Rebuild Docker images and regenerate compose files
  - `service:list` - List available services
  - `service:add` - Add services to the project
  - `service:remove` - Remove services from the project
  - `start` - Start Docker containers
  - `stop` - Stop Docker containers
  - `restart` - Restart Docker containers
  - `status` - Show container status
  - `destroy` - Remove all containers and volumes
  - `clean` - Remove all Seaman-generated files
  - `shell` - Open a shell in the app container
  - `logs` - View container logs
  - `xdebug` - Toggle Xdebug on/off
  - `composer` - Run Composer commands in container
  - `console` - Run Symfony console commands
  - `php` - Run PHP commands in container
  - `db:dump` - Export database to SQL file
  - `db:restore` - Restore database from SQL file
  - `db:shell` - Open database shell
  - `proxy:enable` - Enable reverse proxy
  - `proxy:disable` - Disable reverse proxy
  - `proxy:configure-dns` - Configure DNS for local domains
  - `inspect` - Show detailed project configuration
  - `devcontainer:generate` - Generate VS Code devcontainer configuration
- **Project Detection**: Automatic detection of Symfony, Laravel, and generic PHP projects
- **PHP Version Support**: PHP 8.3, 8.4, and 8.5
- **Privilege Escalation**: pkexec with sudo fallback for DNS configuration
- **Headless Mode**: Non-interactive mode for CI/CD environments
- **Desktop Notifications**: System notifications for long-running operations
- **Passthrough Mode**: Real-time output streaming for `composer`, `php`, and `console` commands

### Technical

- Built with PHP 8.4 and strict types
- Symfony Console components
- PHP-DI for dependency injection
- PHPStan level 10 compliance
- Pest PHP for testing
- Distributable as PHAR archive
