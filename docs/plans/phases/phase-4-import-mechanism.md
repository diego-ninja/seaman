# Phase 4: Import Mechanism - Implementation Plan

**Phase**: 4 of 6
**Goal**: Import existing docker-compose.yaml files to seaman.yml
**Dependencies**: Phase 1, 2, 3
**Estimated Tasks**: 9 tasks
**Testing Strategy**: TDD for all services, 95%+ unit test coverage

## Overview

This phase enables seaman to import existing docker-compose.yaml files using fuzzy service detection. Recognized services become managed, unknown services preserved as custom_services in seaman.yml.

## Prerequisites

- Phases 1, 2, 3 completed and committed
- All previous tests passing
- Working in `.worktrees/dual-mode-traefik-import` branch

## Implementation Tasks

### Task 1: Create DetectedService Value Object

**File**: `src/ValueObject/DetectedService.php`

**Test First** (`tests/Unit/ValueObject/DetectedServiceTest.php`):
```php
<?php

// ABOUTME: Tests for DetectedService value object.
// ABOUTME: Validates detected service data structure.

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use Ninja\Seaman\Enum\Service;
use Ninja\Seaman\ValueObject\DetectedService;
use PHPUnit\Framework\TestCase;

final class DetectedServiceTest extends TestCase
{
    public function test_creates_detected_service_with_high_confidence(): void
    {
        $detected = new DetectedService(
            type: Service::PostgreSQL,
            version: '16',
            confidence: 'high'
        );

        $this->assertSame(Service::PostgreSQL, $detected->type);
        $this->assertSame('16', $detected->version);
        $this->assertSame('high', $detected->confidence);
    }

    public function test_creates_detected_service_with_medium_confidence(): void
    {
        $detected = new DetectedService(
            type: Service::Redis,
            version: '7-alpine',
            confidence: 'medium'
        );

        $this->assertSame('medium', $detected->confidence);
    }

    public function test_is_high_confidence_helper(): void
    {
        $high = new DetectedService(Service::MySQL, '8.0', 'high');
        $medium = new DetectedService(Service::Redis, '7', 'medium');

        $this->assertTrue($high->isHighConfidence());
        $this->assertFalse($medium->isHighConfidence());
    }

    public function test_version_defaults_to_latest(): void
    {
        $detected = new DetectedService(Service::PostgreSQL);

        $this->assertSame('latest', $detected->version);
    }

    public function test_confidence_defaults_to_high(): void
    {
        $detected = new DetectedService(Service::PostgreSQL, '16');

        $this->assertSame('high', $detected->confidence);
    }
}
```

**Implementation**:
```php
<?php

// ABOUTME: Value object representing a detected service from docker-compose.
// ABOUTME: Contains service type, version, and detection confidence level.

declare(strict_types=1);

namespace Ninja\Seaman\ValueObject;

use Ninja\Seaman\Enum\Service;

final readonly class DetectedService
{
    public function __construct(
        public Service $type,
        public string $version = 'latest',
        public string $confidence = 'high',
    ) {}

    public function isHighConfidence(): bool
    {
        return $this->confidence === 'high';
    }

    public function isMediumConfidence(): bool
    {
        return $this->confidence === 'medium';
    }

    public function isLowConfidence(): bool
    {
        return $this->confidence === 'low';
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/ValueObject/DetectedServiceTest.php
vendor/bin/phpstan analyse src/ValueObject/DetectedService.php
vendor/bin/php-cs-fixer fix src/ValueObject/DetectedService.php
```

---

### Task 2: Create CustomServiceCollection Value Object

**File**: `src/ValueObject/CustomServiceCollection.php`

