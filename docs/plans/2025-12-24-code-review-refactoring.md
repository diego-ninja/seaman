# Plan de Refactorizacion - Code Review

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Eliminar duplicacion, simplificar abstracciones innecesarias, y limpiar el enum Service para una arquitectura coherente.

**Architecture:** Consolidar los dos PluginServiceAdapter en uno solo, eliminar interface sin uso real, extraer herencia de comandos a servicios inyectables, y refactorizar enum Service para separar core services de plugin services.

**Tech Stack:** PHP 8.4, PHPStan nivel 10, Pest

---

## Orden de Ejecucion

Las tareas estan ordenadas por dependencias:

1. **Task 1-2**: Eliminar interface PluginExporter (independiente, bajo riesgo)
2. **Task 3-6**: Unificar PluginServiceAdapters (alto impacto, elimina 140 lineas)
3. **Task 7-10**: Extraer AbstractServiceCommand a servicio (simplifica herencia)
4. **Task 11-15**: Refactorizar enum Service (mas complejo, afecta muchos archivos)
5. **Task 16**: Eliminar PluginConfig wrapper (opcional, bajo impacto)

---

## Task 1: Eliminar interface PluginExporter

**Problema:** Interface con unica implementacion (YAGNI)

**Files:**
- Delete: `src/Plugin/Export/PluginExporter.php`
- Modify: `src/Plugin/Export/DefaultPluginExporter.php`
- Modify: `src/Command/PluginExportCommand.php`
- Test: `tests/Unit/Plugin/Export/DefaultPluginExporterTest.php`

**Step 1: Verificar que solo hay una implementacion**

```bash
grep -r "implements PluginExporter" src/
# Expected: Solo DefaultPluginExporter
```

**Step 2: Renombrar DefaultPluginExporter a PluginExporter**

En `src/Plugin/Export/DefaultPluginExporter.php`:

```php
<?php

// ABOUTME: Exports local plugins to distributable Composer packages.
// ABOUTME: Transforms namespaces and generates composer.json files.

declare(strict_types=1);

namespace Seaman\Plugin\Export;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final readonly class PluginExporter  // <-- Renombrar de DefaultPluginExporter
{
    // ... resto del codigo igual, sin "implements PluginExporter"
}
```

**Step 3: Actualizar imports en PluginExportCommand**

Buscar y reemplazar cualquier referencia a `DefaultPluginExporter` por `PluginExporter`.

**Step 4: Eliminar interface**

```bash
rm src/Plugin/Export/PluginExporter.php
```

**Step 5: Actualizar tests**

Renombrar test si es necesario y verificar que no haya mocks de la interface.

**Step 6: Verificar**

```bash
./vendor/bin/phpstan analyse
./vendor/bin/pest
```

**Step 7: Commit**

```bash
git add -A
git commit -m "refactor: remove PluginExporter interface (single implementation)"
```

---

## Task 2: Actualizar DI Container para PluginExporter

**Files:**
- Modify: Archivo de configuracion de servicios (buscar donde se registra)

**Step 1: Buscar registro de servicios**

```bash
grep -r "DefaultPluginExporter" src/
```

**Step 2: Actualizar registro**

Cambiar `DefaultPluginExporter::class` por `PluginExporter::class`.

**Step 3: Verificar**

```bash
./vendor/bin/phpstan analyse
./vendor/bin/pest
```

**Step 4: Commit**

```bash
git add -A
git commit -m "refactor: update DI for renamed PluginExporter"
```

---

## Task 3: Crear test para PluginServiceAdapter unificado

**Problema:** Dos adapters con 90% codigo duplicado

**Files:**
- Create: `tests/Unit/Plugin/PluginServiceAdapterTest.php` (si no existe)

**Step 1: Escribir test para servicio sin database operations**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin;

use PHPUnit\Framework\Attributes\Test;
use Seaman\Contract\DatabaseServiceInterface;
use Seaman\Plugin\PluginServiceAdapter;
use Seaman\Plugin\ServiceDefinition;
use Seaman\Service\Container\ServiceInterface;

