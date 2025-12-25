# Plugin Autoloader para Composer Plugins - Diseño

## Problema

Cuando Seaman se ejecuta como PHAR, tiene su propio autoloader interno. Los plugins instalados via Composer en el proyecto del usuario no pueden cargarse porque:

1. El PHAR no carga `vendor/autoload.php` del proyecto
2. `class_exists()` en `ComposerPluginLoader` falla para clases de plugins

## Caso de Uso Principal

**Desarrollador de proyecto Symfony** que:
- Tiene Seaman instalado globalmente como PHAR
- Instala plugins de Packagist en su proyecto (`composer require acme/seaman-redis`)
- Espera que Seaman detecte y use esos plugins

## Decisiones de Diseño

1. **Autoloader aislado** (vs cargar vendor/autoload.php completo)
   - Evita conflictos de versiones entre PHAR y proyecto
   - Solo carga lo necesario para plugins

2. **Integración en ComposerPluginLoader** (vs PluginRegistry o servicio DI)
   - Responsabilidad localizada donde se necesita
   - BundledPluginLoader y LocalPluginLoader no lo necesitan

3. **Solo PSR-4** (vs PSR-4 + classmap + files)
   - YAGNI - plugins modernos usan PSR-4
   - Documentar como requisito para plugins de Seaman

## Arquitectura

```
┌─────────────────────────────────────────────────────────────┐
│                    PluginRegistry::discover()                │
└─────────────────────────────────────────────────────────────┘
                              │
         ┌────────────────────┼────────────────────┐
         ▼                    ▼                    ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│ BundledPlugin   │  │ ComposerPlugin  │  │ LocalPlugin     │
│ Loader          │  │ Loader          │  │ Loader          │
│                 │  │                 │  │                 │
│ (PHAR interno)  │  │ ┌─────────────┐ │  │ (.seaman/       │
│                 │  │ │ Plugin      │ │  │  plugins/)      │
│                 │  │ │ Autoloader  │ │  │                 │
└─────────────────┘  │ └─────────────┘ │  └─────────────────┘
                     └─────────────────┘
```

## Componentes

### PluginAutoloader (nueva clase)

**Ubicación**: `src/Plugin/Loader/PluginAutoloader.php`

**Responsabilidades**:
- Construir mapa PSR-4 para plugins y sus dependencias
- Registrar autoloader via `spl_autoload_register()`
- Resolver clases bajo demanda

**Algoritmo de resolución de dependencias**:
1. Empezar con plugins de Seaman como semilla
2. BFS sobre `require` de cada paquete
3. Ignorar dependencias de plataforma (php, ext-*, lib-*)
4. Construir mappings PSR-4 para todos los paquetes relevantes

### ComposerPluginLoader (modificación)

**Cambio**: Registrar `PluginAutoloader` antes de intentar `class_exists()`

```php
public function load(): array
{
    // 1. Parsear installed.json
    // 2. Encontrar paquetes tipo seaman-plugin
    // 3. Registrar PluginAutoloader <-- NUEVO
    // 4. Cargar clases de plugins (ahora class_exists funciona)
}
```

## Flujo de Ejecución

```
Usuario ejecuta: seaman start

1. PluginRegistry::discover()
2. ComposerPluginLoader::load()
   2.1. Leer vendor/composer/installed.json
   2.2. Filtrar paquetes type=seaman-plugin
   2.3. Crear PluginAutoloader
   2.4. autoloader->register(projectRoot, pluginNames, allPackages)
        - Resolver dependencias transitivas
        - Construir mapa PSR-4
        - spl_autoload_register()
   2.5. Para cada plugin: class_exists() → new $className()
3. Plugins cargados y disponibles
```

## Prioridad de Autoloaders

1. Autoloader del PHAR (registrado primero)
2. PluginAutoloader (registrado después)

Si una clase existe en ambos, el PHAR gana. Esto es deseable porque:
- Las dependencias del PHAR son estables y probadas
- Evita que un plugin rompa Seaman con versiones incompatibles

## Limitaciones Conocidas

1. **Solo PSR-4**: Plugins deben usar autoloading PSR-4
2. **Dependencias compartidas**: Si plugin necesita versión específica de librería que PHAR ya tiene, usa la del PHAR
3. **Sin files autoload**: Scripts de inicialización (`files` en composer.json) no se ejecutan

## Testing

- Test unitario de `PluginAutoloader` con mappings mock
- Test de integración con plugin real en vendor/ simulado
- Test de resolución de dependencias transitivas
- Test de prioridad de autoloaders (PHAR vs plugin)