**Test First** (`tests/Unit/ValueObject/CustomServiceCollectionTest.php`):
```php
<?php

// ABOUTME: Tests for CustomServiceCollection value object.
// ABOUTME: Validates custom services collection behavior.

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use Ninja\Seaman\ValueObject\CustomServiceCollection;
use PHPUnit\Framework\TestCase;

final class CustomServiceCollectionTest extends TestCase
{
    public function test_creates_empty_collection(): void
    {
        $collection = new CustomServiceCollection();

        $this->assertCount(0, $collection->all());
        $this->assertSame(0, $collection->count());
    }

    public function test_creates_collection_with_services(): void
    {
        $services = [
            'my-app' => ['image' => 'myapp:latest'],
            'cache' => ['image' => 'redis:7'],
        ];

        $collection = new CustomServiceCollection($services);

        $this->assertCount(2, $collection->all());
        $this->assertSame(2, $collection->count());
    }

    public function test_has_service(): void
    {
        $services = ['my-app' => ['image' => 'myapp:latest']];
        $collection = new CustomServiceCollection($services);

        $this->assertTrue($collection->has('my-app'));
        $this->assertFalse($collection->has('non-existent'));
    }

    public function test_get_service(): void
    {
        $serviceConfig = ['image' => 'myapp:latest', 'ports' => ['8080:80']];
        $services = ['my-app' => $serviceConfig];
        $collection = new CustomServiceCollection($services);

        $this->assertSame($serviceConfig, $collection->get('my-app'));
    }

    public function test_get_throws_for_non_existent_service(): void
    {
        $collection = new CustomServiceCollection();

        $this->expectException(\InvalidArgumentException::class);
        $collection->get('non-existent');
    }

    public function test_add_service_returns_new_collection(): void
    {
        $collection = new CustomServiceCollection();
        $newCollection = $collection->add('my-app', ['image' => 'myapp:latest']);

        $this->assertCount(0, $collection->all());
        $this->assertCount(1, $newCollection->all());
        $this->assertTrue($newCollection->has('my-app'));
    }

    public function test_is_immutable(): void
    {
        $collection = new CustomServiceCollection(['app1' => ['image' => 'test']]);
        $collection->add('app2', ['image' => 'test2']);

        $this->assertCount(1, $collection->all());
        $this->assertFalse($collection->has('app2'));
    }
}
```

**Implementation**:
```php
<?php

// ABOUTME: Value object collection for custom (unrecognized) services.
// ABOUTME: Immutable collection storing raw docker-compose service configurations.

declare(strict_types=1);

namespace Ninja\Seaman\ValueObject;

final readonly class CustomServiceCollection
{
    /**
     * @param array<string, array<string, mixed>> $services
     */
    public function __construct(
        private array $services = []
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->services;
    }

    public function has(string $name): bool
    {
        return isset($this->services[$name]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $name): array
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException("Custom service '{$name}' not found");
        }

        return $this->services[$name];
    }

    public function count(): int
    {
        return count($this->services);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function add(string $name, array $config): self
    {
        return new self([...$this->services, $name => $config]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->services);
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/ValueObject/CustomServiceCollectionTest.php
vendor/bin/phpstan analyse src/ValueObject/CustomServiceCollection.php
vendor/bin/php-cs-fixer fix src/ValueObject/CustomServiceCollection.php
```

---

### Task 3: Update Configuration to Include CustomServiceCollection

**File**: `src/ValueObject/Configuration.php` (existing, needs update)

**Test Update** (`tests/Unit/ValueObject/ConfigurationTest.php`):
```php
public function test_configuration_includes_custom_services(): void
{
    $customServices = new CustomServiceCollection([
        'my-app' => ['image' => 'myapp:latest']
    ]);

    $config = new Configuration(
        version: '1.0',
        projectType: ProjectType::Web,
        php: $this->createPhpConfig(),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        proxy: ProxyConfig::default('test'),
        customServices: $customServices
    );

    $this->assertSame($customServices, $config->customServices());
    $this->assertTrue($config->hasCustomServices());
}

public function test_has_custom_services_returns_false_when_empty(): void
{
    $config = new Configuration(
        version: '1.0',
        projectType: ProjectType::Web,
        php: $this->createPhpConfig(),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        proxy: ProxyConfig::default('test')
    );

    $this->assertFalse($config->hasCustomServices());
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
        private ProxyConfig $proxy,
        private CustomServiceCollection $customServices = new CustomServiceCollection(), // NEW
    ) {}

    // ... existing methods

    public function customServices(): CustomServiceCollection
    {
        return $this->customServices;
    }

    public function hasCustomServices(): bool
    {
        return !$this->customServices->isEmpty();
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/ValueObject/ConfigurationTest.php
vendor/bin/phpstan analyse src/ValueObject/Configuration.php
```

