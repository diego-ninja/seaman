# Phase 2: Traefik Integration - Implementation Plan

**Phase**: 2 of 6
**Goal**: Add Traefik as required service with HTTPS support
**Dependencies**: Phase 1 (Foundation)
**Estimated Tasks**: 11 tasks
**Testing Strategy**: TDD for all new services, 95%+ unit test coverage

## Overview

This phase integrates Traefik as a mandatory reverse proxy service with automatic HTTPS certificate generation, service routing, and label management.

## Prerequisites

- Phase 1 completed and committed
- All Phase 1 tests passing
- Working in `.worktrees/dual-mode-traefik-import` branch

## Implementation Tasks

### Task 1: Add Traefik to Service Enum

**File**: `src/Enum/Service.php` (existing, needs update)

**Test First** (`tests/Unit/Enum/ServiceTest.php` - add to existing):
```php
public function test_traefik_is_required_service(): void
{
    $this->assertTrue(Service::Traefik->isRequired());
}

public function test_other_services_are_not_required(): void
{
    $this->assertFalse(Service::PostgreSQL->isRequired());
    $this->assertFalse(Service::MySQL->isRequired());
    $this->assertFalse(Service::Redis->isRequired());
}

public function test_traefik_has_correct_default_port(): void
{
    $this->assertSame(443, Service::Traefik->defaultPort());
}

public function test_traefik_has_additional_ports(): void
{
    $this->assertSame([80, 8080], Service::Traefik->additionalPorts());
}
```

**Update**: `src/Enum/Service.php`
```php
enum Service: string
{
    case Traefik = 'traefik';  // NEW - add as first case
    case PostgreSQL = 'postgresql';
    // ... existing cases

    public function isRequired(): bool
    {
        return $this === self::Traefik;
    }

    public function defaultPort(): int
    {
        return match ($this) {
            self::Traefik => 443,
            self::PostgreSQL => 5432,
            // ... existing matches
        };
    }

    /**
     * @return list<int>
     */
    public function additionalPorts(): array
    {
        return match ($this) {
            self::Traefik => [80, 8080],  // HTTP + Dashboard
            self::RabbitMQ => [15672],    // Management UI (existing)
            self::Mailpit => [1025],      // SMTP (existing)
            default => [],
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Traefik => '🔀',
            // ... existing icons
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Traefik => 'Reverse proxy with automatic HTTPS',
            // ... existing descriptions
        };
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/Enum/ServiceTest.php
vendor/bin/phpstan analyse src/Enum/Service.php
```

---

### Task 2: Create ProxyConfig Value Object

**File**: `src/ValueObject/ProxyConfig.php`

**Test First** (`tests/Unit/ValueObject/ProxyConfigTest.php`):
```php
<?php

// ABOUTME: Tests for ProxyConfig value object.
// ABOUTME: Validates proxy configuration behavior.

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use Ninja\Seaman\ValueObject\ProxyConfig;
use PHPUnit\Framework\TestCase;

final class ProxyConfigTest extends TestCase
{
    public function test_can_be_created_with_all_fields(): void
    {
        $config = new ProxyConfig(
            enabled: true,
            domainPrefix: 'myproject',
            certResolver: 'mkcert',
            dashboard: true
        );

        $this->assertTrue($config->enabled);
        $this->assertSame('myproject', $config->domainPrefix);
        $this->assertSame('mkcert', $config->certResolver);
        $this->assertTrue($config->dashboard);
    }

    public function test_default_creates_sensible_defaults(): void
    {
        $config = ProxyConfig::default('testproject');

        $this->assertTrue($config->enabled);
        $this->assertSame('testproject', $config->domainPrefix);
        $this->assertSame('selfsigned', $config->certResolver);
        $this->assertTrue($config->dashboard);
    }

    public function test_get_domain_with_subdomain(): void
    {
        $config = new ProxyConfig(true, 'myproject', 'mkcert', true);

        $this->assertSame('mailpit.myproject.local', $config->getDomain('mailpit'));
    }

    public function test_get_domain_defaults_to_app(): void
    {
        $config = new ProxyConfig(true, 'myproject', 'mkcert', true);

        $this->assertSame('app.myproject.local', $config->getDomain());
    }

    public function test_get_traefik_domain(): void
    {
        $config = new ProxyConfig(true, 'myapp', 'selfsigned', true);

        $this->assertSame('traefik.myapp.local', $config->getDomain('traefik'));
    }
}
```

