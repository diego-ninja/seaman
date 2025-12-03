# Configuration

Seaman uses a single YAML configuration file located at `.seaman/seaman.yaml`.

## Configuration File Format

```yaml
version: "1.0"

php:
  version: "8.4"
  xdebug:
    enabled: false
    ide_key: "PHPSTORM"
    client_host: "host.docker.internal"

services:
  postgresql:
    enabled: true
    type: "postgresql"
    version: "16"
    port: 5432
    environment:
      POSTGRES_DB: "myapp"
      POSTGRES_USER: "myapp"
      POSTGRES_PASSWORD: "secret"

  redis:
    enabled: true
    type: "redis"
    version: "7-alpine"
    port: 6379

volumes:
  persist:
    - "postgresql"
    - "redis"
```

## PHP Configuration

### Version

Specifies the PHP version for your application container.

```yaml
php:
  version: "8.4"
```

**Supported versions**: `8.3`, `8.4`, `8.5`

The version is auto-detected from your `composer.json` during initialization.

### Xdebug

Configure Xdebug settings for debugging.

```yaml
php:
  xdebug:
    enabled: false
    ide_key: "PHPSTORM"
    client_host: "host.docker.internal"
```

**Options**:
- `enabled`: Whether Xdebug is enabled by default (can be toggled with `seaman xdebug on/off`)
- `ide_key`: IDE key for debugging (default: `PHPSTORM`)
- `client_host`: Host where your IDE is running (default: `host.docker.internal` for Docker Desktop)

**IDE-Specific Keys**:
- PhpStorm / IntelliJ: `PHPSTORM`
- VS Code: `VSCODE`
- Sublime Text: `sublime.xdebug`

**Linux Users**: Change `client_host` to `172.17.0.1` (Docker bridge IP) or use `host.docker.internal` with Docker 20.10+.

## Service Configuration

Each service has a consistent configuration structure:

```yaml
services:
  service_name:
    enabled: true|false
    type: "service_type"
    version: "version_string"
    port: port_number
    ports:
      - "port1"
      - "port2"
    environment:
      KEY: "value"
```

### Common Options

- `enabled`: Whether the service should be started
- `type`: Service type identifier (postgresql, mysql, redis, etc.)
- `version`: Docker image version tag
- `port`: Single port to expose (shorthand)
- `ports`: Multiple ports to expose (array)
- `environment`: Environment variables for the service

### Database Service Example

```yaml
services:
  postgresql:
    enabled: true
    type: "postgresql"
    version: "16"
    port: 5432
    environment:
      POSTGRES_DB: "myapp"
      POSTGRES_USER: "myapp"
      POSTGRES_PASSWORD: "secret"
```

### Multi-Port Service Example

```yaml
services:
  rabbitmq:
    enabled: true
    type: "rabbitmq"
    version: "3.13-management"
    ports:
      - "5672"   # AMQP
      - "15672"  # Management UI
    environment:
      RABBITMQ_DEFAULT_USER: "guest"
      RABBITMQ_DEFAULT_PASS: "guest"
```

## Volume Configuration

Define which services should persist data across container restarts.

```yaml
volumes:
  persist:
    - "postgresql"
    - "redis"
    - "mongodb"
```

Services listed here will have Docker volumes created automatically. This ensures database data and cache persist when containers are stopped and restarted.

**Important**: `seaman destroy` removes these volumes permanently.

## Generated Files

Seaman generates several files based on your configuration:

### docker-compose.yml

Generated Docker Compose file with all services configured. **Do not edit manually** - regenerated from `seaman.yaml` when running commands.

### .seaman/Dockerfile

PHP application container Dockerfile with:
- Selected PHP version
- Required extensions
- Xdebug configuration
- Composer installed

### scripts/xdebug-toggle.sh

Script to enable/disable Xdebug without restarting containers. Used by `seaman xdebug on/off` command.

## Environment Variables

Seaman creates or updates `.env` with Docker-specific variables:

```env
DATABASE_URL="postgresql://myapp:secret@postgresql:5432/myapp?serverVersion=16&charset=utf8"
REDIS_URL="redis://redis:6379"
MAILER_DSN="smtp://mailpit:1025"
```

**Note**: Service hostnames use the service name from `seaman.yaml` (e.g., `postgresql`, `redis`), not `localhost`.

## Customizing Configuration

### Manual Editing

Edit `.seaman/seaman.yaml` directly and rebuild:

```bash
# Edit configuration
vim .seaman/seaman.yaml

# Rebuild to apply changes
seaman rebuild
```

### Interactive Management

Use service management commands:

```bash
# Add new service
seaman service:add

# Remove service
seaman service:remove

# List services
seaman service:list
```

### Changing PHP Version

1. Edit `php.version` in `seaman.yaml`
2. Rebuild containers:

```bash
seaman rebuild
```

### Changing Ports

To avoid port conflicts, change the port numbers:

```yaml
services:
  postgresql:
    port: 5433  # Changed from 5432
```

Then restart:

```bash
seaman restart postgresql
```

## Configuration Best Practices

1. **Keep production secrets separate**: Use different credentials for development
2. **Version control**: Commit `.seaman/seaman.yaml` but not `.env`
3. **Document customizations**: Add comments to your `seaman.yaml` for team members
4. **Use persistent volumes**: Always persist database and cache data
5. **Match versions**: Use service versions compatible with your production environment

## Troubleshooting

### Port Already in Use

If a port is already in use, change it in `seaman.yaml`:

```yaml
services:
  postgresql:
    port: 5433  # Different port
```

### Services Not Starting

Check service logs:

```bash
seaman logs service_name
```

Verify Docker Compose configuration:

```bash
docker compose config
```

### Xdebug Not Connecting

1. Verify IDE key matches:
   ```yaml
   php:
     xdebug:
       ide_key: "PHPSTORM"  # Must match IDE
   ```

2. Check client host (Linux users):
   ```yaml
   php:
     xdebug:
       client_host: "172.17.0.1"  # Docker bridge IP
   ```

3. Ensure Xdebug is enabled:
   ```bash
   seaman xdebug on
   ```

### Configuration Not Applied

Rebuild containers after configuration changes:

```bash
seaman rebuild
```