---

### Task 4: Create ServiceDetector Service

**File**: `src/Service/ServiceDetector.php`

**Test First** (`tests/Unit/Service/ServiceDetectorTest.php`):
```php
<?php

// ABOUTME: Tests for ServiceDetector service.
// ABOUTME: Validates fuzzy service detection from docker-compose configs.

declare(strict_types=1);

namespace Tests\Unit\Service;

use Ninja\Seaman\Enum\Service;
use Ninja\Seaman\Service\ServiceDetector;
use PHPUnit\Framework\TestCase;

final class ServiceDetectorTest extends TestCase
{
    private ServiceDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new ServiceDetector();
    }

    public function test_detects_postgresql_by_image(): void
    {
        $composeService = ['image' => 'postgres:16'];

        $detected = $this->detector->detectService('db', $composeService);

        $this->assertNotNull($detected);
        $this->assertSame(Service::PostgreSQL, $detected->type);
        $this->assertSame('16', $detected->version);
        $this->assertTrue($detected->isHighConfidence());
    }

    public function test_detects_redis_by_image(): void
    {
        $composeService = ['image' => 'redis:7-alpine'];

        $detected = $this->detector->detectService('cache', $composeService);

        $this->assertNotNull($detected);
        $this->assertSame(Service::Redis, $detected->type);
        $this->assertSame('7-alpine', $detected->version);
    }

    public function test_detects_mysql_by_service_name(): void
    {
        $composeService = ['image' => 'some-custom-mysql:latest'];

        $detected = $this->detector->detectService('mysql', $composeService);

        $this->assertNotNull($detected);
        $this->assertSame(Service::MySQL, $detected->type);
    }

    public function test_detects_postgresql_by_port(): void
    {
        $composeService = [
            'image' => 'unknown:latest',
            'ports' => ['5432:5432']
        ];

        $detected = $this->detector->detectService('database', $composeService);

        $this->assertNotNull($detected);
        $this->assertSame(Service::PostgreSQL, $detected->type);
        $this->assertTrue($detected->isMediumConfidence());
    }

    public function test_detects_rabbitmq_by_image(): void
    {
        $composeService = ['image' => 'rabbitmq:3.13-management'];

        $detected = $this->detector->detectService('queue', $composeService);

        $this->assertNotNull($detected);
        $this->assertSame(Service::RabbitMQ, $detected->type);
        $this->assertSame('3.13-management', $detected->version);
    }

    public function test_detects_mailpit_by_image(): void
    {
        $composeService = ['image' => 'axllent/mailpit:latest'];

        $detected = $this->detector->detectService('mail', $composeService);

        $this->assertNotNull($detected);
        $this->assertSame(Service::Mailpit, $detected->type);
    }

    public function test_returns_null_for_unknown_service(): void
    {
        $composeService = ['image' => 'my-custom-app:latest'];

        $detected = $this->detector->detectService('my-app', $composeService);

        $this->assertNull($detected);
    }

    public function test_version_extraction_handles_latest(): void
    {
        $composeService = ['image' => 'postgres:latest'];

        $detected = $this->detector->detectService('db', $composeService);

        $this->assertSame('latest', $detected->version);
    }

    public function test_version_extraction_handles_no_tag(): void
    {
        $composeService = ['image' => 'postgres'];

        $detected = $this->detector->detectService('db', $composeService);

        $this->assertSame('latest', $detected->version);
    }
}
```