**Implementation**:
```php
<?php

// ABOUTME: Value object representing Traefik proxy configuration.
// ABOUTME: Manages domain prefixes, certificate resolvers, and dashboard settings.

declare(strict_types=1);

namespace Ninja\Seaman\ValueObject;

final readonly class ProxyConfig
{
    public function __construct(
        public bool $enabled,
        public string $domainPrefix,
        public string $certResolver,
        public bool $dashboard,
    ) {}

    public static function default(string $projectName): self
    {
        return new self(
            enabled: true,
            domainPrefix: $projectName,
            certResolver: 'selfsigned',
            dashboard: true
        );
    }

    public function getDomain(string $subdomain = 'app'): string
    {
        return "{$subdomain}.{$this->domainPrefix}.local";
    }

    public function isMkcert(): bool
    {
        return $this->certResolver === 'mkcert';
    }

    public function isSelfSigned(): bool
    {
        return $this->certResolver === 'selfsigned';
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/ValueObject/ProxyConfigTest.php
vendor/bin/phpstan analyse src/ValueObject/ProxyConfig.php
vendor/bin/php-cs-fixer fix src/ValueObject/ProxyConfig.php
```

---

### Task 3: Update Configuration to Include ProxyConfig

**File**: `src/ValueObject/Configuration.php` (existing, needs update)

**Test Update** (`tests/Unit/ValueObject/ConfigurationTest.php`):
```php
public function test_configuration_includes_proxy_config(): void
{
    $proxyConfig = ProxyConfig::default('testproject');
    $config = new Configuration(
        version: '1.0',
        projectType: ProjectType::Web,
        php: $this->createPhpConfig(),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        proxy: $proxyConfig
    );

    $this->assertSame($proxyConfig, $config->proxy());
}
```

**Update**:
```php
readonly class Configuration
{
    public function __construct(
        private string $version,
        private ProjectType $projectType,
        private PhpConfig $php,
        private ServiceCollection $services,
        private VolumeConfig $volumes,
        private ProxyConfig $proxy,  // NEW
    ) {}

    // ... existing methods

    public function proxy(): ProxyConfig
    {
        return $this->proxy;
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/ValueObject/ConfigurationTest.php
vendor/bin/phpstan analyse src/ValueObject/Configuration.php
```

---

### Task 4: Create CertificateResult Value Object

**File**: `src/ValueObject/CertificateResult.php`

**Test First** (`tests/Unit/ValueObject/CertificateResultTest.php`):
```php
<?php

// ABOUTME: Tests for CertificateResult value object.
// ABOUTME: Validates certificate generation result data.

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use Ninja\Seaman\ValueObject\CertificateResult;
use PHPUnit\Framework\TestCase;

final class CertificateResultTest extends TestCase
{
    public function test_can_be_created_with_all_fields(): void
    {
        $result = new CertificateResult(
            type: 'mkcert',
            certPath: '/path/to/cert.pem',
            keyPath: '/path/to/key.pem',
            trusted: true
        );

        $this->assertSame('mkcert', $result->type);
        $this->assertSame('/path/to/cert.pem', $result->certPath);
        $this->assertSame('/path/to/key.pem', $result->keyPath);
        $this->assertTrue($result->trusted);
    }

    public function test_self_signed_is_not_trusted(): void
    {
        $result = new CertificateResult(
            type: 'self-signed',
            certPath: '/path/to/cert.pem',
            keyPath: '/path/to/key.pem',
            trusted: false
        );

        $this->assertFalse($result->trusted);
    }

    public function test_is_mkcert_helper(): void
    {
        $mkcert = new CertificateResult('mkcert', 'cert', 'key', true);
        $selfSigned = new CertificateResult('self-signed', 'cert', 'key', false);

        $this->assertTrue($mkcert->isMkcert());
        $this->assertFalse($selfSigned->isMkcert());
    }

    public function test_is_self_signed_helper(): void
    {
        $mkcert = new CertificateResult('mkcert', 'cert', 'key', true);
        $selfSigned = new CertificateResult('self-signed', 'cert', 'key', false);

        $this->assertFalse($mkcert->isSelfSigned());
        $this->assertTrue($selfSigned->isSelfSigned());
    }
}
```

