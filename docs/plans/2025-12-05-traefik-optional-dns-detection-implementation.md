# Traefik Opcional y Detección Inteligente de DNS - Plan de Implementación

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Hacer Traefik opcional durante la inicialización con comandos de toggle, y añadir detección inteligente de múltiples proveedores DNS.

**Architecture:** Se modifica el flujo de inicialización para preguntar sobre proxy, se condiciona la generación de docker-compose según el estado del proxy, y se añaden nuevos comandos para toggle. Para DNS se crea un enum de proveedores con prioridades y se refactoriza DnsConfigurationHelper para detectar múltiples opciones.

**Tech Stack:** PHP 8.4, Symfony Console, Pest PHP

---

## Fase 1: Traefik Opcional - Value Objects y Configuración

### Task 1: Añadir factory method disabled() a ProxyConfig

**Files:**
- Modify: `src/ValueObject/ProxyConfig.php`
- Test: `tests/Unit/ValueObject/ProxyConfigTest.php`

**Step 1: Write the failing test**

Añadir al final de `tests/Unit/ValueObject/ProxyConfigTest.php`:

```php
test('creates disabled ProxyConfig', function () {
    $config = ProxyConfig::disabled();

    expect($config->enabled)->toBeFalse()
        ->and($config->domainPrefix)->toBe('')
        ->and($config->certResolver)->toBe('')
        ->and($config->dashboard)->toBeFalse();
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ValueObject/ProxyConfigTest.php --filter="creates disabled ProxyConfig"`
Expected: FAIL - Call to undefined method ProxyConfig::disabled()

**Step 3: Write minimal implementation**

Añadir a `src/ValueObject/ProxyConfig.php` después del método `default()`:

```php
/**
 * Create disabled proxy configuration.
 */
public static function disabled(): self
{
    return new self(
        enabled: false,
        domainPrefix: '',
        certResolver: '',
        dashboard: false,
    );
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ValueObject/ProxyConfigTest.php --filter="creates disabled ProxyConfig"`
Expected: PASS

**Step 5: Commit**

```bash
git add src/ValueObject/ProxyConfig.php tests/Unit/ValueObject/ProxyConfigTest.php
git commit -m "feat(proxy): add disabled() factory to ProxyConfig"
```

---

### Task 2: Añadir campo useProxy a InitializationChoices

**Files:**
- Modify: `src/ValueObject/InitializationChoices.php`
- Create: `tests/Unit/ValueObject/InitializationChoicesTest.php`

**Step 1: Write the failing test**

Crear `tests/Unit/ValueObject/InitializationChoicesTest.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for InitializationChoices value object.
// ABOUTME: Validates all initialization choice properties including useProxy.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\Service;
use Seaman\ValueObject\InitializationChoices;
use Seaman\ValueObject\XdebugConfig;

test('creates InitializationChoices with all properties including useProxy', function () {
    $xdebug = XdebugConfig::default();

    $choices = new InitializationChoices(
        projectName: 'myproject',
        phpVersion: PhpVersion::Php84,
        database: Service::PostgreSQL,
        services: [Service::Redis, Service::Mailpit],
        xdebug: $xdebug,
        generateDevContainer: true,
        useProxy: true,
    );

    expect($choices->projectName)->toBe('myproject')
        ->and($choices->phpVersion)->toBe(PhpVersion::Php84)
        ->and($choices->database)->toBe(Service::PostgreSQL)
        ->and($choices->services)->toBe([Service::Redis, Service::Mailpit])
        ->and($choices->xdebug)->toBe($xdebug)
        ->and($choices->generateDevContainer)->toBeTrue()
        ->and($choices->useProxy)->toBeTrue();
});

test('InitializationChoices useProxy defaults to true', function () {
    $xdebug = XdebugConfig::default();

    $choices = new InitializationChoices(
        projectName: 'myproject',
        phpVersion: PhpVersion::Php84,
        database: Service::PostgreSQL,
        services: [],
        xdebug: $xdebug,
        generateDevContainer: false,
    );

    expect($choices->useProxy)->toBeTrue();
});

test('InitializationChoices is immutable', function () {
    $xdebug = XdebugConfig::default();

    $choices = new InitializationChoices(
        projectName: 'myproject',
        phpVersion: PhpVersion::Php84,
        database: Service::PostgreSQL,
        services: [],
        xdebug: $xdebug,
        generateDevContainer: false,
    );

    $reflection = new \ReflectionClass($choices);
    expect($reflection->isReadOnly())->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ValueObject/InitializationChoicesTest.php`
Expected: FAIL - Too many arguments / Unknown named parameter useProxy

**Step 3: Write minimal implementation**

Modificar `src/ValueObject/InitializationChoices.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Value object containing user choices during initialization.
// ABOUTME: Used to pass configuration selections between services.

namespace Seaman\ValueObject;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\Service;

final readonly class InitializationChoices
{
    /**
     * @param list<Service> $services
     */
    public function __construct(
        public string $projectName,
        public PhpVersion $phpVersion,
        public Service $database,
        public array $services,
        public XdebugConfig $xdebug,
        public bool $generateDevContainer,
        public bool $useProxy = true,
    ) {}
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ValueObject/InitializationChoicesTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/ValueObject/InitializationChoices.php tests/Unit/ValueObject/InitializationChoicesTest.php
git commit -m "feat(proxy): add useProxy field to InitializationChoices"
```

---

### Task 3: Añadir pregunta de proxy a InitializationWizard

**Files:**
- Modify: `src/Service/InitializationWizard.php`
- Modify: `tests/Unit/Service/InitializationWizardTest.php`

**Step 1: Write the failing test**

Añadir al final de `tests/Unit/Service/InitializationWizardTest.php`:

```php
test('shouldUseProxy returns true by default', function () {
    $detector = new PhpVersionDetector();
    $wizard = new InitializationWizard($detector);

    // El método debería existir y devolver true por defecto en contexto no-interactivo
    expect(method_exists($wizard, 'shouldUseProxy'))->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/InitializationWizardTest.php --filter="shouldUseProxy"`
Expected: FAIL - method shouldUseProxy does not exist

**Step 3: Write minimal implementation**

Añadir a `src/Service/InitializationWizard.php` después del método `enableDevContainer()`:

```php
/**
 * Ask if user wants to use Traefik proxy.
 */
public function shouldUseProxy(): bool
{
    return confirm(
        label: 'Use Traefik as reverse proxy?',
        default: true,
        hint: 'Enables HTTPS and local domains (app.project.local). Disable for direct port access.',
    );
}
```

**Step 4: Modificar el método run() para incluir useProxy**

Modificar el método `run()` en `src/Service/InitializationWizard.php`:

```php
public function run(InputInterface $input, ProjectType $projectType, string $projectRoot): InitializationChoices
{
    $projectName = basename($projectRoot);
    $phpVersion = $this->selectPhpVersion($projectRoot);
    $database = $this->selectDatabase();
    $services = $this->selectServices($projectType);
    $xdebug = $this->enableXdebug();
    $useProxy = $this->shouldUseProxy();
    $devContainer = $this->enableDevContainer($input);

    return new InitializationChoices(
        projectName: $projectName,
        phpVersion: $phpVersion,
        database: $database,
        services: $services,
        xdebug: $xdebug,
        generateDevContainer: $devContainer,
        useProxy: $useProxy,
    );
}
```

**Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/InitializationWizardTest.php --filter="shouldUseProxy"`
Expected: PASS

**Step 6: Commit**

```bash
git add src/Service/InitializationWizard.php tests/Unit/Service/InitializationWizardTest.php
git commit -m "feat(proxy): add proxy question to InitializationWizard"
```

---

### Task 4: Modificar ConfigurationFactory para usar useProxy

**Files:**
- Modify: `src/Service/ConfigurationFactory.php`
- Create: `tests/Unit/Service/ConfigurationFactoryProxyTest.php`

**Step 1: Write the failing test**

Crear `tests/Unit/Service/ConfigurationFactoryProxyTest.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for ConfigurationFactory proxy handling.
// ABOUTME: Validates ProxyConfig creation based on useProxy choice.