**Implementation**:
```php
<?php

// ABOUTME: Detects seaman service types from docker-compose service configurations.
// ABOUTME: Uses fuzzy matching (image, name patterns, ports) with confidence levels.

declare(strict_types=1);

namespace Ninja\Seaman\Service;

use Ninja\Seaman\Enum\Service;
use Ninja\Seaman\ValueObject\DetectedService;

final readonly class ServiceDetector
{
    /**
     * @param array<string, mixed> $composeService
     */
    public function detectService(string $serviceName, array $composeService): ?DetectedService
    {
        // Strategy 1: Match by image name (highest confidence)
        $imageDetection = $this->detectByImage($composeService);
        if ($imageDetection !== null) {
            return $imageDetection;
        }

        // Strategy 2: Match by service name patterns (medium confidence)
        $nameDetection = $this->detectByServiceName($serviceName, $composeService);
        if ($nameDetection !== null) {
            return $nameDetection;
        }

        // Strategy 3: Match by exposed ports (medium confidence)
        $portDetection = $this->detectByPort($composeService);
        if ($portDetection !== null) {
            return $portDetection;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $composeService
     */
    private function detectByImage(array $composeService): ?DetectedService
    {
        if (!isset($composeService['image'])) {
            return null;
        }

        $image = $composeService['image'];
        $version = $this->extractVersion($image);

        return match (true) {
            str_contains($image, 'postgres') => new DetectedService(Service::PostgreSQL, $version, 'high'),
            str_contains($image, 'mysql') => new DetectedService(Service::MySQL, $version, 'high'),
            str_contains($image, 'mariadb') => new DetectedService(Service::MariaDB, $version, 'high'),
            str_contains($image, 'mongo') => new DetectedService(Service::MongoDB, $version, 'high'),
            str_contains($image, 'redis') => new DetectedService(Service::Redis, $version, 'high'),
            str_contains($image, 'memcached') => new DetectedService(Service::Memcached, $version, 'high'),
            str_contains($image, 'rabbitmq') => new DetectedService(Service::RabbitMQ, $version, 'high'),
            str_contains($image, 'mailpit') || str_contains($image, 'axllent/mailpit') => new DetectedService(Service::Mailpit, $version, 'high'),
            str_contains($image, 'elasticsearch') => new DetectedService(Service::Elasticsearch, $version, 'high'),
            str_contains($image, 'minio') => new DetectedService(Service::MinIO, $version, 'high'),
            str_contains($image, 'dozzle') => new DetectedService(Service::Dozzle, $version, 'high'),
            str_contains($image, 'kafka') => new DetectedService(Service::Kafka, $version, 'high'),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $composeService
     */
    private function detectByServiceName(string $serviceName, array $composeService): ?DetectedService
    {
        $name = strtolower($serviceName);

        return match (true) {
            in_array($name, ['postgres', 'postgresql', 'pgsql']) => new DetectedService(Service::PostgreSQL, 'latest', 'medium'),
            in_array($name, ['mysql', 'mariadb']) => new DetectedService(Service::MySQL, 'latest', 'medium'),
            in_array($name, ['mongo', 'mongodb']) => new DetectedService(Service::MongoDB, 'latest', 'medium'),
            in_array($name, ['redis', 'cache']) => new DetectedService(Service::Redis, 'latest', 'medium'),
            in_array($name, ['memcached']) => new DetectedService(Service::Memcached, 'latest', 'medium'),
            in_array($name, ['rabbitmq', 'rabbit', 'queue']) => new DetectedService(Service::RabbitMQ, 'latest', 'medium'),
            in_array($name, ['mailpit', 'mail', 'mailhog']) => new DetectedService(Service::Mailpit, 'latest', 'medium'),
            in_array($name, ['elasticsearch', 'elastic', 'search']) => new DetectedService(Service::Elasticsearch, 'latest', 'medium'),
            in_array($name, ['minio', 's3']) => new DetectedService(Service::MinIO, 'latest', 'medium'),
            in_array($name, ['kafka']) => new DetectedService(Service::Kafka, 'latest', 'medium'),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $composeService
     */
    private function detectByPort(array $composeService): ?DetectedService
    {
        if (!isset($composeService['ports']) || !is_array($composeService['ports'])) {
            return null;
        }

        $ports = $this->extractPorts($composeService['ports']);

        return match (true) {
            in_array(5432, $ports) => new DetectedService(Service::PostgreSQL, 'latest', 'medium'),
            in_array(3306, $ports) => new DetectedService(Service::MySQL, 'latest', 'medium'),
            in_array(27017, $ports) => new DetectedService(Service::MongoDB, 'latest', 'medium'),
            in_array(6379, $ports) => new DetectedService(Service::Redis, 'latest', 'medium'),
            in_array(11211, $ports) => new DetectedService(Service::Memcached, 'latest', 'medium'),
            in_array(5672, $ports) || in_array(15672, $ports) => new DetectedService(Service::RabbitMQ, 'latest', 'medium'),
            in_array(8025, $ports) || in_array(1025, $ports) => new DetectedService(Service::Mailpit, 'latest', 'medium'),
            in_array(9200, $ports) => new DetectedService(Service::Elasticsearch, 'latest', 'medium'),
            in_array(9000, $ports) || in_array(9001, $ports) => new DetectedService(Service::MinIO, 'latest', 'medium'),
            in_array(9092, $ports) => new DetectedService(Service::Kafka, 'latest', 'medium'),
            default => null,
        };
    }

    private function extractVersion(string $image): string
    {
        if (preg_match('/:(.+)$/', $image, $matches)) {
            return $matches[1];
        }

        return 'latest';
    }

    /**
     * @param list<string|array<string, mixed>> $ports
     * @return list<int>
     */
    private function extractPorts(array $ports): array
    {
        $extractedPorts = [];

        foreach ($ports as $port) {
            if (is_string($port)) {
                // Format: "8080:80" or "80"
                $parts = explode(':', $port);
                $extractedPorts[] = (int) $parts[0];
            } elseif (is_array($port) && isset($port['published'])) {
                // Long format: {published: 8080, target: 80}
                $extractedPorts[] = (int) $port['published'];
            }
        }

        return $extractedPorts;
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/Service/ServiceDetectorTest.php
vendor/bin/phpstan analyse src/Service/ServiceDetector.php
vendor/bin/php-cs-fixer fix src/Service/ServiceDetector.php
```