**Implementation**:
```php
<?php

// ABOUTME: Value object representing certificate generation result.
// ABOUTME: Contains certificate paths, type, and trust status.

declare(strict_types=1);

namespace Ninja\Seaman\ValueObject;

final readonly class CertificateResult
{
    public function __construct(
        public string $type,
        public string $certPath,
        public string $keyPath,
        public bool $trusted,
    ) {}

    public function isMkcert(): bool
    {
        return $this->type === 'mkcert';
    }

    public function isSelfSigned(): bool
    {
        return $this->type === 'self-signed';
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/ValueObject/CertificateResultTest.php
vendor/bin/phpstan analyse src/ValueObject/CertificateResult.php
```

---

### Task 5: Create CertificateManager Service

**File**: `src/Service/CertificateManager.php`

**Test First** (`tests/Unit/Service/CertificateManagerTest.php`):
```php
<?php

// ABOUTME: Tests for CertificateManager service.
// ABOUTME: Validates certificate generation logic (mocked processes).

declare(strict_types=1);

namespace Tests\Unit\Service;

use Ninja\Seaman\Service\CertificateManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class CertificateManagerTest extends TestCase
{
    private CertificateManager $manager;

    protected function setUp(): void
    {
        $this->manager = new CertificateManager();
    }

    public function test_detects_mkcert_when_available(): void
    {
        if (!$this->isMkcertInstalled()) {
            $this->markTestSkipped('mkcert not installed');
        }

        $this->assertTrue($this->manager->hasMkcert());
    }

    public function test_generates_self_signed_certificate(): void
    {
        $tempDir = sys_get_temp_dir() . '/seaman-cert-test-' . uniqid();
        mkdir($tempDir . '/.seaman/certs', 0755, true);

        $result = $this->manager->generateSelfSigned('testproject', $tempDir);

        $this->assertSame('self-signed', $result->type);
        $this->assertStringEndsWith('/.seaman/certs/cert.pem', $result->certPath);
        $this->assertStringEndsWith('/.seaman/certs/key.pem', $result->keyPath);
        $this->assertFalse($result->trusted);

        // Verify files exist
        $this->assertFileExists($result->certPath);
        $this->assertFileExists($result->keyPath);

        // Cleanup
        unlink($result->certPath);
        unlink($result->keyPath);
        rmdir(dirname($result->certPath));
        rmdir(dirname(dirname($result->certPath)));
        rmdir($tempDir);
    }

    private function isMkcertInstalled(): bool
    {
        $process = new Process(['which', 'mkcert']);
        $process->run();
        return $process->isSuccessful();
    }
}
```

**Implementation**:
```php
<?php

// ABOUTME: Manages SSL certificate generation for Traefik.
// ABOUTME: Supports mkcert (trusted) and OpenSSL self-signed certificates.

declare(strict_types=1);

namespace Ninja\Seaman\Service;

use Ninja\Seaman\ValueObject\CertificateResult;
use Symfony\Component\Process\Process;

final readonly class CertificateManager
{
    public function generateCertificates(string $projectName, ?string $basePath = null): CertificateResult
    {
        $basePath ??= getcwd();

        if ($this->hasMkcert()) {
            return $this->generateWithMkcert($projectName, $basePath);
        }

        return $this->generateSelfSigned($projectName, $basePath);
    }

    public function hasMkcert(): bool
    {
        $process = new Process(['which', 'mkcert']);
        $process->run();
        return $process->isSuccessful();
    }

    public function generateWithMkcert(string $projectName, string $basePath): CertificateResult
    {
        $certsDir = $basePath . '/.seaman/certs';
        $this->ensureDirectoryExists($certsDir);

        $domains = [
            "*.{$projectName}.local",
            "{$projectName}.local"
        ];

        $certPath = $certsDir . '/cert.pem';
        $keyPath = $certsDir . '/key.pem';

        $process = new Process([
            'mkcert',
            '-cert-file', $certPath,
            '-key-file', $keyPath,
            ...$domains
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("Failed to generate mkcert certificates: " . $process->getErrorOutput());
        }

        return new CertificateResult(
            type: 'mkcert',
            certPath: $certPath,
            keyPath: $keyPath,
            trusted: true
        );
    }

    public function generateSelfSigned(string $projectName, string $basePath): CertificateResult
    {
        $certsDir = $basePath . '/.seaman/certs';
        $this->ensureDirectoryExists($certsDir);

        $certPath = $certsDir . '/cert.pem';
        $keyPath = $certsDir . '/key.pem';

        $process = new Process([
            'openssl', 'req', '-x509', '-nodes', '-days', '365',
            '-newkey', 'rsa:2048',
            '-keyout', $keyPath,
            '-out', $certPath,
            '-subj', "/CN=*.{$projectName}.local"
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("Failed to generate self-signed certificates: " . $process->getErrorOutput());
        }

        return new CertificateResult(
            type: 'self-signed',
            certPath: $certPath,
            keyPath: $keyPath,
            trusted: false
        );
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/Service/CertificateManagerTest.php
vendor/bin/phpstan analyse src/Service/CertificateManager.php
```

