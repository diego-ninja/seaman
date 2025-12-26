# FrankenPHP Support Design

## Goal

Add FrankenPHP as an alternative to Symfony Server for serving applications in Seaman Docker containers. Users can choose between three server options during project initialization.

## Architecture

The wizard presents a server selection after PHP version. Based on the choice, Seaman generates a customized Dockerfile using Twig templates. FrankenPHP uses the official `dunglas/frankenphp` image with all PHP extensions pre-installed for parity with the current Ubuntu-based image.

## Server Options

| Option | Image Base | Description |
|--------|-----------|-------------|
| `symfony` | Ubuntu 24.04 + ondrej/php | Current behavior, Symfony CLI server |
| `frankenphp` | dunglas/frankenphp | Classic mode, traditional PHP execution |
| `frankenphp-worker` | dunglas/frankenphp | Worker mode, persistent process in memory |

## Data Model Changes

### New Enum: `ServerType`

```php
enum ServerType: string
{
    case SymfonyServer = 'symfony';
    case FrankenPhpClassic = 'frankenphp';
    case FrankenPhpWorker = 'frankenphp-worker';
}
```

### PhpConfig Changes

Add `ServerType $server` property alongside `version` and `xdebug`.

### Configuration YAML

```yaml
php:
  version: "8.4"
  server: frankenphp  # NEW
  xdebug:
    enabled: false
```

### Backward Compatibility

`ConfigParser` defaults to `ServerType::SymfonyServer` when `server` field is missing, ensuring existing projects continue working.

## Wizard Flow

```
1. selectProjectType()
2. selectPhpVersion()
3. selectServer()           ← NEW
4. selectDatabase()
5. selectServices()
6. enableXdebug()           ← Warning if worker + xdebug
7. shouldUseProxy()
8. enableDevContainer()
9. selectDnsConfiguration()
```

### Server Selection Prompt

```
Select application server
> Symfony Server - Built-in development server
  FrankenPHP - Modern PHP server with Caddy
  FrankenPHP Worker - Persistent process (advanced)
```

### Xdebug Warning

When selecting `frankenphp-worker` + Xdebug enabled:
> "Xdebug en modo worker requiere reiniciar el contenedor tras activar/desactivar"

## Dockerfile Template

Replace `docker/Dockerfile.template` with `docker/Dockerfile.twig`:

### Symfony Server Branch

```dockerfile
FROM ubuntu:24.04
# Current implementation unchanged
CMD ["symfony", "server:start", "--port=80", "--allow-all-ip"]
```

### FrankenPHP Branches

```dockerfile
FROM dunglas/frankenphp:latest-php{{ php_version }}-bookworm

RUN install-php-extensions \
    pgsql pdo_pgsql mysql pdo_mysql sqlite3 pdo_sqlite \
    gd curl imap mbstring xml zip bcmath soap intl \
    redis memcached imagick xdebug yaml gmp

# Node.js, Composer, tools
RUN apt-get update && apt-get install -y \
    nodejs npm git zip unzip supervisor ...

# Classic mode
CMD ["frankenphp", "php-server", "--root", "/var/www/html/public"]

# OR Worker mode
COPY .seaman/Caddyfile /etc/caddy/Caddyfile
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
```

### Template Variables

- `server`: symfony | frankenphp | frankenphp-worker
- `php_version`: 8.3 | 8.4 | 8.5
- `xdebug`: XdebugConfig object

## Caddyfile for Worker Mode

Generated only when `server == frankenphp-worker`:

```caddyfile
{
    frankenphp
    order php_server before file_server
}

:80 {
    root * /var/www/html/public

    php_server {
        worker {
            file ./public/index.php
            num {$PHP_WORKERS:2}
        }
    }

    file_server
}
```

Location: `.seaman/Caddyfile`

## ProjectInitializer Changes

```php
// Render Dockerfile with Twig
$dockerfileContent = $renderer->render('docker/Dockerfile.twig', [
    'server' => $config->php->server->value,
    'php_version' => $config->php->version->value,
    'xdebug' => $config->php->xdebug,
]);
file_put_contents($seamanDir . '/Dockerfile', $dockerfileContent);

// Generate Caddyfile for worker mode
if ($config->php->server === ServerType::FrankenPhpWorker) {
    $caddyfileContent = $renderer->render('docker/Caddyfile.twig', []);
    file_put_contents($seamanDir . '/Caddyfile', $caddyfileContent);
}
```

## Files to Create/Modify

### New Files
- `src/Enum/ServerType.php`
- `src/Template/docker/Dockerfile.twig`
- `src/Template/docker/Caddyfile.twig`

### Modified Files
- `src/ValueObject/InitializationChoices.php` - Add server property
- `src/ValueObject/PhpConfig.php` - Add server property
- `src/Service/InitializationWizard.php` - Add selectServer()
- `src/Service/ProjectInitializer.php` - Render Dockerfile with Twig
- `src/Service/ConfigParser/PhpConfigParser.php` - Parse server field
- `config/container.php` - No changes needed

### Deleted Files
- `docker/Dockerfile.template` - Replaced by Twig template

## Testing

### Unit Tests
1. `ServerType` enum cases and values
2. `InitializationWizard::selectServer()` returns correct type
3. `PhpConfigParser` parses `server` field with fallback
4. `PhpConfig` serialization includes server
5. `Dockerfile.twig` renders correctly for each ServerType
6. `Caddyfile.twig` generation only for worker mode

### Integration Tests
- Full wizard flow with each server option
- Verify generated Dockerfile content

## PHP Extensions Parity

Both images install:
- Database: pgsql, pdo_pgsql, mysql, pdo_mysql, sqlite3, pdo_sqlite
- Core: gd, curl, imap, mbstring, xml, zip, bcmath, soap, intl, ldap
- Cache: redis, memcached
- Dev: xdebug, pcov
- Extra: imagick, yaml, gmp, igbinary, msgpack