---

### Task 5: Create ComposeImporter Service

**File**: `src/Service/ComposeImporter.php`

**Test First** (`tests/Unit/Service/ComposeImporterTest.php`):
```php
<?php

// ABOUTME: Tests for ComposeImporter service.
// ABOUTME: Validates docker-compose.yaml import logic.

declare(strict_types=1);

namespace Tests\Unit\Service;

use Ninja\Seaman\Service\ComposeImporter;
use Ninja\Seaman\Service\ServiceDetector;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ComposeImporterTest extends TestCase
{
    private vfsStreamDirectory $root;
    private ComposeImporter $importer;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('project');
        $this->importer = new ComposeImporter(new ServiceDetector());
    }

    public function test_imports_recognized_services(): void
    {
        $compose = [
            'services' => [
                'postgres' => ['image' => 'postgres:16'],
                'redis' => ['image' => 'redis:7-alpine'],
            ]
        ];

        vfsStream::newFile('docker-compose.yml')
            ->at($this->root)
            ->setContent(Yaml::dump($compose));

        $result = $this->importer->import($this->root->url() . '/docker-compose.yml');

        $this->assertCount(2, $result['recognized']);
        $this->assertCount(0, $result['custom']);
    }

    public function test_separates_custom_services(): void
    {
        $compose = [
            'services' => [
                'postgres' => ['image' => 'postgres:16'],
                'my-app' => ['image' => 'myapp:latest'],
            ]
        ];

        vfsStream::newFile('docker-compose.yml')
            ->at($this->root)
            ->setContent(Yaml::dump($compose));

        $result = $this->importer->import($this->root->url() . '/docker-compose.yml');

        $this->assertCount(1, $result['recognized']);
        $this->assertCount(1, $result['custom']);
        $this->assertArrayHasKey('my-app', $result['custom']);
    }

    public function test_extracts_service_configuration(): void
    {
        $compose = [
            'services' => [
                'postgres' => [
                    'image' => 'postgres:16',
                    'ports' => ['5432:5432'],
                    'environment' => ['POSTGRES_PASSWORD' => 'secret']
                ],
            ]
        ];

        vfsStream::newFile('docker-compose.yml')
            ->at($this->root)
            ->setContent(Yaml::dump($compose));

        $result = $this->importer->import($this->root->url() . '/docker-compose.yml');

        $this->assertArrayHasKey('postgres', $result['recognized']);
        $recognized = $result['recognized']['postgres'];
        $this->assertArrayHasKey('detected', $recognized);
        $this->assertArrayHasKey('config', $recognized);
    }

    public function test_throws_exception_for_missing_file(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('docker-compose file not found');

        $this->importer->import($this->root->url() . '/non-existent.yml');
    }

    public function test_throws_exception_for_invalid_yaml(): void
    {
        vfsStream::newFile('docker-compose.yml')
            ->at($this->root)
            ->setContent('invalid: yaml: content:');

        $this->expectException(\RuntimeException::class);

        $this->importer->import($this->root->url() . '/docker-compose.yml');
    }
}
```