---

### Task 6: Create ServiceExposureType Enum

**File**: `src/Enum/ServiceExposureType.php`

**Test First** (`tests/Unit/Enum/ServiceExposureTypeTest.php`):
```php
<?php

// ABOUTME: Tests for ServiceExposureType enum.
// ABOUTME: Validates enum cases.

declare(strict_types=1);

namespace Tests\Unit\Enum;

use Ninja\Seaman\Enum\ServiceExposureType;
use PHPUnit\Framework\TestCase;

final class ServiceExposureTypeTest extends TestCase
{
    public function test_has_proxy_only_case(): void
    {
        $this->assertTrue(ServiceExposureType::ProxyOnly instanceof ServiceExposureType);
    }

    public function test_has_direct_port_case(): void
    {
        $this->assertTrue(ServiceExposureType::DirectPort instanceof ServiceExposureType);
    }
}
```

**Implementation**:
```php
<?php

// ABOUTME: Enum defining how services expose ports.
// ABOUTME: ProxyOnly for web UIs, DirectPort for data services.

declare(strict_types=1);

namespace Ninja\Seaman\Enum;

enum ServiceExposureType
{
    case ProxyOnly;    // Web UIs - only through Traefik
    case DirectPort;   // Data services - need direct port access
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/Enum/ServiceExposureTypeTest.php
vendor/bin/phpstan analyse src/Enum/ServiceExposureType.php
```

---

### Task 7: Create TraefikLabelGenerator Service

**File**: `src/Service/TraefikLabelGenerator.php`