#[Test]
public function it_implements_service_interface(): void
{
    $definition = new ServiceDefinition(
        name: 'test-service',
        description: 'Test service',
        icon: '🧪',
        template: 'test.yaml.twig',
        ports: [8080],
    );

    $adapter = new PluginServiceAdapter($definition);

    expect($adapter)->toBeInstanceOf(ServiceInterface::class);
    expect($adapter)->not->toBeInstanceOf(DatabaseServiceInterface::class);
}
```

**Step 2: Escribir test para servicio CON database operations**

```php
#[Test]
public function it_implements_database_interface_when_definition_has_database_operations(): void
{
    $dbOps = new DatabaseOperations(/* ... */);
    $definition = new ServiceDefinition(
        name: 'mysql',
        description: 'MySQL',
        icon: '🐬',
        template: 'mysql.yaml.twig',
        ports: [3306],
        databaseOperations: $dbOps,
    );

    $adapter = new PluginServiceAdapter($definition);

    expect($adapter)->toBeInstanceOf(ServiceInterface::class);
    expect($adapter)->toBeInstanceOf(DatabaseServiceInterface::class);
}
```

**Step 3: Escribir test para metodos de database cuando no hay operations**

```php
#[Test]
public function it_throws_when_calling_database_methods_without_operations(): void
{
    $definition = new ServiceDefinition(
        name: 'redis',
        description: 'Redis',
        icon: '🧵',
        template: 'redis.yaml.twig',
        ports: [6379],
        // Sin databaseOperations
    );

    $adapter = new PluginServiceAdapter($definition);

    expect(fn() => $adapter->getDumpCommand($config))
        ->toThrow(\LogicException::class, "does not support database operations");
}
```

**Step 4: Run tests (should fail)**

```bash
./vendor/bin/pest tests/Unit/Plugin/PluginServiceAdapterTest.php
# Expected: FAIL - clase no tiene la nueva logica
```

---

## Task 4: Unificar PluginServiceAdapter y PluginDatabaseServiceAdapter

**Files:**
- Modify: `src/Plugin/PluginServiceAdapter.php`
- Delete: `src/Plugin/PluginDatabaseServiceAdapter.php`

**Step 1: Modificar PluginServiceAdapter para implementar ambas interfaces**

```php
<?php

// ABOUTME: Adapts a plugin ServiceDefinition to ServiceInterface and optionally DatabaseServiceInterface.
// ABOUTME: Single adapter for all plugin services, delegating database operations when available.

declare(strict_types=1);

namespace Seaman\Plugin;

use Seaman\Contract\DatabaseServiceInterface;
use Seaman\Enum\Service;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Service\Container\ServiceInterface;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\ServiceConfig;