**Implementation**:
```php
<?php

// ABOUTME: Imports docker-compose.yaml files into seaman configuration.
// ABOUTME: Detects recognized services and preserves custom services.

declare(strict_types=1);

namespace Ninja\Seaman\Service;

use Ninja\Seaman\ValueObject\DetectedService;
use Symfony\Component\Yaml\Yaml;

final readonly class ComposeImporter
{
    public function __construct(
        private ServiceDetector $detector
    ) {}

    /**
     * @return array{recognized: array<string, array{detected: DetectedService, config: array<string, mixed>}>, custom: array<string, array<string, mixed>>}
     */
    public function import(string $composePath): array
    {
        if (!file_exists($composePath)) {
            throw new \RuntimeException("docker-compose file not found: {$composePath}");
        }

        $content = file_get_contents($composePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read docker-compose file: {$composePath}");
        }

        try {
            $composeData = Yaml::parse($content);
        } catch (\Exception $e) {
            throw new \RuntimeException("Invalid YAML in docker-compose file: " . $e->getMessage());
        }

        if (!isset($composeData['services']) || !is_array($composeData['services'])) {
            throw new \RuntimeException("No services found in docker-compose file");
        }

        return $this->categorizeServices($composeData['services']);
    }

    /**
     * @param array<string, array<string, mixed>> $services
     * @return array{recognized: array<string, array{detected: DetectedService, config: array<string, mixed>}>, custom: array<string, array<string, mixed>>}
     */
    private function categorizeServices(array $services): array
    {
        $recognized = [];
        $custom = [];

        foreach ($services as $name => $config) {
            $detected = $this->detector->detectService($name, $config);

            if ($detected !== null) {
                $recognized[$name] = [
                    'detected' => $detected,
                    'config' => $config,
                ];
            } else {
                $custom[$name] = $config;
            }
        }

        return [
            'recognized' => $recognized,
            'custom' => $custom,
        ];
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/Service/ComposeImporterTest.php
vendor/bin/phpstan analyse src/Service/ComposeImporter.php
vendor/bin/php-cs-fixer fix src/Service/ComposeImporter.php
```

---

### Task 6: Update ConfigManager to Serialize/Deserialize Custom Services

**File**: `src/Service/ConfigManager.php` (existing, needs update)

**Update save() method**:
```php
public function save(Configuration $config): void
{
    $data = [
        'version' => $config->version(),
        'project_type' => $config->projectType()->value,
        'php' => [
            'version' => $config->php()->version()->value,
            'xdebug' => [
                'enabled' => $config->php()->xdebug()->enabled(),
                'ide_key' => $config->php()->xdebug()->ideKey(),
                'client_host' => $config->php()->xdebug()->clientHost(),
            ],
        ],
        'proxy' => [
            'enabled' => $config->proxy()->enabled,
            'domain_prefix' => $config->proxy()->domainPrefix,
            'cert_resolver' => $config->proxy()->certResolver,
            'dashboard' => $config->proxy()->dashboard,
        ],
        'services' => $this->serializeServices($config->services()),
        'volumes' => [
            'persist' => $config->volumes()->persisted(),
        ],
    ];

    // NEW: Add custom services if present
    if ($config->hasCustomServices()) {
        $data['custom_services'] = $config->customServices()->all();
    }

    $yaml = Yaml::dump($data, 4, 2);
    file_put_contents($this->getConfigPath(), $yaml);
}
```

**Update load() method**:
```php
public function load(): Configuration
{
    // ... existing loading logic

    // NEW: Load custom services
    $customServices = new CustomServiceCollection(
        $data['custom_services'] ?? []
    );

    return new Configuration(
        version: $data['version'],
        projectType: ProjectType::from($data['project_type']),
        php: $phpConfig,
        services: $services,
        volumes: $volumeConfig,
        proxy: $proxyConfig,
        customServices: $customServices, // NEW
    );
}
```