**Test First** (`tests/Unit/Service/TraefikLabelGeneratorTest.php`):
```php
<?php

// ABOUTME: Tests for TraefikLabelGenerator service.
// ABOUTME: Validates Traefik label generation for different service types.

declare(strict_types=1);

namespace Tests\Unit\Service;

use Ninja\Seaman\Enum\Service;
use Ninja\Seaman\Service\TraefikLabelGenerator;
use Ninja\Seaman\ValueObject\ProxyConfig;
use Ninja\Seaman\ValueObject\ServiceConfig;
use PHPUnit\Framework\TestCase;

final class TraefikLabelGeneratorTest extends TestCase
{
    private TraefikLabelGenerator $generator;
    private ProxyConfig $proxyConfig;

    protected function setUp(): void
    {
        $this->generator = new TraefikLabelGenerator();
        $this->proxyConfig = ProxyConfig::default('testproject');
    }

    public function test_generates_labels_for_web_service(): void
    {
        $service = new ServiceConfig('mailpit', true, Service::Mailpit, 'latest', 8025, [1025], []);

        $labels = $this->generator->generateLabels($service, $this->proxyConfig);

        $this->assertContains('traefik.enable=true', $labels);
        $this->assertContains('traefik.http.routers.mailpit.rule=Host(`mailpit.testproject.local`)', $labels);
        $this->assertContains('traefik.http.routers.mailpit.entrypoints=websecure', $labels);
        $this->assertContains('traefik.http.routers.mailpit.tls=true', $labels);
        $this->assertContains('traefik.http.services.mailpit.loadbalancer.server.port=8025', $labels);
    }

    public function test_disables_traefik_for_data_services(): void
    {
        $service = new ServiceConfig('postgresql', true, Service::PostgreSQL, '16', 5432, [], []);

        $labels = $this->generator->generateLabels($service, $this->proxyConfig);

        $this->assertContains('traefik.enable=false', $labels);
        $this->assertCount(1, $labels);
    }

    public function test_generates_labels_for_rabbitmq_management(): void
    {
        $service = new ServiceConfig('rabbitmq', true, Service::RabbitMQ, '3.13', 5672, [15672], []);

        $labels = $this->generator->generateLabels($service, $this->proxyConfig);

        $this->assertContains('traefik.http.services.rabbitmq.loadbalancer.server.port=15672', $labels);
    }

    public function test_generates_app_labels(): void
    {
        $labels = $this->generator->generateAppLabels($this->proxyConfig);

        $this->assertContains('traefik.enable=true', $labels);
        $this->assertContains('traefik.http.routers.app.rule=Host(`app.testproject.local`)', $labels);
        $this->assertContains('traefik.http.middlewares.app-compress.compress=true', $labels);
        $this->assertContains('traefik.http.middlewares.app-headers.headers.sslredirect=true', $labels);
    }
}
```

**Implementation**:
```php
<?php

// ABOUTME: Generates Traefik routing labels for services.
// ABOUTME: Handles different service types with appropriate exposure strategies.

declare(strict_types=1);

namespace Ninja\Seaman\Service;

use Ninja\Seaman\Enum\Service;
use Ninja\Seaman\Enum\ServiceExposureType;
use Ninja\Seaman\ValueObject\ProxyConfig;
use Ninja\Seaman\ValueObject\ServiceConfig;

final readonly class TraefikLabelGenerator
{
    /**
     * @return list<string>
     */
    public function generateLabels(ServiceConfig $service, ProxyConfig $proxy): array
    {
        $exposureType = $this->getExposureType($service->type());

        if ($exposureType === ServiceExposureType::DirectPort) {
            return ['traefik.enable=false'];
        }

        $serviceName = $service->name();
        $domain = $proxy->getDomain($serviceName);
        $routerName = str_replace('.', '-', $serviceName);

        return [
            'traefik.enable=true',
            "traefik.http.routers.{$routerName}.rule=Host(`{$domain}`)",
            "traefik.http.routers.{$routerName}.entrypoints=websecure",
            "traefik.http.routers.{$routerName}.tls=true",
            $this->getServicePortLabel($service),
        ];
    }

    /**
     * @return list<string>
     */
    public function generateAppLabels(ProxyConfig $proxy): array
    {
        $domain = $proxy->getDomain('app');

        return [
            'traefik.enable=true',
            "traefik.http.routers.app.rule=Host(`{$domain}`)",
            'traefik.http.routers.app.entrypoints=websecure',
            'traefik.http.routers.app.tls=true',
            'traefik.http.services.app.loadbalancer.server.port=80',
            'traefik.http.middlewares.app-compress.compress=true',
            'traefik.http.middlewares.app-headers.headers.sslredirect=true',
            'traefik.http.routers.app.middlewares=app-compress,app-headers',
        ];
    }

    /**
     * @return list<string>
     */
    public function generateTraefikLabels(ProxyConfig $proxy): array
    {
        $domain = $proxy->getDomain('traefik');

        return [
            'traefik.enable=true',
            "traefik.http.routers.traefik.rule=Host(`{$domain}`)",
            'traefik.http.routers.traefik.service=api@internal',
            'traefik.http.routers.traefik.entrypoints=websecure',
            'traefik.http.routers.traefik.tls=true',
        ];
    }

    private function getExposureType(Service $service): ServiceExposureType
    {
        return match ($service) {
            Service::PostgreSQL,
            Service::MySQL,
            Service::MariaDB,
            Service::MongoDB,
            Service::Redis,
            Service::Memcached => ServiceExposureType::DirectPort,

            default => ServiceExposureType::ProxyOnly,
        };
    }

    private function getServicePortLabel(ServiceConfig $service): string
    {
        $port = match ($service->type()) {
            Service::Mailpit => 8025,
            Service::RabbitMQ => 15672,
            Service::Dozzle => 8080,
            Service::MinIO => 9001,
            default => 80,
        };

        return "traefik.http.services.{$service->name()}.loadbalancer.server.port={$port}";
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/Service/TraefikLabelGeneratorTest.php
vendor/bin/phpstan analyse src/Service/TraefikLabelGenerator.php
```

