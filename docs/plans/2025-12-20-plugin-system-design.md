# Plugin System Design

## Overview

Sistema de plugins para Seaman que permite extender la funcionalidad sin modificar el core. Los plugins pueden añadir servicios Docker, comandos CLI, hooks de lifecycle, y sobrescribir templates.

## Arquitectura General

### Componentes Principales

```
Application::boot()
  → PluginRegistry::discover()
    → ComposerPluginLoader::load()  // vendor packages
    → LocalPluginLoader::load()      // .seaman/plugins/*.php
  → PluginRegistry::initialize()
    → Para cada plugin:
      → Validar dependencias
      → Registrar servicios (ServiceRegistry)
      → Registrar comandos (Application)
      → Registrar hooks (EventDispatcher)
      → Registrar templates (Twig paths)
  → Application::run()
```

| Componente | Responsabilidad |
|------------|-----------------|
| `PluginInterface` | Contrato base para plugins |
| `PluginRegistry` | Descubre, valida y gestiona plugins |
| `ComposerPluginLoader` | Carga plugins desde vendor/ |
| `LocalPluginLoader` | Carga plugins desde .seaman/plugins/ |
| `ConfigSchema` | Define y valida configuración de plugins |
| `PluginConfig` | Acceso tipado a la configuración |

### Distribución Híbrida

- **Composer packages**: Plugins públicos se instalan con `composer require seaman-plugins/X`
- **Directorio local**: Customizaciones del proyecto en `.seaman/plugins/`

Los plugins Composer se descubren por `type: seaman-plugin` en composer.json. Los locales se escanean automáticamente.

## Atributos PHP

### `#[AsSeamanPlugin]`

Marca la clase como plugin. Obligatorio.

```php
#[AsSeamanPlugin(
    name: 'redis-cluster',
    version: '1.0.0',
    description: 'Redis Cluster support for Seaman',
    requires: ['seaman/core:^1.0']
)]
class RedisClusterPlugin implements PluginInterface { }
```

### `#[ProvidesService]`

Registra un servicio Docker.

```php
#[ProvidesService(
    name: 'redis-cluster',
    category: ServiceCategory::Cache
)]
public function redisCluster(): ServiceDefinition
{
    return new ServiceDefinition(
        template: __DIR__ . '/templates/redis-cluster.yaml.twig',
        configParser: RedisClusterConfigParser::class,
        defaultConfig: ['nodes' => 3]
    );
}
```

### `#[ProvidesCommand]`

Añade comando a la CLI.

```php
#[ProvidesCommand]
public function clusterInfo(): Command
{
    return new RedisClusterInfoCommand();
}
```

### `#[OnLifecycle]`

Hook en eventos del ciclo de vida.

```php
#[OnLifecycle(event: 'before:start', priority: 10)]
public function ensureClusterReady(LifecycleEvent $event): void
{
    // Validar configuración antes de start
}
```

Eventos disponibles:
- `before:init`, `after:init`
- `before:start`, `after:start`
- `before:stop`, `after:stop`
- `before:rebuild`, `after:rebuild`
- `before:destroy`, `after:destroy`

### `#[OverridesTemplate]`

Sobrescribe un template del core (opcional, alternativa a convención de carpetas).

```php
#[OverridesTemplate('docker/app.dockerfile.twig')]
public function customDockerfile(): string
{
    return __DIR__ . '/templates/app.dockerfile.twig';
}
```

## Template Overrides

### Por Convención

Los plugins pueden sobrescribir templates colocándolos en la misma ruta relativa:

```
.seaman/plugins/my-company/
├── MyCompanyPlugin.php
└── templates/
    ├── docker/
    │   └── app.dockerfile.twig      # Sobrescribe Dockerfile del core
    └── config/
        └── php.ini.twig              # Sobrescribe php.ini
```

### Herencia de Templates

Un plugin puede extender en vez de reemplazar completamente:

```twig
{# plugins/my-company/templates/docker/app.dockerfile.twig #}
{% extends '@core/docker/app.dockerfile.twig' %}

{% block php_extensions %}
    {{ parent() }}
    RUN install-php-extensions imagick
{% endblock %}
```

Los templates del core deben definir bloques extensibles en puntos estratégicos.

## Configuración de Plugins

### Declaración del Schema

```php
#[AsSeamanPlugin(name: 'redis-cluster')]
class RedisClusterPlugin implements PluginInterface
{
    public function __construct(
        private readonly PluginConfig $config
    ) {}

    public static function configSchema(): ConfigSchema
    {
        return ConfigSchema::create()
            ->integer('nodes', default: 3, min: 3)
            ->integer('replicas_per_master', default: 1)
            ->string('password', default: null)
            ->boolean('persistent', default: true);
    }
}
```

### Uso en seaman.yaml

```yaml
plugins:
  redis-cluster:
    nodes: 6
    replicas_per_master: 2
    password: "${REDIS_PASSWORD}"
```

### Características

- **Validación automática**: Al cargar el plugin se valida contra el schema
- **Variables de entorno**: Soporte para `${VAR}` y `${VAR:-default}`
- **Acceso tipado**: `$this->config->get('nodes')` retorna el tipo correcto

## Plugins Composer

### composer.json del Plugin

```json
{
    "name": "seaman-plugins/redis-cluster",
    "type": "seaman-plugin",
    "description": "Redis Cluster support for Seaman",
    "require": {
        "php": "^8.4",
        "seaman/seaman": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "SeamanPlugins\\RedisCluster\\": "src/"
        }
    },
    "extra": {
        "seaman": {
            "plugin-class": "SeamanPlugins\\RedisCluster\\RedisClusterPlugin"
        }
    }
}
```

### Instalación

```bash
composer require seaman-plugins/redis-cluster
```

## Plugins Locales

### Estructura

```
.seaman/
├── seaman.yaml
└── plugins/
    └── my-project/
        ├── MyProjectPlugin.php
        └── templates/
```

### Plugin Mínimo

```php
<?php

declare(strict_types=1);

namespace Seaman\LocalPlugins\MyProject;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\OnLifecycle;
use Seaman\Plugin\PluginInterface;

#[AsSeamanPlugin(name: 'my-project', version: '1.0.0')]
class MyProjectPlugin implements PluginInterface
{
    #[OnLifecycle(event: 'after:start')]
    public function seedDatabase(): void
    {
        // Ejecutar migrations/seeds después de start
    }
}
```

### Prioridad

Los plugins locales se cargan después de los de Composer, permitiendo sobrescribir comportamiento si es necesario.

## Comandos de Gestión

| Comando | Descripción |
|---------|-------------|
| `seaman plugin:list` | Lista plugins instalados (Composer + locales) |
| `seaman plugin:info <name>` | Muestra detalles, servicios y comandos del plugin |
| `seaman plugin:create <name>` | Genera scaffold para plugin local |

## Refactors Necesarios en el Core

1. **Bloques Twig extensibles**: Añadir bloques en templates del core para permitir extensión
2. **ServiceDefinition**: Extraer servicios actuales a esta abstracción para consistencia
3. **Eventos de lifecycle**: Emitir eventos en comandos principales (init, start, stop, rebuild, destroy)
4. **Namespace de templates**: Registrar templates core bajo `@core/` para permitir herencia

## Orden de Carga

1. Plugins Composer (ordenados por dependencias)
2. Plugins locales (ordenados alfabéticamente)

Dentro de cada grupo, se respeta la prioridad declarada en `#[AsSeamanPlugin]`.