final readonly class PluginServiceAdapter implements ServiceInterface, DatabaseServiceInterface
{
    public function __construct(
        private ServiceDefinition $definition,
    ) {}

    public function getType(): Service
    {
        foreach (Service::cases() as $case) {
            if ($case->value === $this->definition->name) {
                return $case;
            }
        }

        return Service::Custom;
    }

    public function getName(): string
    {
        return $this->definition->name;
    }

    public function getDisplayName(): string
    {
        return $this->definition->getDisplayName();
    }

    public function getDescription(): string
    {
        return $this->definition->description;
    }

    public function getIcon(): string
    {
        return $this->definition->icon;
    }

    /**
     * @return list<string>
     */
    public function getDependencies(): array
    {
        return $this->definition->dependencies;
    }

    public function getDefaultConfig(): ServiceConfig
    {
        /** @var string $version */
        $version = $this->definition->defaultConfig['version'] ?? 'latest';
        /** @var array<string, string> $environment */
        $environment = $this->definition->defaultConfig['environment'] ?? [];

        $port = $this->definition->ports[0] ?? 0;
        $additionalPorts = array_slice($this->definition->ports, 1);

        return new ServiceConfig(
            name: $this->definition->name,
            enabled: true,
            type: $this->getType(),
            version: $version,
            port: $port,
            additionalPorts: $additionalPorts,
            environmentVariables: $environment,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        return [
            '__template_path' => $this->definition->template,
        ];
    }

    /**
     * @return list<int>
     */
    public function getRequiredPorts(): array
    {
        return $this->definition->ports;
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return $this->definition->healthCheck;
    }

    /**
     * @return array<string, string|int>
     */
    public function getEnvVariables(ServiceConfig $config): array
    {
        $envVars = $config->environmentVariables;

        $portVarName = strtoupper($this->definition->name) . '_PORT';

        if (in_array($this->definition->name, ['mysql', 'postgresql', 'mariadb', 'sqlite'], true)) {
            $envVars['DB_PORT'] = $config->port;
        }

        $envVars[$portVarName] = $config->port;

        return $envVars;
    }

    /**
     * @return list<int>
     */
    public function getInternalPorts(): array
    {
        return $this->definition->internalPorts;
    }

    public function getInspectInfo(ServiceConfig $config): string
    {
        return "v{$config->version}";
    }

    public function getConfigSchema(): ?ConfigSchema
    {
        return $this->definition->configSchema;
    }

    // --- DatabaseServiceInterface methods ---

    public function supportsDatabaseOperations(): bool
    {
        return $this->definition->databaseOperations !== null;
    }

    /**
     * @return list<string>
     * @throws \LogicException When service does not support database operations
     */
    public function getDumpCommand(ServiceConfig $config): array
    {
        $this->ensureDatabaseOperationsSupported();

        return $this->definition->databaseOperations->getDumpCommand($config);
    }

    /**
     * @return list<string>
     * @throws \LogicException When service does not support database operations
     */
    public function getRestoreCommand(ServiceConfig $config): array
    {
        $this->ensureDatabaseOperationsSupported();

        return $this->definition->databaseOperations->getRestoreCommand($config);
    }

    /**
     * @return list<string>
     * @throws \LogicException When service does not support database operations
     */
    public function getShellCommand(ServiceConfig $config): array
    {
        $this->ensureDatabaseOperationsSupported();

        return $this->definition->databaseOperations->getShellCommand($config);
    }

    /**
     * @throws \LogicException
     */
    private function ensureDatabaseOperationsSupported(): void
    {
        if ($this->definition->databaseOperations === null) {
            throw new \LogicException(
                sprintf("Service '%s' does not support database operations", $this->definition->name),
            );
        }
    }
}
```

**Step 2: Eliminar PluginDatabaseServiceAdapter**

```bash
rm src/Plugin/PluginDatabaseServiceAdapter.php
```

**Step 3: Run tests**

```bash
./vendor/bin/pest tests/Unit/Plugin/PluginServiceAdapterTest.php
# Expected: PASS
```

---

## Task 5: Actualizar codigo que usaba PluginDatabaseServiceAdapter

**Files:**
- Buscar y modificar todos los usos

**Step 1: Buscar usos**

```bash
grep -r "PluginDatabaseServiceAdapter" src/ tests/
```

**Step 2: Reemplazar con PluginServiceAdapter**

El codigo que instanciaba `PluginDatabaseServiceAdapter` ahora usa `PluginServiceAdapter` directamente. La logica de decision se basa en `supportsDatabaseOperations()`.

**Step 3: Actualizar PluginLoader o similar**

Buscar donde se decide que adapter usar:

```php
// ANTES:
if ($definition->databaseOperations !== null) {
    return new PluginDatabaseServiceAdapter($definition);
}
return new PluginServiceAdapter($definition);

// DESPUES:
return new PluginServiceAdapter($definition);
```

**Step 4: Verificar**

```bash
./vendor/bin/phpstan analyse
./vendor/bin/pest
```

**Step 5: Commit**

```bash
git add -A
git commit -m "refactor: unify PluginServiceAdapter, remove PluginDatabaseServiceAdapter"
```

---

## Task 6: Agregar metodo supportsDatabaseOperations a DatabaseServiceInterface

**Files:**
- Modify: `src/Contract/DatabaseServiceInterface.php`

**Step 1: Verificar interface actual**

```bash
cat src/Contract/DatabaseServiceInterface.php
```

**Step 2: Agregar metodo si no existe**

```php
interface DatabaseServiceInterface
{
    public function supportsDatabaseOperations(): bool;

    // ... otros metodos
}
```

**Step 3: Actualizar implementaciones existentes**

Todas las implementaciones de `DatabaseServiceInterface` deben implementar `supportsDatabaseOperations()`. Para servicios core, retornar `true`.

**Step 4: Verificar**

```bash
./vendor/bin/phpstan analyse
./vendor/bin/pest
```

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add supportsDatabaseOperations to DatabaseServiceInterface"
```

---

## Task 7: Crear servicio ComposeRegenerator

**Problema:** AbstractServiceCommand tiene herencia para reusar 2 metodos

**Files:**
- Create: `src/Service/ComposeRegenerator.php`
- Test: `tests/Unit/Service/ComposeRegeneratorTest.php`

**Step 1: Escribir test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Seaman\Service\ComposeRegenerator;
use Seaman\ValueObject\Configuration;

test('it regenerates docker-compose.yml', function () {
    // Arrange
    $config = createTestConfiguration();
    $regenerator = new ComposeRegenerator(/* dependencies */);

    // Act
    $regenerator->regenerate($config, '/tmp/test-project');

    // Assert
    expect(file_exists('/tmp/test-project/docker-compose.yml'))->toBeTrue();
});
```

**Step 2: Run test (should fail)**

```bash
./vendor/bin/pest tests/Unit/Service/ComposeRegeneratorTest.php
```

**Step 3: Implementar ComposeRegenerator**

```php
<?php

// ABOUTME: Regenerates docker-compose.yml and optionally restarts services.
// ABOUTME: Extracted from AbstractServiceCommand for better composition.

declare(strict_types=1);

namespace Seaman\Service;

use Seaman\Service\Generator\DockerComposeGenerator;
use Seaman\UI\Prompts;
use Seaman\UI\Terminal;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ProcessResult;

final readonly class ComposeRegenerator
{
    public function __construct(
        private DockerComposeGenerator $composeGenerator,
        private DockerManager $dockerManager,
    ) {}

    public function regenerate(Configuration $config, string $projectRoot): void
    {
        $composeYaml = $this->composeGenerator->generate($config);
        file_put_contents($projectRoot . '/docker-compose.yml', $composeYaml);

        Terminal::success('Services updated successfully.');
    }

    public function restartIfConfirmed(): ProcessResult
    {
        if (!Prompts::confirm(label: 'Restart seaman stack with new services?')) {
            return new ProcessResult(0, '', '');
        }

        $downResult = $this->dockerManager->down();
        if (!$downResult->isSuccessful()) {
            Terminal::error('Failed to stop services');
            Terminal::output()->writeln($downResult->errorOutput);
            return $downResult;
        }

        $startResult = $this->dockerManager->start();
        if (!$startResult->isSuccessful()) {
            Terminal::error('Failed to start services');
            Terminal::output()->writeln($startResult->errorOutput);
            return $startResult;
        }

        Terminal::success('Stack restarted successfully.');
        return $startResult;
    }
}
```

**Step 4: Run test**

```bash
./vendor/bin/pest tests/Unit/Service/ComposeRegeneratorTest.php
# Expected: PASS
```

**Step 5: Commit**

```bash
git add -A
git commit -m "feat: add ComposeRegenerator service"
```

---

## Task 8: Refactorizar ServiceAddCommand para usar ComposeRegenerator

**Files:**
- Modify: `src/Command/ServiceAddCommand.php`

**Step 1: Inyectar ComposeRegenerator**

```php
final class ServiceAddCommand extends ModeAwareCommand  // <-- Ya no extiende AbstractServiceCommand
{
    public function __construct(
        private ComposeRegenerator $regenerator,
        // ... otros
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // ... logica existente ...

        $projectRoot = (string) getcwd();
        $this->regenerator->regenerate($config, $projectRoot);

        $result = $this->regenerator->restartIfConfirmed();

        return $result->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}
```

**Step 2: Verificar**

```bash
./vendor/bin/phpstan analyse
./vendor/bin/pest
```

**Step 3: Commit**

```bash
git add -A
git commit -m "refactor: ServiceAddCommand uses ComposeRegenerator"
```

---

## Task 9: Refactorizar ServiceRemoveCommand

**Files:**
- Modify: `src/Command/ServiceRemoveCommand.php`

**Step 1: Aplicar mismo patron que ServiceAddCommand**

Inyectar `ComposeRegenerator` y eliminar herencia de `AbstractServiceCommand`.

**Step 2: Verificar**

```bash
./vendor/bin/phpstan analyse
./vendor/bin/pest
```

**Step 3: Commit**

```bash
git add -A
git commit -m "refactor: ServiceRemoveCommand uses ComposeRegenerator"
```

---

## Task 10: Eliminar AbstractServiceCommand

**Files:**
- Delete: `src/Command/AbstractServiceCommand.php`

**Step 1: Verificar que nadie mas lo usa**

```bash
grep -r "AbstractServiceCommand" src/
# Expected: Ningun resultado
```

**Step 2: Eliminar**

```bash
rm src/Command/AbstractServiceCommand.php
```

**Step 3: Verificar**

```bash
./vendor/bin/phpstan analyse
./vendor/bin/pest
```

**Step 4: Commit**

```bash
git add -A
git commit -m "refactor: remove AbstractServiceCommand (replaced by ComposeRegenerator)"
```

---

## Task 11: Limpiar enum Service - Eliminar Service::None

**Problema:** Service::None lanza excepcion en description()

**Files:**
- Modify: `src/Enum/Service.php`

**Step 1: Buscar usos de Service::None**

```bash
grep -r "Service::None" src/ tests/
grep -r "none" src/Enum/Service.php
```

**Step 2: Eliminar caso None**

```php
enum Service: string
{
    case App = 'app';
    case Traefik = 'traefik';
    // ... otros ...
    case Custom = 'custom';
    // ELIMINAR: case None = 'none';
}
```

**Step 3: Eliminar de metodos**

```php
public function description(): string
{
    return match ($this) {
        // ELIMINAR: self::None => throw new \Exception('To be implemented'),
        // ... resto igual
    };
}

public static function services(): array
{
    return [
        // ELIMINAR: self::None->value,
        // ... resto igual
    ];
}
```

**Step 4: Actualizar codigo que usaba None**

Buscar y reemplazar con logica apropiada (probablemente nullable o vacio).

**Step 5: Verificar**

```bash
./vendor/bin/phpstan analyse
./vendor/bin/pest
```

**Step 6: Commit**

```bash
git add -A
git commit -m "refactor: remove Service::None (unused zombie case)"
```

---

## Task 12: Mover metadata de Service enum a ServiceDefinition

**Problema:** Enum con 100+ lineas de metadata que ya existe en plugins

**Files:**
- Modify: `src/Enum/Service.php`
- Modify: Lugares que usan `Service::X->description()`, `Service::X->port()`, `Service::X->icon()`

**Step 1: Identificar todos los usos de metadata del enum**

```bash
grep -r "->description()" src/ | grep Service
grep -r "->port()" src/ | grep Service
grep -r "->icon()" src/ | grep Service
```

**Step 2: Evaluar impacto**

Este es un cambio de alto impacto. Para cada uso, determinar si puede obtener la metadata de `ServiceRegistry->get($service)` en lugar del enum.

**Step 3: Refactorizar usos gradualmente**

```php
// ANTES:
$description = $service->description();

// DESPUES:
$serviceInterface = $serviceRegistry->get($service->value);
$description = $serviceInterface->getDescription();
```

**Step 4: Deprecar metodos en enum (opcional)**

```php
/**
 * @deprecated Use ServiceRegistry->get()->getDescription() instead
 */
public function description(): string
{
    // ...
}
```

**Step 5: Verificar**

```bash
./vendor/bin/phpstan analyse
./vendor/bin/pest
```

**Step 6: Commit**

```bash
git add -A
git commit -m "refactor: migrate Service metadata access to ServiceRegistry"
```

---

## Task 13: Reducir casos de Service enum a core services

**Problema:** Enum mezcla core services con plugin services

**Files:**
- Modify: `src/Enum/Service.php`

**Step 1: Identificar servicios core vs plugin**

Core (no deberían ser plugins):
- `App` - la aplicacion
- `Traefik` - reverse proxy requerido

Todo lo demas son plugins bundled (MySQL, Redis, etc.)

**Step 2: Evaluar viabilidad**

Este cambio es MUY invasivo. Requiere:
1. Que `getType()` de adapters retorne `?Service` o un tipo diferente
2. Actualizar TODOS los lugares que hacen `match` sobre Service
3. Actualizar configuracion de servicios

**Recomendacion:** Este cambio es candidato para una rama separada despues de estabilizar los cambios anteriores.

**Step 3: Documentar decision**

Crear issue o nota para futuro refactor.

---

## Task 14: Simplificar getType() en PluginServiceAdapter

**Problema:** getType() busca en enum, pero deberia retornar Service::Custom para plugins

**Files:**
- Modify: `src/Plugin/PluginServiceAdapter.php`

**Step 1: Evaluar si realmente necesita buscar en enum**

El proposito de buscar es: si el plugin se llama "mysql", retornar `Service::MySQL`.

**Opcion A:** Mantener comportamiento actual (plugins bundled usan enum existente)

**Opcion B:** Siempre retornar `Service::Custom` para plugins

```php
public function getType(): Service
{
    return Service::Custom;
}
```

**Opcion C:** Retornar nullable

```php
public function getType(): ?Service
{
    foreach (Service::cases() as $case) {
        if ($case->value === $this->definition->name) {
            return $case;
        }
    }
    return null;  // No es un servicio core
}
```

**Recomendacion:** Opcion A por ahora. Cambiar a Opcion C cuando se refactorice el enum completamente (Task 13).

---

## Task 15: Agregar validacion en CI

**Files:**
- Create/Modify: `.github/workflows/ci.yml`

**Step 1: Verificar workflow existente**

```bash
cat .github/workflows/*.yml
```

**Step 2: Agregar checks**

```yaml
jobs:
  quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'

      - name: Install dependencies
        run: composer install

      - name: Run PHPStan
        run: ./vendor/bin/phpstan analyse

      - name: Check code style
        run: ./vendor/bin/php-cs-fixer fix --dry-run --diff

      - name: Run tests with coverage
        run: ./vendor/bin/pest --coverage --min=95
```

**Step 3: Commit**

```bash
git add -A
git commit -m "ci: add quality checks (PHPStan, cs-fixer, coverage)"
```

---

## Task 16: Eliminar PluginConfig wrapper (Opcional)

**Problema:** Wrapper sobre array sin comportamiento adicional

**Files:**
- Delete: `src/Plugin/Config/PluginConfig.php`
- Modify: Usos de PluginConfig

**Step 1: Buscar usos**

```bash
grep -r "PluginConfig" src/ tests/
```

**Step 2: Evaluar impacto**

Si hay pocos usos, reemplazar con `array<string, mixed>` tipado.

**Step 3: Decisión**

Este cambio es de bajo impacto. Puede hacerse o dejarse para despues. El wrapper no causa problemas, solo es innecesario.

---

## Resumen de Commits Esperados

1. `refactor: remove PluginExporter interface (single implementation)`
2. `refactor: update DI for renamed PluginExporter`
3. `refactor: unify PluginServiceAdapter, remove PluginDatabaseServiceAdapter`
4. `feat: add supportsDatabaseOperations to DatabaseServiceInterface`
5. `feat: add ComposeRegenerator service`
6. `refactor: ServiceAddCommand uses ComposeRegenerator`
7. `refactor: ServiceRemoveCommand uses ComposeRegenerator`
8. `refactor: remove AbstractServiceCommand (replaced by ComposeRegenerator)`
9. `refactor: remove Service::None (unused zombie case)`
10. `refactor: migrate Service metadata access to ServiceRegistry`
11. `ci: add quality checks (PHPStan, cs-fixer, coverage)`

---

## Verificacion Final

Al completar todas las tareas:

```bash
./vendor/bin/phpstan analyse
./vendor/bin/php-cs-fixer fix
./vendor/bin/pest --coverage
```

Todas deben pasar sin errores.

---

## Notas de Ejecucion

### Task 11 - Eliminar Service::None ✅ COMPLETADO

**Fecha:** 2025-12-24
**Commit:** `201a3a5` - refactor: remove Service::None zombie enum case

**Impacto:**
- 6 archivos de producción modificados
- 6 archivos de tests modificados
- 17 usos de Service::None eliminados
- Cambio de Service a ?Service en 3 firmas de métodos

**Resultado:** Todos los tests pasan (695 unit tests), PHPStan nivel 10 sin errores.

---

### Tasks 12-14 - Metadata del Enum ⏸️ POSPUESTO

**Fecha:** 2025-12-24
**Análisis:**

Ejecuté búsqueda exhaustiva de usos de métodos de metadata del enum:
```bash
grep -r "->description()" src/ tests/
grep -r "->port()" src/ tests/
grep -r "->icon()" src/ tests/
```

**Resultado:** CERO usos en código de producción (src/).

Los métodos `description()`, `port()`, e `icon()` del enum Service existen pero NO se usan en ningún lugar del código de producción. Solo están definidos en el enum.

**Conclusión:**
- No hay migración que hacer - los métodos ya están sin usar
- Los métodos pueden marcarse como deprecated o eliminarse directamente
- Service::Custom ya tiene metadata genérica ('Custom plugin-provided service')
- El enum actualmente tiene 148 líneas, de las cuales ~100 son metadata no utilizada

**Recomendación:**
Eliminar directamente los métodos `description()`, `port()`, e `icon()` del enum Service en una futura PR de limpieza. No hay código que romper porque nadie los usa.

**Siguiente paso sugerido:**
Task 13 (reducir casos de Service enum a core services) es MUY invasivo y debe evaluarse con cuidado. Podría ser mejor abordarlo en una rama separada después de estabilizar el sistema de plugins.