---

### Task 8: Create TraefikService Implementation

**File**: `src/Service/Container/TraefikService.php`

**Test First** (`tests/Unit/Service/Container/TraefikServiceTest.php`):
```php
<?php

// ABOUTME: Tests for TraefikService.
// ABOUTME: Validates Traefik service configuration.

declare(strict_types=1);

namespace Tests\Unit\Service\Container;

use Ninja\Seaman\Enum\Service;
use Ninja\Seaman\Service\Container\TraefikService;
use PHPUnit\Framework\TestCase;

final class TraefikServiceTest extends TestCase
{
    private TraefikService $service;

    protected function setUp(): void
    {
        $this->service = new TraefikService();
    }

    public function test_type_is_traefik(): void
    {
        $this->assertSame(Service::Traefik, $this->service->getType());
    }

    public function test_name_is_traefik(): void
    {
        $this->assertSame('traefik', $this->service->getName());
    }

    public function test_display_name(): void
    {
        $this->assertSame('Traefik', $this->service->getDisplayName());
    }

    public function test_has_icon(): void
    {
        $this->assertSame('🔀', $this->service->getIcon());
    }

    public function test_has_description(): void
    {
        $this->assertStringContainsString('reverse proxy', strtolower($this->service->getDescription()));
    }

    public function test_default_config(): void
    {
        $config = $this->service->getDefaultConfig();

        $this->assertTrue($config->enabled());
        $this->assertSame('traefik', $config->name());
        $this->assertSame(Service::Traefik, $config->type());
        $this->assertSame('v3.1', $config->version());
        $this->assertSame(443, $config->port());
        $this->assertSame([80, 8080], $config->additionalPorts());
    }

    public function test_required_ports(): void
    {
        $ports = $this->service->getRequiredPorts();

        $this->assertContains(80, $ports);
        $this->assertContains(443, $ports);
        $this->assertContains(8080, $ports);
    }

    public function test_no_dependencies(): void
    {
        $this->assertEmpty($this->service->getDependencies());
    }

    public function test_no_environment_variables(): void
    {
        $this->assertEmpty($this->service->getEnvVariables());
    }
}
```

**Implementation**:
```php
<?php

// ABOUTME: Traefik reverse proxy service implementation.
// ABOUTME: Required service providing HTTPS and automatic routing.

declare(strict_types=1);

namespace Ninja\Seaman\Service\Container;

use Ninja\Seaman\Contract\ServiceInterface;
use Ninja\Seaman\Enum\Service;
use Ninja\Seaman\ValueObject\HealthCheck;
use Ninja\Seaman\ValueObject\ServiceConfig;

final readonly class TraefikService implements ServiceInterface
{
    public function getType(): Service
    {
        return Service::Traefik;
    }

    public function getName(): string
    {
        return 'traefik';
    }

    public function getDisplayName(): string
    {
        return 'Traefik';
    }

    public function getIcon(): string
    {
        return '🔀';
    }

    public function getDescription(): string
    {
        return 'Reverse proxy with automatic HTTPS';
    }

    /**
     * @return list<Service>
     */
    public function getDependencies(): array
    {
        return [];
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: 'traefik',
            enabled: true,
            type: Service::Traefik,
            version: 'v3.1',
            port: 443,
            additionalPorts: [80, 8080],
            environmentVariables: []
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        return [
            'image' => "traefik:{$config->version()}",
            'container_name' => 'seaman-traefik',
            'ports' => [
                '80:80',
                '443:443',
                '8080:8080',
            ],
            'volumes' => [
                '/var/run/docker.sock:/var/run/docker.sock:ro',
                './.seaman/traefik:/etc/traefik:ro',
                './.seaman/certs:/certs:ro',
            ],
            'command' => [
                '--api.dashboard=true',
                '--api.insecure=true',
                '--providers.docker=true',
                '--providers.docker.exposedbydefault=false',
                '--providers.file.directory=/etc/traefik/dynamic',
                '--entrypoints.web.address=:80',
                '--entrypoints.websecure.address=:443',
                '--log.level=INFO',
                '--accesslog=true',
            ],
            'restart' => 'unless-stopped',
        ];
    }

    /**
     * @return list<int>
     */
    public function getRequiredPorts(): array
    {
        return [80, 443, 8080];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(
            test: ['wget', '--spider', '-q', 'http://localhost:8080/ping'],
            interval: '10s',
            timeout: '5s',
            retries: 3
        );
    }

    /**
     * @return array<string, string>
     */
    public function getEnvVariables(): array
    {
        return [];
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/Service/Container/TraefikServiceTest.php
vendor/bin/phpstan analyse src/Service/Container/TraefikService.php
```