**Verification**:
```bash
vendor/bin/phpstan analyse src/Service/ConfigManager.php
```

---

### Task 7: Update DockerComposeGenerator to Merge Custom Services

**File**: `src/Service/DockerComposeGenerator.php` (existing, needs update)

**Update generate() method**:
```php
public function generate(Configuration $config): string
{
    // ... existing generation logic

    $baseCompose = $this->renderer->render('docker/compose.base.twig', [
        // ... existing template vars
    ]);

    // NEW: Merge custom services if present
    if ($config->hasCustomServices()) {
        $baseCompose = $this->mergeCustomServices($baseCompose, $config->customServices());
    }

    return $baseCompose;
}

private function mergeCustomServices(string $baseYaml, CustomServiceCollection $custom): string
{
    $yaml = Yaml::parse($baseYaml);

    foreach ($custom->all() as $name => $serviceConfig) {
        // Ensure custom service has seaman network
        if (!isset($serviceConfig['networks'])) {
            $serviceConfig['networks'] = ['seaman'];
        }

        $yaml['services'][$name] = $serviceConfig;
    }

    return Yaml::dump($yaml, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
}
```

**Verification**:
```bash
vendor/bin/phpstan analyse src/Service/DockerComposeGenerator.php
```

---

### Task 8: Update InitCommand with Import Flow

**File**: `src/Command/InitCommand.php` (existing, needs significant update)

**Add to constructor**:
```php
public function __construct(
    // ... existing
    private readonly ComposeImporter $composeImporter, // NEW
) {
    parent::__construct($modeDetector);
}
```

**Update execute() method**:
```php
protected function execute(InputInterface $input, OutputInterface $output): int
{
    $io = new SymfonyStyle($input, $output);

    // 1. Detect project state
    $detection = $this->projectDetector->detect();

    // 2. Handle existing config
    if ($detection->hasSeamanConfig() && !$input->getOption('force')) {
        if (!$this->confirmOverwrite($io)) {
            return Command::SUCCESS;
        }
    }

    // 3. NEW: Handle existing docker-compose
    $importMode = false;
    $importResult = null;

    if ($detection->hasDockerCompose() && !$detection->hasSeamanConfig()) {
        $choice = $io->choice(
            'Existing docker-compose.yaml found. How would you like to proceed?',
            ['Import it', 'Create new configuration'],
            'Import it'
        );

        if ($choice === 'Import it') {
            $importMode = true;
            $importResult = $this->importExistingCompose($io);
        }
    }

    // 4. Run wizard or use import result
    if ($importMode && $importResult !== null) {
        $config = $this->createConfigurationFromImport($io, $importResult, $detection);
    } else {
        $config = $this->wizard->run($io, $detection);
    }

    // 5. Initialize environment (existing logic)
    $this->initializer->initializeDockerEnvironment($config);

    // ... rest of init logic

    return Command::SUCCESS;
}
```

