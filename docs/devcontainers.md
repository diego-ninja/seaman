# DevContainers

Seaman supports VS Code Dev Containers for a fully configured development environment inside Docker.

## Overview

DevContainers allow you to:
- Develop inside Docker containers
- Have a consistent environment across team members
- Pre-install extensions and tools
- Configure VS Code settings automatically
- Access all services (database, Redis, etc.) from inside the container

## Enabling DevContainers

### During Initialization

```bash
seaman init --with-devcontainer
```

This generates DevContainer configuration along with your Seaman setup.

### For Existing Projects

```bash
seaman devcontainer:generate
```

Generates DevContainer configuration for an already initialized Seaman project.

## What Gets Created

### .devcontainer/devcontainer.json

Main DevContainer configuration file:

```json
{
  "name": "Symfony with Seaman",
  "dockerComposeFile": "../docker-compose.yml",
  "service": "app",
  "workspaceFolder": "/var/www/html",

  "customizations": {
    "vscode": {
      "settings": {
        "php.validate.executablePath": "/usr/local/bin/php",
        "php.suggest.basic": false
      },
      "extensions": [
        "bmewburn.vscode-intelephense-client",
        "xdebug.php-debug",
        "editorconfig.editorconfig"
      ]
    }
  },

  "forwardPorts": [80, 5432, 6379, 8025],
  "postCreateCommand": "composer install"
}
```

### .devcontainer/README.md

Usage instructions and customization guide.

## Using DevContainers

### First Time Setup

1. Install the **Dev Containers** extension in VS Code:
   ```
   code --install-extension ms-vscode-remote.remote-containers
   ```

2. Open your project in VS Code:
   ```bash
   code .
   ```

3. When prompted, click **"Reopen in Container"**

   Or use Command Palette (`Cmd+Shift+P` / `Ctrl+Shift+P`):
   ```
   Remote-Containers: Reopen in Container
   ```

4. VS Code will:
   - Build and start your Docker environment
   - Install configured extensions
   - Run post-create commands
   - Connect your editor to the container

### Daily Usage

Once configured, simply open your project and select "Reopen in Container" when prompted.

## Pre-Configured Features

### PHP Development

- **Intelephense**: PHP IntelliSense and code completion
- **PHP Debug**: Xdebug integration for step debugging
- **EditorConfig**: Consistent coding style

### Xdebug

Xdebug is pre-configured and ready to use:

1. Set breakpoints in your code
2. Press `F5` or use Run & Debug panel
3. Select "Listen for Xdebug" configuration
4. Start debugging

### Service-Specific Extensions

Based on your configured services, Seaman adds relevant extensions:

**PostgreSQL**:
- `ckolkman.vscode-postgres` - PostgreSQL explorer and query runner

**Redis**:
- `dunn.redis` - Redis client and explorer

**MongoDB**:
- `mongodb.mongodb-vscode` - MongoDB explorer and queries

**Docker**:
- `ms-azuretools.vscode-docker` - Docker management

## Customization

Edit `.devcontainer/devcontainer.json` to customize:

### Add VS Code Settings

```json
{
  "customizations": {
    "vscode": {
      "settings": {
        "php.validate.executablePath": "/usr/local/bin/php",
        "files.autoSave": "onFocusChange",
        "editor.formatOnSave": true
      }
    }
  }
}
```

### Add Extensions

```json
{
  "customizations": {
    "vscode": {
      "extensions": [
        "bmewburn.vscode-intelephense-client",
        "esbenp.prettier-vscode",
        "dbaeumer.vscode-eslint"
      ]
    }
  }
}
```

### Change Post-Create Commands

```json
{
  "postCreateCommand": "composer install && php bin/console cache:clear"
}
```

### Forward Additional Ports

```json
{
  "forwardPorts": [80, 5432, 6379, 8025, 9000]
}
```

## Port Forwarding

DevContainers automatically forwards ports defined in `forwardPorts`. Access services via:

- Application: http://localhost
- PostgreSQL: localhost:5432
- Redis: localhost:6379
- Mailpit: http://localhost:8025

## Terminal Access

Open integrated terminal in VS Code (`Ctrl+` ` or `Cmd+` `):
- Already inside the app container
- All tools available: composer, php, console
- Direct access to services by name (postgresql, redis, etc.)

```bash
# No need for "seaman composer" - you're already inside
composer install

# Direct Symfony console access
php bin/console cache:clear

# Direct database access
psql -h postgresql -U myapp myapp
```

## Advantages Over Regular Docker

| Feature | Regular Docker | DevContainers |
|---------|----------------|---------------|
| Editor location | Host machine | Inside container |
| Extensions | Host extensions | Container extensions |
| IntelliSense | Limited in Docker context | Full access to container files |
| Debugging | Requires port forwarding setup | Pre-configured Xdebug |
| Terminal | Need `seaman shell` | Native integrated terminal |
| File performance | Volume mount overhead | Direct container filesystem |

## Troubleshooting

### Container Won't Start

Check Docker Compose is valid:
```bash
docker compose config
```

View container logs:
```bash
docker compose logs app
```

### Extensions Not Installing

1. Open Command Palette (`Cmd+Shift+P`)
2. Run "Developer: Reload Window"
3. Extensions should install on reload

### Can't Connect to Services

Verify services are running:
```bash
seaman status
```

Use service names (not localhost) in your code:
```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        url: 'postgresql://myapp:secret@postgresql:5432/myapp'
```

### Performance Issues on macOS

Add `:cached` or `:delegated` to volume mounts in `docker-compose.yml`:

```yaml
volumes:
  - .:/var/www/html:cached
```

## Best Practices

1. **Commit DevContainer config**: Share configuration with your team via Git
2. **Pin extension versions**: Use specific versions for consistency
3. **Document custom settings**: Add comments explaining non-obvious settings
4. **Test fresh container**: Periodically rebuild from scratch to ensure reproducibility
5. **Use post-create commands**: Automate setup steps (composer install, migrations, etc.)

## Additional Resources

- [VS Code DevContainers Documentation](https://code.visualstudio.com/docs/devcontainers/containers)
- [DevContainer Specification](https://containers.dev/)
- [DevContainer Features](https://containers.dev/features)