---

### Task 9: Create Traefik Configuration Templates

**File**: `src/Template/traefik/traefik.yml.twig`

**Create Template**:
```yaml
api:
  dashboard: {{ proxy.dashboard ? 'true' : 'false' }}
  insecure: true

providers:
  docker:
    exposedByDefault: false
  file:
    directory: /etc/traefik/dynamic

entryPoints:
  web:
    address: :80
  websecure:
    address: :443

log:
  level: INFO

accessLog: {}
```

**File**: `src/Template/traefik/dynamic/certs.yml.twig`

**Create Template**:
```yaml
tls:
  certificates:
    - certFile: /certs/cert.pem
      keyFile: /certs/key.pem
  stores:
    default:
      defaultCertificate:
        certFile: /certs/cert.pem
        keyFile: /certs/key.pem
```

**Verification**: Visual inspection of templates

---

### Task 10: Update DockerComposeGenerator

**File**: `src/Service/DockerComposeGenerator.php` (existing, needs updates)

**Update to inject TraefikLabelGenerator**:
```php
public function __construct(
    private readonly TemplateRenderer $renderer,
    private readonly TraefikLabelGenerator $labelGenerator, // NEW
) {}
```

**Update generate() method**:
```php
public function generate(Configuration $config): string
{
    // Generate Traefik labels for app
    $appLabels = $this->labelGenerator->generateAppLabels($config->proxy());

    // Generate Traefik labels for each service
    $servicesWithLabels = [];
    foreach ($config->services()->enabled() as $service) {
        $labels = $this->labelGenerator->generateLabels($service, $config->proxy());
        $servicesWithLabels[] = [
            'config' => $service,
            'labels' => $labels,
        ];
    }

    return $this->renderer->render('docker/compose.base.twig', [
        'php_version' => $config->php()->version()->value,
        'proxy' => $config->proxy(),
        'app_labels' => $appLabels,
        'services' => $servicesWithLabels,
        'volumes' => $config->volumes(),
        'xdebug' => $config->php()->xdebug(),
    ]);
}
```

**Update Template** (`src/Template/docker/compose.base.twig`):
```yaml
services:
  app:
    build:
      context: .
      dockerfile: .seaman/Dockerfile
    # ... existing config
    labels:
{% for label in app_labels %}
      - "{{ label }}"
{% endfor %}

  traefik:
    image: traefik:v3.1
    container_name: seaman-traefik
    ports:
      - "80:80"
      - "443:443"
      - "8080:8080"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./.seaman/traefik:/etc/traefik:ro
      - ./.seaman/certs:/certs:ro
    command:
      - --api.dashboard=true
      - --api.insecure=true
      - --providers.docker=true
      - --providers.docker.exposedbydefault=false
      - --providers.file.directory=/etc/traefik/dynamic
      - --entrypoints.web.address=:80
      - --entrypoints.websecure.address=:443
      - --log.level=INFO
      - --accesslog=true
    networks:
      - seaman
    restart: unless-stopped
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.traefik.rule=Host(`traefik.{{ proxy.domainPrefix }}.local`)"
      - "traefik.http.routers.traefik.service=api@internal"
      - "traefik.http.routers.traefik.entrypoints=websecure"
      - "traefik.http.routers.traefik.tls=true"

{% for service_data in services %}
  {{ service_data.config.name }}:
    # ... existing service config
    labels:
{% for label in service_data.labels %}
      - "{{ label }}"
{% endfor %}
{% endfor %}
```