namespace Seaman\Tests\Unit\Service;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;
use Seaman\Service\ConfigurationFactory;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\ValueObject\InitializationChoices;
use Seaman\ValueObject\XdebugConfig;

test('creates Configuration with enabled proxy when useProxy is true', function () {
    $registry = ServiceRegistry::create();
    $factory = new ConfigurationFactory($registry);

    $choices = new InitializationChoices(
        projectName: 'myproject',
        phpVersion: PhpVersion::Php84,
        database: Service::PostgreSQL,
        services: [],
        xdebug: XdebugConfig::default(),
        generateDevContainer: false,
        useProxy: true,
    );

    $config = $factory->createFromChoices($choices, ProjectType::Existing);

    expect($config->proxy()->enabled)->toBeTrue()
        ->and($config->proxy()->domainPrefix)->toBe('myproject');
});

test('creates Configuration with disabled proxy when useProxy is false', function () {
    $registry = ServiceRegistry::create();
    $factory = new ConfigurationFactory($registry);

    $choices = new InitializationChoices(
        projectName: 'myproject',
        phpVersion: PhpVersion::Php84,
        database: Service::PostgreSQL,
        services: [],
        xdebug: XdebugConfig::default(),
        generateDevContainer: false,
        useProxy: false,
    );

    $config = $factory->createFromChoices($choices, ProjectType::Existing);

    expect($config->proxy()->enabled)->toBeFalse();
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/ConfigurationFactoryProxyTest.php`
Expected: First test passes (default behavior), second test FAILS (proxy always enabled)

**Step 3: Write minimal implementation**

Modificar `src/Service/ConfigurationFactory.php`:

1. Añadir import al inicio:
```php
use Seaman\ValueObject\ProxyConfig;
```

2. Modificar el método `createFromChoices()`:

```php
public function createFromChoices(
    InitializationChoices $choices,
    ProjectType $projectType,
): Configuration {
    $php = new PhpConfig($choices->phpVersion, $choices->xdebug);

    $serviceConfigs = $this->buildServiceConfigs($choices->database, $choices->services);
    $persistVolumes = $this->determinePersistVolumes($choices->database, $choices->services);

    $proxy = $choices->useProxy
        ? ProxyConfig::default($choices->projectName)
        : ProxyConfig::disabled();

    return new Configuration(
        projectName: $choices->projectName,
        version: '1.0',
        php: $php,
        services: new ServiceCollection($serviceConfigs),
        volumes: new VolumeConfig($persistVolumes),
        projectType: $projectType,
        proxy: $proxy,
    );
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/ConfigurationFactoryProxyTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Service/ConfigurationFactory.php tests/Unit/Service/ConfigurationFactoryProxyTest.php
git commit -m "feat(proxy): handle useProxy in ConfigurationFactory"
```

---

## Fase 2: Traefik Opcional - Generación de Docker Compose

### Task 5: Modificar DockerComposeGenerator para condicionar proxy

**Files:**
- Modify: `src/Service/DockerComposeGenerator.php`
- Modify: `tests/Unit/Service/DockerComposeGeneratorTest.php`

**Step 1: Write the failing test**

Añadir a `tests/Unit/Service/DockerComposeGeneratorTest.php`:

```php
test('generates docker-compose without traefik labels when proxy disabled', function () {
    $templateDir = __DIR__ . '/../../../src/Template';
    $renderer = new TemplateRenderer($templateDir);
    $labelGenerator = new TraefikLabelGenerator();
    $generator = new DockerComposeGenerator($renderer, $labelGenerator);

    $config = new Configuration(
        projectName: 'testproject',
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, XdebugConfig::default()),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        projectType: ProjectType::Existing,
        proxy: ProxyConfig::disabled(),
    );

    $yaml = $generator->generate($config);

    expect($yaml)->not->toContain('traefik.enable=true')
        ->and($yaml)->toContain('ports:');
});

test('generates docker-compose with traefik labels when proxy enabled', function () {
    $templateDir = __DIR__ . '/../../../src/Template';
    $renderer = new TemplateRenderer($templateDir);
    $labelGenerator = new TraefikLabelGenerator();
    $generator = new DockerComposeGenerator($renderer, $labelGenerator);

    $config = new Configuration(
        projectName: 'testproject',
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, XdebugConfig::default()),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        projectType: ProjectType::Existing,
        proxy: ProxyConfig::default('testproject'),
    );

    $yaml = $generator->generate($config);

    expect($yaml)->toContain('traefik.enable=true');
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/DockerComposeGeneratorTest.php --filter="without traefik labels when proxy disabled"`
Expected: FAIL - yaml contains traefik.enable=true

**Step 3: Write minimal implementation**

Modificar `src/Service/DockerComposeGenerator.php`:

```php
public function generate(Configuration $config): string
{
    $enabledServices = $config->services->enabled();
    $proxy = $config->proxy();
    $proxyEnabled = $proxy->enabled;

    // Generate Traefik labels for all enabled services (only if proxy enabled)
    $servicesWithLabels = [];
    foreach ($enabledServices as $name => $service) {
        $labels = $proxyEnabled
            ? $this->labelGenerator->generateLabels($service, $proxy)
            : [];
        $servicesWithLabels[$name] = [
            'config' => $service,
            'labels' => $labels,
        ];
    }

    // Generate Traefik labels for app service (only if proxy enabled)
    $appService = $this->createAppServiceConfig($config);
    $appLabels = $proxyEnabled
        ? $this->labelGenerator->generateLabels($appService, $proxy)
        : [];

    $context = [
        'php_version' => $config->php->version->value,
        'app_labels' => $appLabels,
        'services' => [
            'enabled' => $servicesWithLabels,
        ],
        'volumes' => $config->volumes,
        'project_name' => $config->projectName,
        'proxy_enabled' => $proxyEnabled,
    ];

    $baseYaml = $this->renderer->render('docker/compose.base.twig', $context);

    // Merge custom services if present
    if ($config->hasCustomServices()) {
        return $this->mergeCustomServices($baseYaml, $config->customServices);
    }

    return $baseYaml;
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/DockerComposeGeneratorTest.php --filter="proxy"`
Expected: FAIL todavía (necesitamos modificar el template)

**Step 5: Commit parcial**

```bash
git add src/Service/DockerComposeGenerator.php
git commit -m "feat(proxy): pass proxy_enabled flag to templates"
```

---

### Task 6: Modificar template compose.base.twig para condicionar proxy

**Files:**
- Modify: `src/Template/docker/compose.base.twig`

**Step 1: Modificar el template**

Reemplazar contenido de `src/Template/docker/compose.base.twig`:

```twig
services:
  app:
    image: seaman/seaman-php{{ php_version }}:latest
    container_name: seaman-app
    build:
      context: .
      dockerfile: .seaman/Dockerfile
      args:
        WWWGROUP: ${WWWGROUP:-1000}
        PHP_VERSION: ${PHP_VERSION:-{{ php_version }}}
    volumes:
      - .:/var/www/html
      - .seaman/scripts/xdebug-toggle.sh:/usr/local/bin/xdebug-toggle
    environment:
      - XDEBUG_MODE=${XDEBUG_MODE:-off}
      - PHP_IDE_CONFIG=serverName=seaman
{% if not proxy_enabled %}
    ports:
      - "${APP_PORT:-80}:80"
{% endif %}
{% if services.enabled|length > 0 %}
    depends_on:
{% for name, service in services.enabled %}
      - {{ name }}
{% endfor %}
{% endif %}
{% if proxy_enabled and app_labels|length > 0 %}
    labels:
{% for label in app_labels %}
      - "{{ label }}"
{% endfor %}
{% endif %}
    networks:
      - seaman

{% for name, service in services.enabled %}
{% include 'docker/services/' ~ service.config.type.value ~ '.twig' with { name: name, service: service.config, labels: service.labels, proxy_enabled: proxy_enabled, project_name: project_name } %}

{% endfor %}
networks:
  seaman:
    driver: bridge

{% if volumes.persist|length > 0 %}
volumes:
{% for volume in volumes.persist %}
  {{ volume }}:
{% endfor %}
{% endif %}
```

**Step 2: Run tests**

Run: `vendor/bin/pest tests/Unit/Service/DockerComposeGeneratorTest.php --filter="proxy"`
Expected: PASS

**Step 3: Commit**

```bash
git add src/Template/docker/compose.base.twig
git commit -m "feat(proxy): conditionally render ports and labels in compose template"
```

---

### Task 7: Modificar templates de servicios para condicionar labels

**Files:**
- Modify: `src/Template/docker/services/mailpit.twig`
- Modify: `src/Template/docker/services/rabbitmq.twig`
- Modify: `src/Template/docker/services/dozzle.twig`
- Modify: `src/Template/docker/services/minio.twig`
- Modify: `src/Template/docker/services/elasticsearch.twig`
- Modify: `src/Template/docker/services/traefik.twig`

**Step 1: Modificar mailpit.twig**

```twig
  {{ name }}:
    image: axllent/mailpit:{{ service.version }}
    container_name: seaman-{{ name }}
    ports:
      - "${MAILPIT_PORT}:8025"
      - "${MAILPIT_SMTP_PORT:-1025}:1025"
{% if proxy_enabled and labels is defined and labels|length > 0 %}
    labels:
{% for label in labels %}
      - "{{ label }}"
{% endfor %}
{% endif %}
    networks:
      - seaman
    environment:
      - MP_MAX_MESSAGES=5000
      - MP_SMTP_AUTH_ACCEPT_ANY=1
      - MP_SMTP_AUTH_ALLOW_INSECURE=1

```

**Step 2: Modificar otros templates con el mismo patrón**

Aplicar el mismo cambio a los demás templates - cambiar:
```twig
{% if labels is defined and labels|length > 0 %}
```
por:
```twig
{% if proxy_enabled and labels is defined and labels|length > 0 %}
```

**Step 3: Modificar traefik.twig para solo incluirse si proxy_enabled**

Este template se incluirá solo cuando Traefik esté habilitado (controlado por DockerComposeGenerator que no incluirá Traefik en los servicios cuando proxy está desactivado).

**Step 4: Run tests**

Run: `vendor/bin/pest tests/Unit/Service/DockerComposeGeneratorTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Template/docker/services/
git commit -m "feat(proxy): conditionally render labels in service templates"
```

---

### Task 8: Modificar ProjectInitializer para condicionar Traefik

**Files:**
- Modify: `src/Service/ProjectInitializer.php`
- Modify: `tests/Unit/Service/ProjectInitializerTest.php`

**Step 1: Write the failing test**

Añadir a `tests/Unit/Service/ProjectInitializerTest.php`:

```php
test('does not initialize traefik when proxy disabled', function () {
    $testDir = sys_get_temp_dir() . '/seaman-init-test-' . uniqid();
    mkdir($testDir, 0755, true);

    $registry = ServiceRegistry::create();
    $initializer = new ProjectInitializer($registry);

    $config = new Configuration(
        projectName: 'testproject',
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, XdebugConfig::default()),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        projectType: ProjectType::Existing,
        proxy: ProxyConfig::disabled(),
    );

    // Create minimal required files
    $dockerDir = dirname(__DIR__, 3) . '/docker';
    if (!is_dir($dockerDir)) {
        mkdir($dockerDir, 0755, true);
    }
    if (!file_exists($dockerDir . '/Dockerfile.template')) {
        file_put_contents($dockerDir . '/Dockerfile.template', 'FROM php:8.4');
    }

    try {
        $initializer->initializeDockerEnvironment($config, $testDir);
    } catch (\Exception $e) {
        // Ignorar errores de build
    }

    expect(is_dir($testDir . '/.seaman/traefik'))->toBeFalse()
        ->and(is_dir($testDir . '/.seaman/certs'))->toBeFalse();

    exec("rm -rf {$testDir}");
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/ProjectInitializerTest.php --filter="does not initialize traefik"`
Expected: FAIL - directories exist

**Step 3: Write minimal implementation**

Modificar `src/Service/ProjectInitializer.php` método `initializeDockerEnvironment()`:

```php
public function initializeDockerEnvironment(Configuration $config, string $projectRoot): void
{
    $seamanDir = $projectRoot . '/.seaman';
    if (!is_dir($seamanDir)) {
        mkdir($seamanDir, 0755, true);
    }

    $templateDir = __DIR__ . '/../Template';
    $renderer = new TemplateRenderer($templateDir);

    // Generate docker-compose.yml (in project root)
    $labelGenerator = new TraefikLabelGenerator();
    $composeGenerator = new DockerComposeGenerator($renderer, $labelGenerator);
    $composeYaml = $composeGenerator->generate($config);
    file_put_contents($projectRoot . '/docker-compose.yml', $composeYaml);

    // Initialize Traefik configuration and certificates only if proxy enabled
    if ($config->proxy()->enabled) {
        $this->initializeTraefik($config, $projectRoot);
    }

    // Save configuration
    $validator = new ConfigurationValidator();
    $configManager = new ConfigManager($projectRoot, $this->registry, $validator);
    $configManager->save($config);

    // Generate xdebug-toggle script (needed by Dockerfile build and runtime)
    $xdebugScript = $renderer->render('scripts/xdebug-toggle.sh.twig', [
        'xdebug' => $config->php->xdebug,
    ]);

    // Create in project root for Docker build
    $rootScriptDir = $projectRoot . '/scripts';
    if (!is_dir($rootScriptDir)) {
        mkdir($rootScriptDir, 0755, true);
    }
    file_put_contents($rootScriptDir . '/xdebug-toggle.sh', $xdebugScript);
    chmod($rootScriptDir . '/xdebug-toggle.sh', 0755);

    // Also create in .seaman for volume mount reference
    $seamanScriptDir = $seamanDir . '/scripts';
    if (!is_dir($seamanScriptDir)) {
        mkdir($seamanScriptDir, 0755, true);
    }
    file_put_contents($seamanScriptDir . '/xdebug-toggle.sh', $xdebugScript);
    chmod($seamanScriptDir . '/xdebug-toggle.sh', 0755);

    // Copy Dockerfile template to .seaman/
    $templateDockerfile = __DIR__ . '/../../docker/Dockerfile.template';
    if (!file_exists($templateDockerfile)) {
        Terminal::error('Seaman Dockerfile template not found.');
        throw new \RuntimeException('Template Dockerfile missing');
    }
    copy($templateDockerfile, $seamanDir . '/Dockerfile');

    // Build Docker image
    $builder = new DockerImageBuilder($projectRoot, $config->php->version);
    $result = $builder->build();

    if (!$result->isSuccessful()) {
        Terminal::error('Failed to build Docker image');
        throw new \RuntimeException('Docker build failed');
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/ProjectInitializerTest.php --filter="does not initialize traefik"`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Service/ProjectInitializer.php tests/Unit/Service/ProjectInitializerTest.php
git commit -m "feat(proxy): conditionally initialize Traefik in ProjectInitializer"
```

---

## Fase 3: Comandos proxy:enable y proxy:disable

### Task 9: Crear ProxyEnableCommand

**Files:**
- Create: `src/Command/ProxyEnableCommand.php`
- Create: `tests/Unit/Command/ProxyEnableCommandTest.php`

**Step 1: Write the failing test**

Crear `tests/Unit/Command/ProxyEnableCommandTest.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for ProxyEnableCommand.
// ABOUTME: Validates proxy enable command behavior.

namespace Seaman\Tests\Unit\Command;

use Seaman\Command\ProxyEnableCommand;
use Seaman\Enum\OperatingMode;

test('ProxyEnableCommand exists and has correct name', function () {
    expect(class_exists(ProxyEnableCommand::class))->toBeTrue();

    $reflection = new \ReflectionClass(ProxyEnableCommand::class);
    $attributes = $reflection->getAttributes();

    $commandAttribute = null;
    foreach ($attributes as $attr) {
        if (str_contains($attr->getName(), 'AsCommand')) {
            $commandAttribute = $attr->newInstance();
            break;
        }
    }

    expect($commandAttribute)->not->toBeNull()
        ->and($commandAttribute->name)->toBe('seaman:proxy:enable');
});

test('ProxyEnableCommand only supports Managed mode', function () {
    $command = new ProxyEnableCommand();

    $reflection = new \ReflectionMethod($command, 'supportsMode');
    $reflection->setAccessible(true);

    expect($reflection->invoke($command, OperatingMode::Managed))->toBeTrue()
        ->and($reflection->invoke($command, OperatingMode::Unmanaged))->toBeFalse()
        ->and($reflection->invoke($command, OperatingMode::Uninitialized))->toBeFalse();
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Command/ProxyEnableCommandTest.php`
Expected: FAIL - Class ProxyEnableCommand not found

**Step 3: Write minimal implementation**

Crear `src/Command/ProxyEnableCommand.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Command to enable Traefik reverse proxy.
// ABOUTME: Regenerates docker-compose with proxy enabled and initializes Traefik.

namespace Seaman\Command;

use Seaman\Enum\OperatingMode;
use Seaman\Service\ConfigManager;
use Seaman\Service\ConfigurationValidator;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\DockerComposeGenerator;
use Seaman\Service\ProjectInitializer;
use Seaman\Service\TemplateRenderer;
use Seaman\Service\TraefikLabelGenerator;
use Seaman\UI\Terminal;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ProxyConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'seaman:proxy:enable',
    description: 'Enable Traefik reverse proxy',
    aliases: ['proxy:enable'],
)]
class ProxyEnableCommand extends ModeAwareCommand
{
    public function __construct(
        private readonly ServiceRegistry $registry,
    ) {
        parent::__construct();
    }

    protected function supportsMode(OperatingMode $mode): bool
    {
        return $mode === OperatingMode::Managed;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = (string) getcwd();

        // Load current configuration
        $validator = new ConfigurationValidator();
        $configManager = new ConfigManager($projectRoot, $this->registry, $validator);

        try {
            $config = $configManager->load();
        } catch (\RuntimeException $e) {
            Terminal::error('Failed to load configuration: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Check if already enabled
        if ($config->proxy()->enabled) {
            Terminal::info('Proxy is already enabled.');
            return Command::SUCCESS;
        }

        // Create new configuration with proxy enabled
        $newConfig = new Configuration(
            projectName: $config->projectName,
            version: $config->version,
            php: $config->php,
            services: $config->services,
            volumes: $config->volumes,
            projectType: $config->projectType,
            proxy: ProxyConfig::default($config->projectName),
            customServices: $config->customServices,
        );

        // Regenerate docker-compose.yml
        $templateDir = __DIR__ . '/../Template';
        $renderer = new TemplateRenderer($templateDir);
        $labelGenerator = new TraefikLabelGenerator();
        $composeGenerator = new DockerComposeGenerator($renderer, $labelGenerator);
        $composeYaml = $composeGenerator->generate($newConfig);
        file_put_contents($projectRoot . '/docker-compose.yml', $composeYaml);

        // Initialize Traefik
        $initializer = new ProjectInitializer($this->registry);
        $initializer->initializeTraefikPublic($newConfig, $projectRoot);

        // Save updated configuration
        $configManager->save($newConfig);

        Terminal::success('Proxy enabled successfully.');
        Terminal::output()->writeln('');
        Terminal::output()->writeln("  Run 'seaman restart' to apply changes.");
        Terminal::output()->writeln('');
        Terminal::output()->writeln('  Your services will be accessible at:');
        Terminal::output()->writeln("  • https://app.{$config->projectName}.local");
        Terminal::output()->writeln("  • https://traefik.{$config->projectName}.local");

        return Command::SUCCESS;
    }
}
```

**Step 4: Añadir método público initializeTraefikPublic a ProjectInitializer**

Añadir a `src/Service/ProjectInitializer.php`:

```php
/**
 * Initialize Traefik configuration (public wrapper for command usage).
 */
public function initializeTraefikPublic(Configuration $config, string $projectRoot): void
{
    $this->initializeTraefik($config, $projectRoot);
}
```

**Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Command/ProxyEnableCommandTest.php`
Expected: PASS

**Step 6: Commit**

```bash
git add src/Command/ProxyEnableCommand.php src/Service/ProjectInitializer.php tests/Unit/Command/ProxyEnableCommandTest.php
git commit -m "feat(proxy): add ProxyEnableCommand"
```

---

### Task 10: Crear ProxyDisableCommand

**Files:**
- Create: `src/Command/ProxyDisableCommand.php`
- Create: `tests/Unit/Command/ProxyDisableCommandTest.php`

**Step 1: Write the failing test**

Crear `tests/Unit/Command/ProxyDisableCommandTest.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for ProxyDisableCommand.
// ABOUTME: Validates proxy disable command behavior.

namespace Seaman\Tests\Unit\Command;

use Seaman\Command\ProxyDisableCommand;
use Seaman\Enum\OperatingMode;

test('ProxyDisableCommand exists and has correct name', function () {
    expect(class_exists(ProxyDisableCommand::class))->toBeTrue();

    $reflection = new \ReflectionClass(ProxyDisableCommand::class);
    $attributes = $reflection->getAttributes();

    $commandAttribute = null;
    foreach ($attributes as $attr) {
        if (str_contains($attr->getName(), 'AsCommand')) {
            $commandAttribute = $attr->newInstance();
            break;
        }
    }

    expect($commandAttribute)->not->toBeNull()
        ->and($commandAttribute->name)->toBe('seaman:proxy:disable');
});

test('ProxyDisableCommand only supports Managed mode', function () {
    $command = new ProxyDisableCommand();

    $reflection = new \ReflectionMethod($command, 'supportsMode');
    $reflection->setAccessible(true);

    expect($reflection->invoke($command, OperatingMode::Managed))->toBeTrue()
        ->and($reflection->invoke($command, OperatingMode::Unmanaged))->toBeFalse()
        ->and($reflection->invoke($command, OperatingMode::Uninitialized))->toBeFalse();
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Command/ProxyDisableCommandTest.php`
Expected: FAIL - Class ProxyDisableCommand not found

**Step 3: Write minimal implementation**

Crear `src/Command/ProxyDisableCommand.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Command to disable Traefik reverse proxy.
// ABOUTME: Regenerates docker-compose with direct port access.

namespace Seaman\Command;

use Seaman\Enum\OperatingMode;
use Seaman\Service\ConfigManager;
use Seaman\Service\ConfigurationValidator;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\DockerComposeGenerator;
use Seaman\Service\TemplateRenderer;
use Seaman\Service\TraefikLabelGenerator;
use Seaman\UI\Terminal;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ProxyConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'seaman:proxy:disable',
    description: 'Disable Traefik reverse proxy',
    aliases: ['proxy:disable'],
)]
class ProxyDisableCommand extends ModeAwareCommand
{
    public function __construct(
        private readonly ServiceRegistry $registry,
    ) {
        parent::__construct();
    }

    protected function supportsMode(OperatingMode $mode): bool
    {
        return $mode === OperatingMode::Managed;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = (string) getcwd();

        // Load current configuration
        $validator = new ConfigurationValidator();
        $configManager = new ConfigManager($projectRoot, $this->registry, $validator);

        try {
            $config = $configManager->load();
        } catch (\RuntimeException $e) {
            Terminal::error('Failed to load configuration: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Check if already disabled
        if (!$config->proxy()->enabled) {
            Terminal::info('Proxy is already disabled.');
            return Command::SUCCESS;
        }

        // Create new configuration with proxy disabled
        $newConfig = new Configuration(
            projectName: $config->projectName,
            version: $config->version,
            php: $config->php,
            services: $config->services,
            volumes: $config->volumes,
            projectType: $config->projectType,
            proxy: ProxyConfig::disabled(),
            customServices: $config->customServices,
        );

        // Regenerate docker-compose.yml
        $templateDir = __DIR__ . '/../Template';
        $renderer = new TemplateRenderer($templateDir);
        $labelGenerator = new TraefikLabelGenerator();
        $composeGenerator = new DockerComposeGenerator($renderer, $labelGenerator);
        $composeYaml = $composeGenerator->generate($newConfig);
        file_put_contents($projectRoot . '/docker-compose.yml', $composeYaml);

        // Save updated configuration
        $configManager->save($newConfig);

        Terminal::success('Proxy disabled successfully.');
        Terminal::output()->writeln('');
        Terminal::output()->writeln("  Run 'seaman restart' to apply changes.");
        Terminal::output()->writeln('');
        Terminal::output()->writeln('  Your services will be accessible at:');
        Terminal::output()->writeln('  • http://localhost:80 (app)');

        return Command::SUCCESS;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Command/ProxyDisableCommandTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Command/ProxyDisableCommand.php tests/Unit/Command/ProxyDisableCommandTest.php
git commit -m "feat(proxy): add ProxyDisableCommand"
```

---

## Fase 4: Detección Inteligente de DNS

### Task 11: Crear enum DnsProvider

**Files:**
- Create: `src/Enum/DnsProvider.php`
- Create: `tests/Unit/Enum/DnsProviderTest.php`

**Step 1: Write the failing test**

Crear `tests/Unit/Enum/DnsProviderTest.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for DnsProvider enum.
// ABOUTME: Validates DNS provider properties and priority ordering.

namespace Seaman\Tests\Unit\Enum;

use Seaman\Enum\DnsProvider;

test('DnsProvider has all expected cases', function () {
    $cases = DnsProvider::cases();

    expect($cases)->toHaveCount(5)
        ->and(DnsProvider::Dnsmasq->value)->toBe('dnsmasq')
        ->and(DnsProvider::SystemdResolved->value)->toBe('systemd-resolved')
        ->and(DnsProvider::NetworkManager->value)->toBe('networkmanager')
        ->and(DnsProvider::MacOSResolver->value)->toBe('macos-resolver')
        ->and(DnsProvider::Manual->value)->toBe('manual');
});

test('DnsProvider has display names', function () {
    expect(DnsProvider::Dnsmasq->getDisplayName())->toBe('dnsmasq')
        ->and(DnsProvider::SystemdResolved->getDisplayName())->toBe('systemd-resolved')
        ->and(DnsProvider::NetworkManager->getDisplayName())->toBe('NetworkManager')
        ->and(DnsProvider::MacOSResolver->getDisplayName())->toBe('macOS Resolver')
        ->and(DnsProvider::Manual->getDisplayName())->toBe('Manual');
});

test('DnsProvider has descriptions', function () {
    expect(DnsProvider::Dnsmasq->getDescription())->toBeString()
        ->and(DnsProvider::MacOSResolver->getDescription())->toContain('native');
});

test('DnsProvider has correct priorities', function () {
    expect(DnsProvider::MacOSResolver->getPriority())->toBe(1)
        ->and(DnsProvider::Dnsmasq->getPriority())->toBe(2)
        ->and(DnsProvider::SystemdResolved->getPriority())->toBe(3)
        ->and(DnsProvider::NetworkManager->getPriority())->toBe(4)
        ->and(DnsProvider::Manual->getPriority())->toBe(99);
});

test('DnsProvider sorts by priority correctly', function () {
    $providers = [
        DnsProvider::Manual,
        DnsProvider::Dnsmasq,
        DnsProvider::MacOSResolver,
        DnsProvider::NetworkManager,
    ];

    usort($providers, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

    expect($providers[0])->toBe(DnsProvider::MacOSResolver)
        ->and($providers[1])->toBe(DnsProvider::Dnsmasq)
        ->and($providers[2])->toBe(DnsProvider::NetworkManager)
        ->and($providers[3])->toBe(DnsProvider::Manual);
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Enum/DnsProviderTest.php`
Expected: FAIL - Class DnsProvider not found

**Step 3: Write minimal implementation**

Crear `src/Enum/DnsProvider.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Enum representing available DNS configuration providers.
// ABOUTME: Includes priority ordering for automatic provider selection.

namespace Seaman\Enum;

enum DnsProvider: string
{
    case Dnsmasq = 'dnsmasq';
    case SystemdResolved = 'systemd-resolved';
    case NetworkManager = 'networkmanager';
    case MacOSResolver = 'macos-resolver';
    case Manual = 'manual';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::Dnsmasq => 'dnsmasq',
            self::SystemdResolved => 'systemd-resolved',
            self::NetworkManager => 'NetworkManager',
            self::MacOSResolver => 'macOS Resolver',
            self::Manual => 'Manual',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Dnsmasq => 'Lightweight DNS forwarder with wildcard support',
            self::SystemdResolved => 'Systemd network name resolution manager',
            self::NetworkManager => 'NetworkManager with dnsmasq plugin',
            self::MacOSResolver => 'macOS native resolver for custom domains',
            self::Manual => 'Configure /etc/hosts manually',
        };
    }

    /**
     * Get priority for automatic selection (lower = higher priority).
     */
    public function getPriority(): int
    {
        return match ($this) {
            self::MacOSResolver => 1,
            self::Dnsmasq => 2,
            self::SystemdResolved => 3,
            self::NetworkManager => 4,
            self::Manual => 99,
        };
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Enum/DnsProviderTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Enum/DnsProvider.php tests/Unit/Enum/DnsProviderTest.php
git commit -m "feat(dns): add DnsProvider enum with priorities"
```

---

### Task 12: Crear ValueObject DetectedDnsProvider

**Files:**
- Create: `src/ValueObject/DetectedDnsProvider.php`
- Create: `tests/Unit/ValueObject/DetectedDnsProviderTest.php`

**Step 1: Write the failing test**

Crear `tests/Unit/ValueObject/DetectedDnsProviderTest.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for DetectedDnsProvider value object.
// ABOUTME: Validates detected DNS provider properties.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\Enum\DnsProvider;
use Seaman\ValueObject\DetectedDnsProvider;

test('creates DetectedDnsProvider with all properties', function () {
    $detected = new DetectedDnsProvider(
        provider: DnsProvider::Dnsmasq,
        configPath: '/etc/dnsmasq.d/seaman-test.conf',
        requiresSudo: true,
    );

    expect($detected->provider)->toBe(DnsProvider::Dnsmasq)
        ->and($detected->configPath)->toBe('/etc/dnsmasq.d/seaman-test.conf')
        ->and($detected->requiresSudo)->toBeTrue();
});

test('DetectedDnsProvider is immutable', function () {
    $detected = new DetectedDnsProvider(
        provider: DnsProvider::MacOSResolver,
        configPath: '/etc/resolver/test.local',
        requiresSudo: true,
    );

    $reflection = new \ReflectionClass($detected);
    expect($reflection->isReadOnly())->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ValueObject/DetectedDnsProviderTest.php`
Expected: FAIL - Class DetectedDnsProvider not found

**Step 3: Write minimal implementation**

Crear `src/ValueObject/DetectedDnsProvider.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Value object representing a detected DNS provider.
// ABOUTME: Contains provider type, config path, and sudo requirement.

namespace Seaman\ValueObject;

use Seaman\Enum\DnsProvider;

final readonly class DetectedDnsProvider
{
    public function __construct(
        public DnsProvider $provider,
        public string $configPath,
        public bool $requiresSudo,
    ) {}
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ValueObject/DetectedDnsProviderTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/ValueObject/DetectedDnsProvider.php tests/Unit/ValueObject/DetectedDnsProviderTest.php
git commit -m "feat(dns): add DetectedDnsProvider value object"
```

---

### Task 13: Añadir restartCommand a DnsConfigurationResult

**Files:**
- Modify: `src/ValueObject/DnsConfigurationResult.php`
- Modify: `tests/Unit/ValueObject/DnsConfigurationResultTest.php`

**Step 1: Write the failing test**

Añadir a `tests/Unit/ValueObject/DnsConfigurationResultTest.php`:

```php
test('DnsConfigurationResult has restartCommand property', function () {
    $result = new DnsConfigurationResult(
        type: 'dnsmasq',
        automatic: true,
        requiresSudo: true,
        configPath: '/etc/dnsmasq.d/test.conf',
        configContent: 'address=/.test.local/127.0.0.1',
        instructions: [],
        restartCommand: 'sudo systemctl restart dnsmasq',
    );

    expect($result->restartCommand)->toBe('sudo systemctl restart dnsmasq');
});

test('DnsConfigurationResult restartCommand can be null', function () {
    $result = new DnsConfigurationResult(
        type: 'macos-resolver',
        automatic: true,
        requiresSudo: true,
        configPath: '/etc/resolver/test.local',
        configContent: 'nameserver 127.0.0.1',
        instructions: [],
        restartCommand: null,
    );

    expect($result->restartCommand)->toBeNull();
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ValueObject/DnsConfigurationResultTest.php --filter="restartCommand"`
Expected: FAIL - Unknown named parameter restartCommand

**Step 3: Write minimal implementation**

Modificar `src/ValueObject/DnsConfigurationResult.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Result of DNS configuration operation.
// ABOUTME: Contains configuration type, paths, and instructions for setup.

namespace Seaman\ValueObject;

final readonly class DnsConfigurationResult
{
    /**
     * @param list<string> $instructions Manual setup instructions
     */
    public function __construct(
        public string $type,
        public bool $automatic,
        public bool $requiresSudo,
        public ?string $configPath,
        public ?string $configContent,
        public array $instructions,
        public ?string $restartCommand = null,
    ) {}
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/ValueObject/DnsConfigurationResultTest.php --filter="restartCommand"`
Expected: PASS

**Step 5: Commit**

```bash
git add src/ValueObject/DnsConfigurationResult.php tests/Unit/ValueObject/DnsConfigurationResultTest.php
git commit -m "feat(dns): add restartCommand to DnsConfigurationResult"
```

---

### Task 14: Refactorizar DnsConfigurationHelper con nuevos proveedores

**Files:**
- Modify: `src/Service/DnsConfigurationHelper.php`
- Modify: `tests/Unit/Service/DnsConfigurationHelperTest.php`

**Step 1: Write the failing tests**

Añadir a `tests/Unit/Service/DnsConfigurationHelperTest.php`:

```php
use Seaman\Enum\DnsProvider;
use Seaman\ValueObject\DetectedDnsProvider;

// Actualizar FakeDnsCommandExecutor para soportar más proveedores
final readonly class FakeDnsCommandExecutorV2 implements CommandExecutor
{
    public function __construct(
        private bool $hasDnsmasq = false,
        private bool $hasSystemdResolved = false,
        private bool $hasNetworkManager = false,
        private string $platform = 'Linux',
    ) {}

    public function execute(array $command): ProcessResult
    {
        if ($command[0] === 'which' && $command[1] === 'dnsmasq') {
            return new ProcessResult(exitCode: $this->hasDnsmasq ? 0 : 1);
        }

        if ($command[0] === 'systemctl' && $command[1] === 'is-active') {
            if ($command[2] === 'systemd-resolved') {
                return new ProcessResult(exitCode: $this->hasSystemdResolved ? 0 : 1);
            }
            if ($command[2] === 'NetworkManager') {
                return new ProcessResult(exitCode: $this->hasNetworkManager ? 0 : 1);
            }
        }

        return new ProcessResult(exitCode: 0);
    }
}

test('detectAvailableProviders returns empty array when no providers available', function () {
    $executor = new FakeDnsCommandExecutorV2();
    $helper = new DnsConfigurationHelper($executor);

    $providers = $helper->detectAvailableProviders('testproject');

    expect($providers)->toBeArray();
});

test('detectAvailableProviders detects dnsmasq', function () {
    $executor = new FakeDnsCommandExecutorV2(hasDnsmasq: true);
    $helper = new DnsConfigurationHelper($executor);

    $providers = $helper->detectAvailableProviders('testproject');

    expect($providers)->not->toBeEmpty();
    $dnsmasq = array_filter($providers, fn($p) => $p->provider === DnsProvider::Dnsmasq);
    expect($dnsmasq)->not->toBeEmpty();
});

test('detectAvailableProviders detects NetworkManager', function () {
    $executor = new FakeDnsCommandExecutorV2(hasNetworkManager: true);
    $helper = new DnsConfigurationHelper($executor);

    $providers = $helper->detectAvailableProviders('testproject');

    $nm = array_filter($providers, fn($p) => $p->provider === DnsProvider::NetworkManager);
    expect($nm)->not->toBeEmpty();
});

test('detectAvailableProviders returns providers sorted by priority', function () {
    $executor = new FakeDnsCommandExecutorV2(
        hasDnsmasq: true,
        hasSystemdResolved: true,
        hasNetworkManager: true,
    );
    $helper = new DnsConfigurationHelper($executor);

    $providers = $helper->detectAvailableProviders('testproject');

    // Dnsmasq should be first (priority 2)
    expect($providers[0]->provider)->toBe(DnsProvider::Dnsmasq);
});

test('getRecommendedProvider returns first provider by priority', function () {
    $executor = new FakeDnsCommandExecutorV2(
        hasDnsmasq: true,
        hasSystemdResolved: true,
    );
    $helper = new DnsConfigurationHelper($executor);

    $recommended = $helper->getRecommendedProvider('testproject');

    expect($recommended)->not->toBeNull()
        ->and($recommended->provider)->toBe(DnsProvider::Dnsmasq);
});

test('getRecommendedProvider returns null when no providers available', function () {
    $executor = new FakeDnsCommandExecutorV2();
    $helper = new DnsConfigurationHelper($executor);

    $recommended = $helper->getRecommendedProvider('testproject');

    expect($recommended)->toBeNull();
});

test('configureProvider returns correct result for dnsmasq', function () {
    $executor = new FakeDnsCommandExecutorV2(hasDnsmasq: true);
    $helper = new DnsConfigurationHelper($executor);

    $result = $helper->configureProvider('testproject', DnsProvider::Dnsmasq);

    expect($result->type)->toBe('dnsmasq')
        ->and($result->automatic)->toBeTrue()
        ->and($result->configContent)->toContain('.testproject.local')
        ->and($result->restartCommand)->toContain('dnsmasq');
});

test('configureProvider returns correct result for NetworkManager', function () {
    $executor = new FakeDnsCommandExecutorV2(hasNetworkManager: true);
    $helper = new DnsConfigurationHelper($executor);

    $result = $helper->configureProvider('testproject', DnsProvider::NetworkManager);

    expect($result->type)->toBe('networkmanager')
        ->and($result->automatic)->toBeTrue()
        ->and($result->configPath)->toContain('NetworkManager')
        ->and($result->restartCommand)->toContain('NetworkManager');
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Service/DnsConfigurationHelperTest.php --filter="detectAvailableProviders"`
Expected: FAIL - method detectAvailableProviders does not exist

**Step 3: Write minimal implementation**

Modificar `src/Service/DnsConfigurationHelper.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Helps configure DNS for local development domains.
// ABOUTME: Detects platform capabilities and provides setup instructions.

namespace Seaman\Service;

use Seaman\Contract\CommandExecutor;
use Seaman\Enum\DnsProvider;
use Seaman\ValueObject\DetectedDnsProvider;
use Seaman\ValueObject\DnsConfigurationResult;

final readonly class DnsConfigurationHelper
{
    public function __construct(
        private CommandExecutor $executor,
    ) {}

    /**
     * Configure DNS for the project (legacy method for backward compatibility).
     */
    public function configure(string $projectName): DnsConfigurationResult
    {
        $recommended = $this->getRecommendedProvider($projectName);

        if ($recommended === null) {
            return $this->getManualInstructions($projectName);
        }

        return $this->configureProvider($projectName, $recommended->provider);
    }

    /**
     * Detect all available DNS providers on this system.
     *
     * @return list<DetectedDnsProvider>
     */
    public function detectAvailableProviders(string $projectName): array
    {
        /** @var list<DetectedDnsProvider> $providers */
        $providers = [];

        // macOS resolver (only on Darwin)
        if (PHP_OS_FAMILY === 'Darwin') {
            $providers[] = new DetectedDnsProvider(
                provider: DnsProvider::MacOSResolver,
                configPath: "/etc/resolver/{$projectName}.local",
                requiresSudo: true,
            );
        }

        // dnsmasq
        if ($this->hasDnsmasq()) {
            $providers[] = new DetectedDnsProvider(
                provider: DnsProvider::Dnsmasq,
                configPath: $this->getDnsmasqConfigPath($projectName),
                requiresSudo: true,
            );
        }

        // systemd-resolved
        if ($this->hasSystemdResolved()) {
            $providers[] = new DetectedDnsProvider(
                provider: DnsProvider::SystemdResolved,
                configPath: "/etc/systemd/resolved.conf.d/seaman-{$projectName}.conf",
                requiresSudo: true,
            );
        }

        // NetworkManager
        if ($this->hasNetworkManager()) {
            $providers[] = new DetectedDnsProvider(
                provider: DnsProvider::NetworkManager,
                configPath: "/etc/NetworkManager/dnsmasq.d/seaman-{$projectName}.conf",
                requiresSudo: true,
            );
        }

        // Sort by priority
        usort($providers, fn(DetectedDnsProvider $a, DetectedDnsProvider $b): int =>
            $a->provider->getPriority() <=> $b->provider->getPriority()
        );

        return $providers;
    }

    /**
     * Get the recommended DNS provider for this system.
     */
    public function getRecommendedProvider(string $projectName): ?DetectedDnsProvider
    {
        $providers = $this->detectAvailableProviders($projectName);

        return $providers[0] ?? null;
    }

    /**
     * Configure DNS using a specific provider.
     */
    public function configureProvider(string $projectName, DnsProvider $provider): DnsConfigurationResult
    {
        return match ($provider) {
            DnsProvider::Dnsmasq => $this->configureDnsmasq($projectName),
            DnsProvider::SystemdResolved => $this->configureSystemdResolved($projectName),
            DnsProvider::NetworkManager => $this->configureNetworkManager($projectName),
            DnsProvider::MacOSResolver => $this->configureMacOSResolver($projectName),
            DnsProvider::Manual => $this->getManualInstructions($projectName),
        };
    }

    /**
     * Check if dnsmasq is available on the system.
     */
    public function hasDnsmasq(): bool
    {
        $result = $this->executor->execute(['which', 'dnsmasq']);
        return $result->isSuccessful();
    }

    /**
     * Check if systemd-resolved is active on the system.
     */
    public function hasSystemdResolved(): bool
    {
        $result = $this->executor->execute(['systemctl', 'is-active', 'systemd-resolved']);
        return $result->isSuccessful();
    }

    /**
     * Check if NetworkManager is active on the system.
     */
    public function hasNetworkManager(): bool
    {
        $result = $this->executor->execute(['systemctl', 'is-active', 'NetworkManager']);
        if (!$result->isSuccessful()) {
            return false;
        }

        return is_dir('/etc/NetworkManager');
    }

    /**
     * Configure DNS using dnsmasq.
     */
    private function configureDnsmasq(string $projectName): DnsConfigurationResult
    {
        $configPath = $this->getDnsmasqConfigPath($projectName);
        $configContent = "address=/.{$projectName}.local/127.0.0.1\n";

        $restartCommand = PHP_OS_FAMILY === 'Darwin'
            ? 'sudo brew services restart dnsmasq'
            : 'sudo systemctl restart dnsmasq';

        return new DnsConfigurationResult(
            type: 'dnsmasq',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: $configContent,
            instructions: [],
            restartCommand: $restartCommand,
        );
    }

    /**
     * Configure DNS using systemd-resolved.
     */
    private function configureSystemdResolved(string $projectName): DnsConfigurationResult
    {
        $configPath = "/etc/systemd/resolved.conf.d/seaman-{$projectName}.conf";
        $configContent = "[Resolve]\n";
        $configContent .= "DNS=127.0.0.1\n";
        $configContent .= "Domains=~{$projectName}.local\n";

        return new DnsConfigurationResult(
            type: 'systemd-resolved',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: $configContent,
            instructions: [],
            restartCommand: 'sudo systemctl restart systemd-resolved',
        );
    }

    /**
     * Configure DNS using NetworkManager with dnsmasq.
     */
    private function configureNetworkManager(string $projectName): DnsConfigurationResult
    {
        $configPath = "/etc/NetworkManager/dnsmasq.d/seaman-{$projectName}.conf";
        $configContent = "address=/.{$projectName}.local/127.0.0.1\n";

        return new DnsConfigurationResult(
            type: 'networkmanager',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: $configContent,
            instructions: [],
            restartCommand: 'sudo systemctl restart NetworkManager',
        );
    }

    /**
     * Configure DNS using macOS resolver.
     */
    private function configureMacOSResolver(string $projectName): DnsConfigurationResult
    {
        $configPath = "/etc/resolver/{$projectName}.local";
        $configContent = "nameserver 127.0.0.1\n";

        return new DnsConfigurationResult(
            type: 'macos-resolver',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: $configContent,
            instructions: [],
            restartCommand: null, // macOS resolver doesn't need restart
        );
    }

    /**
     * Get manual instructions for DNS configuration.
     */
    private function getManualInstructions(string $projectName): DnsConfigurationResult
    {
        $instructions = [
            'Add the following entries to /etc/hosts:',
            '',
            "127.0.0.1 app.{$projectName}.local",
            "127.0.0.1 traefik.{$projectName}.local",
            "127.0.0.1 mailpit.{$projectName}.local",
            '',
            'Or install dnsmasq for wildcard domain support:',
            '  - macOS: brew install dnsmasq',
            '  - Ubuntu/Debian: apt-get install dnsmasq',
        ];

        return new DnsConfigurationResult(
            type: 'manual',
            automatic: false,
            requiresSudo: false,
            configPath: null,
            configContent: null,
            instructions: $instructions,
            restartCommand: null,
        );
    }

    /**
     * Get dnsmasq configuration path based on platform.
     */
    private function getDnsmasqConfigPath(string $projectName): string
    {
        return match (PHP_OS_FAMILY) {
            'Linux' => "/etc/dnsmasq.d/seaman-{$projectName}.conf",
            'Darwin' => "/usr/local/etc/dnsmasq.d/seaman-{$projectName}.conf",
            default => throw new \RuntimeException('Unsupported platform: ' . PHP_OS_FAMILY),
        };
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Service/DnsConfigurationHelperTest.php`
Expected: PASS (puede haber algunos tests que necesiten ajustes por la nueva clase FakeDnsCommandExecutorV2)

**Step 5: Commit**

```bash
git add src/Service/DnsConfigurationHelper.php tests/Unit/Service/DnsConfigurationHelperTest.php
git commit -m "feat(dns): add multi-provider detection to DnsConfigurationHelper"
```

---

### Task 15: Actualizar flujo DNS en InitCommand

**Files:**
- Modify: `src/Command/InitCommand.php`

**Step 1: Modificar el método configureDns**

Actualizar `src/Command/InitCommand.php` método `configureDns()`:

```php
private function configureDns(string $projectName): void
{
    Terminal::output()->writeln('');
    Terminal::output()->writeln('  <fg=cyan>DNS Configuration</>');
    Terminal::output()->writeln('');

    $executor = new RealCommandExecutor();
    $helper = new DnsConfigurationHelper($executor);

    $providers = $helper->detectAvailableProviders($projectName);

    if (empty($providers)) {
        $this->handleManualDnsConfiguration($helper->configureProvider($projectName, \Seaman\Enum\DnsProvider::Manual));
        return;
    }

    $recommended = $providers[0];

    Terminal::output()->writeln("  Detected: <fg=green>{$recommended->provider->getDisplayName()}</>");
    Terminal::output()->writeln("  {$recommended->provider->getDescription()}");
    Terminal::output()->writeln('');

    if (confirm("Use {$recommended->provider->getDisplayName()} for DNS?", true)) {
        $result = $helper->configureProvider($projectName, $recommended->provider);
        $this->applyDnsConfiguration($result, $projectName);
        return;
    }

    // User rejected recommendation, show alternatives
    if (count($providers) > 1) {
        $options = $this->buildProviderOptions($providers);
        /** @var string $choice */
        $choice = select(
            label: 'Select DNS provider',
            options: $options,
        );

        if ($choice === 'manual') {
            $this->handleManualDnsConfiguration($helper->configureProvider($projectName, \Seaman\Enum\DnsProvider::Manual));
        } else {
            $provider = \Seaman\Enum\DnsProvider::from($choice);
            $result = $helper->configureProvider($projectName, $provider);
            $this->applyDnsConfiguration($result, $projectName);
        }
    } else {
        $this->handleManualDnsConfiguration($helper->configureProvider($projectName, \Seaman\Enum\DnsProvider::Manual));
    }
}

/**
 * @param list<\Seaman\ValueObject\DetectedDnsProvider> $providers
 * @return array<string, string>
 */
private function buildProviderOptions(array $providers): array
{
    $options = [];
    foreach ($providers as $detected) {
        $options[$detected->provider->value] = $detected->provider->getDisplayName()
            . ' - ' . $detected->provider->getDescription();
    }
    $options['manual'] = 'Manual - Configure /etc/hosts yourself';
    return $options;
}

private function applyDnsConfiguration(DnsConfigurationResult $result, string $projectName): void
{
    if (!$result->automatic) {
        $this->handleManualDnsConfiguration($result);
        return;
    }

    $this->handleAutomaticDnsConfiguration($result, $projectName);
}
```

También actualizar `handleAutomaticDnsConfiguration()` para usar `restartCommand`:

```php
private function handleAutomaticDnsConfiguration(DnsConfigurationResult $result, string $projectName): void
{
    if ($result->configPath === null || $result->configContent === null) {
        Terminal::error('Invalid automatic configuration: missing path or content');
        return;
    }

    if ($result->requiresSudo) {
        Terminal::output()->writeln('  <fg=yellow>⚠ This configuration requires sudo access</>');
        Terminal::output()->writeln('');
    }

    Terminal::output()->writeln("  Configuration file: <fg=cyan>{$result->configPath}</>");
    Terminal::output()->writeln('');
    Terminal::output()->writeln('  Content:');
    Terminal::output()->writeln('  <fg=gray>' . str_replace("\n", "\n  ", trim($result->configContent)) . '</>');
    Terminal::output()->writeln('');

    if (!confirm('Apply this DNS configuration?', true)) {
        info('DNS configuration skipped.');
        return;
    }

    // Create directory if needed
    $configDir = dirname($result->configPath);
    if (!is_dir($configDir)) {
        $mkdirCmd = $result->requiresSudo ? "sudo mkdir -p {$configDir}" : "mkdir -p {$configDir}";
        Terminal::output()->writeln("  Creating directory: {$configDir}");
        exec($mkdirCmd, $output, $exitCode);

        if ($exitCode !== 0) {
            Terminal::error('Failed to create configuration directory');
            return;
        }
    }

    // Write configuration
    $tempFile = tempnam(sys_get_temp_dir(), 'seaman-dns-');
    file_put_contents($tempFile, $result->configContent);

    $cpCmd = $result->requiresSudo
        ? "sudo cp {$tempFile} {$result->configPath}"
        : "cp {$tempFile} {$result->configPath}";

    exec($cpCmd, $output, $exitCode);
    unlink($tempFile);

    if ($exitCode !== 0) {
        Terminal::error('Failed to write DNS configuration');
        return;
    }

    Terminal::output()->writeln('');
    Terminal::output()->writeln('  <fg=green>✓</> DNS configuration written');

    // Restart DNS service if needed
    if ($result->restartCommand !== null) {
        Terminal::output()->writeln('');
        Terminal::output()->writeln("  Restarting DNS service...");
        exec($result->restartCommand);
    }

    Terminal::success('DNS configured successfully!');
    Terminal::output()->writeln('');
    Terminal::output()->writeln('  Your services will be accessible at:');
    Terminal::output()->writeln("  • https://app.{$projectName}.local");
    Terminal::output()->writeln("  • https://traefik.{$projectName}.local");
}
```

**Step 2: Añadir import necesario**

Añadir al inicio del archivo:

```php
use Seaman\Enum\DnsProvider;
```

**Step 3: Run tests**

Run: `vendor/bin/pest tests/Integration/Command/InitCommandTest.php`
Expected: PASS

**Step 4: Commit**

```bash
git add src/Command/InitCommand.php
git commit -m "feat(dns): update InitCommand with multi-provider DNS selection"
```

---

### Task 16: Actualizar InitializationSummary para mostrar estado del proxy

**Files:**
- Modify: `src/Service/InitializationSummary.php`

**Step 1: Modificar el método display para incluir proxy**

Actualizar la firma del método y mostrar el estado del proxy. Revisar el archivo actual y añadir un parámetro `bool $proxyEnabled = true` y mostrarlo en el summary.

**Step 2: Commit**

```bash
git add src/Service/InitializationSummary.php
git commit -m "feat(proxy): show proxy status in initialization summary"
```

---

## Fase 5: Tests de Integración y Limpieza

### Task 17: Ejecutar todos los tests y corregir errores

**Step 1: Ejecutar suite completa**

Run: `vendor/bin/pest`

**Step 2: Corregir cualquier test que falle**

Ajustar tests según sea necesario.

**Step 3: Ejecutar PHPStan**

Run: `vendor/bin/phpstan analyse`

**Step 4: Corregir errores de PHPStan**

**Step 5: Ejecutar php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix`

**Step 6: Commit final**

```bash
git add .
git commit -m "test: fix tests and static analysis for proxy and DNS features"
```

---

### Task 18: Commit final y merge

**Step 1: Verificar todos los tests pasan**

Run: `vendor/bin/pest && vendor/bin/phpstan analyse`

**Step 2: Review de cambios**

Run: `git log --oneline feature/traefik-optional-dns-detection...develop`

**Step 3: Preparar para merge**

El feature branch está listo para review y merge a develop.