**Add methods**:
```php
/**
 * @return array{recognized: array<string, array{detected: DetectedService, config: array<string, mixed>}>, custom: array<string, array<string, mixed>>}
 */
private function importExistingCompose(SymfonyStyle $io): array
{
    $io->section('Importing docker-compose.yaml');

    $composePath = file_exists('docker-compose.yml') ? 'docker-compose.yml' : 'docker-compose.yaml';
    $importResult = $this->composeImporter->import($composePath);

    // Show detected services
    if (count($importResult['recognized']) > 0) {
        $io->success('Detected recognized services:');

        $table = [];
        foreach ($importResult['recognized'] as $name => $data) {
            $detected = $data['detected'];
            $table[] = [
                $name,
                $detected->type->value,
                $detected->version,
                $detected->confidence,
            ];
        }

        $io->table(['Service Name', 'Detected As', 'Version', 'Confidence'], $table);
    }

    // Show custom services
    if (count($importResult['custom']) > 0) {
        $io->warning('Unknown services (will be preserved):');
        $io->listing(array_keys($importResult['custom']));
    }

    if (!$io->confirm('Import recognized services?', true)) {
        return ['recognized' => [], 'custom' => []];
    }

    // Backup original
    $backupPath = $composePath . '.backup-' . date('Y-m-d-His');
    copy($composePath, $backupPath);
    $io->note("Original backed up to: {$backupPath}");

    return $importResult;
}

/**
 * @param array{recognized: array<string, array{detected: DetectedService, config: array<string, mixed>}>, custom: array<string, array<string, mixed>>} $importResult
 */
private function createConfigurationFromImport(
    SymfonyStyle $io,
    array $importResult,
    ProjectDetectionResult $detection
): Configuration {
    // Convert detected services to ServiceConfig objects
    $serviceConfigs = [];
    foreach ($importResult['recognized'] as $name => $data) {
        $detected = $data['detected'];
        $composeConfig = $data['config'];

        // Extract port from compose config
        $port = $detected->type->defaultPort();
        if (isset($composeConfig['ports']) && is_array($composeConfig['ports'])) {
            $portString = is_string($composeConfig['ports'][0]) ? $composeConfig['ports'][0] : '';
            if (preg_match('/^(\d+):/', $portString, $matches)) {
                $port = (int) $matches[1];
            }
        }

        $serviceConfigs[] = new ServiceConfig(
            name: $name,
            enabled: true,
            type: $detected->type,
            version: $detected->version,
            port: $port,
            additionalPorts: [],
            environmentVariables: $composeConfig['environment'] ?? []
        );
    }

    // Create custom services collection
    $customServices = new CustomServiceCollection($importResult['custom']);

    // Detect PHP version from composer.json or ask
    $phpVersion = $detection->phpVersion() !== null
        ? PhpVersion::tryFrom($detection->phpVersion()) ?? PhpVersion::PHP84
        : PhpVersion::PHP84;

    return new Configuration(
        version: '1.0',
        projectType: ProjectType::Existing,
        php: new PhpConfig($phpVersion, XdebugConfig::default()),
        services: new ServiceCollection($serviceConfigs),
        volumes: new VolumeConfig([]),
        proxy: ProxyConfig::default(basename(getcwd())),
        customServices: $customServices
    );
}
```

**Verification**:
```bash
vendor/bin/phpstan analyse src/Command/InitCommand.php
vendor/bin/php-cs-fixer fix src/Command/InitCommand.php
```

---

### Task 9: Add ProjectType::Existing Enum Case

**File**: `src/Enum/ProjectType.php` (existing, needs update)

**Add case**:
```php
enum ProjectType: string
{
    case Web = 'web';
    case Api = 'api';
    case Microservice = 'microservice';
    case Skeleton = 'skeleton';
    case Existing = 'existing'; // NEW
}
```

**Verification**:
```bash
vendor/bin/phpstan analyse src/Enum/ProjectType.php
```

---

## Final Phase 4 Verification

After all tasks complete:

```bash
# Run all unit tests
vendor/bin/pest tests/Unit --coverage

# Verify 95% coverage
vendor/bin/pest tests/Unit --coverage --min=95

# Run PHPStan
vendor/bin/phpstan analyse

# Test import manually
# Create a test docker-compose.yml with postgres, redis, and a custom service
# Run: seaman init
# Choose "Import it"
# Verify:
# - recognized services imported
# - custom services preserved in seaman.yml under custom_services
# - generated docker-compose.yml includes both
```

## Expected Coverage Report

```
Phase 4 New Files:
- DetectedService: 100%
- CustomServiceCollection: 100%
- ServiceDetector: 100%
- ComposeImporter: 100%

Overall Project Coverage: ≥ 95%
```

## Commit Strategy

Commit after each completed task:

```bash
git add <files>
git commit -m "feat(import): <task description>

<details>

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

## Success Criteria

- ✅ All 9 tasks completed
- ✅ docker-compose.yaml can be imported
- ✅ Recognized services detected with fuzzy matching
- ✅ Custom services preserved in seaman.yml
- ✅ Generated compose includes both managed and custom services
- ✅ Import confirmation UI works
- ✅ Original file backed up
- ✅ All unit tests passing (95%+ coverage)
- ✅ PHPStan level 10 clean

## Next Phase

After Phase 4 completion:
- Phase 5: Unmanaged Mode Support
- Document: `docs/plans/phases/phase-5-unmanaged-mode.md`