**Verification**:
```bash
vendor/bin/phpstan analyse src/Service/DockerComposeGenerator.php
```

---

### Task 11: Update InitCommand to Generate Traefik Config

**File**: `src/Command/InitCommand.php` (existing, needs update)

**Add to constructor**:
```php
public function __construct(
    // ... existing deps
    private readonly CertificateManager $certificateManager, // NEW
    private readonly TemplateRenderer $templateRenderer, // NEW (if not already there)
) {
    parent::__construct();
}
```

**Add after ProjectInitializer call**:
```php
// Generate certificates
$io->section('Generating SSL Certificates');
$certResult = $this->certificateManager->generateCertificates(
    $config->proxy()->domainPrefix()
);

if ($certResult->isMkcert()) {
    $io->success('Generated trusted certificates with mkcert');
} else {
    $io->warning('Generated self-signed certificates (browser will show warnings)');
    $io->note('Install mkcert for trusted local certificates: https://github.com/FiloSottile/mkcert');
}

// Generate Traefik configuration
$io->section('Configuring Traefik');
$this->generateTraefikConfig($config);
$io->success('Traefik configured');
```

**Add method**:
```php
private function generateTraefikConfig(Configuration $config): void
{
    $traefikDir = '.seaman/traefik';
    $dynamicDir = $traefikDir . '/dynamic';

    if (!is_dir($dynamicDir)) {
        mkdir($dynamicDir, 0755, true);
    }

    // Generate static config
    $staticConfig = $this->templateRenderer->render('traefik/traefik.yml.twig', [
        'proxy' => $config->proxy(),
    ]);
    file_put_contents($traefikDir . '/traefik.yml', $staticConfig);

    // Generate dynamic config
    $dynamicConfig = $this->templateRenderer->render('traefik/dynamic/certs.yml.twig', []);
    file_put_contents($dynamicDir . '/certs.yml', $dynamicConfig);
}
```

**Verification**:
```bash
vendor/bin/phpstan analyse src/Command/InitCommand.php
vendor/bin/php-cs-fixer fix src/Command/InitCommand.php
```

---

## Final Phase 2 Verification

After all tasks complete:

```bash
# Run all unit tests
vendor/bin/pest tests/Unit --coverage

# Verify 95% coverage
vendor/bin/pest tests/Unit --coverage --min=95

# Run PHPStan
vendor/bin/phpstan analyse

# Run PHP CS Fixer
vendor/bin/php-cs-fixer fix --dry-run --diff

# Test initialization manually
seaman init
# Should generate:
# - .seaman/certs/cert.pem
# - .seaman/certs/key.pem
# - .seaman/traefik/traefik.yml
# - .seaman/traefik/dynamic/certs.yml
# - docker-compose.yml with Traefik service
```

## Expected Coverage Report

```
Phase 2 New Files:
- ProxyConfig: 100%
- CertificateResult: 100%
- CertificateManager: 95%+ (process execution mocked)
- ServiceExposureType: 100%
- TraefikLabelGenerator: 100%
- TraefikService: 100%

Overall Project Coverage: ≥ 95%
```

## Commit Strategy

Commit after each completed task:

```bash
git add <files>
git commit -m "feat(traefik): <task description>

<details>

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

## Success Criteria

- ✅ All 11 tasks completed
- ✅ Traefik added to Service enum
- ✅ ProxyConfig integrated into Configuration
- ✅ Certificates generated (mkcert or self-signed)
- ✅ Traefik labels generated for all services
- ✅ docker-compose.yml includes Traefik
- ✅ All unit tests passing (95%+ coverage)
- ✅ PHPStan level 10 clean
- ✅ Manual init test successful

## Next Phase

After Phase 2 completion:
- Phase 3: DNS Management
- Document: `docs/plans/phases/phase-3-dns-management.md`
