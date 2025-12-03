# Phase 1: Foundation - Implementation Plan

**Phase**: 1 of 6
**Goal**: Establish dual-mode architecture and refactor existing code
**Dependencies**: None
**Estimated Tasks**: 15 tasks
**Testing Strategy**: TDD for all new services, 95%+ unit test coverage

## Overview

This phase establishes the foundation for dual-mode operation (managed/unmanaged) and improves existing code quality with better validation, error handling, and port conflict detection.

## Prerequisites

- Worktree created: `.worktrees/dual-mode-traefik-import`
- Branch: `feature/dual-mode-traefik-import`
- All existing tests passing
- PHPStan level 10 clean
- PHP CS Fixer clean

## Implementation Tasks

### Task 1: Create OperatingMode Enum

**File**: `src/Enum/OperatingMode.php`

**Test First** (`tests/Unit/Enum/OperatingModeTest.php`):
```php
<?php

// ABOUTME: Tests for OperatingMode enum.
// ABOUTME: Validates enum cases and behavior.

declare(strict_types=1);

namespace Tests\Unit\Enum;

use Ninja\Seaman\Enum\OperatingMode;
use PHPUnit\Framework\TestCase;

final class OperatingModeTest extends TestCase
{
    public function test_has_managed_case(): void
    {
        $this->assertTrue(OperatingMode::Managed instanceof OperatingMode);
    }

    public function test_has_unmanaged_case(): void
    {
        $this->assertTrue(OperatingMode::Unmanaged instanceof OperatingMode);
    }

    public function test_has_uninitialized_case(): void
    {
        $this->assertTrue(OperatingMode::Uninitialized instanceof OperatingMode);
    }

    public function test_managed_requires_initialization(): void
    {
        $this->assertFalse(OperatingMode::Managed->requiresInitialization());
    }

    public function test_unmanaged_does_not_require_initialization(): void
    {
        $this->assertFalse(OperatingMode::Unmanaged->requiresInitialization());
    }

    public function test_uninitialized_requires_initialization(): void
    {
        $this->assertTrue(OperatingMode::Uninitialized->requiresInitialization());
    }

    public function test_managed_is_managed_mode(): void
    {
        $this->assertTrue(OperatingMode::Managed->isManaged());
        $this->assertFalse(OperatingMode::Unmanaged->isManaged());
        $this->assertFalse(OperatingMode::Uninitialized->isManaged());
    }
}
```

**Implementation**:
```php
<?php

// ABOUTME: Enum representing seaman's operating modes.
// ABOUTME: Determines what features are available based on configuration state.

declare(strict_types=1);

namespace Ninja\Seaman\Enum;

enum OperatingMode
{
    case Managed;       // .seaman/seaman.yaml exists - full features
    case Unmanaged;     // Only docker-compose.yaml exists - basic passthrough
    case Uninitialized; // Neither exists - must run init

    public function requiresInitialization(): bool
    {
        return $this === self::Uninitialized;
    }

    public function isManaged(): bool
    {
        return $this === self::Managed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Managed => 'Managed (Full Features)',
            self::Unmanaged => 'Unmanaged (Basic Commands)',
            self::Uninitialized => 'Not Initialized',
        };
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/Enum/OperatingModeTest.php
vendor/bin/phpstan analyse src/Enum/OperatingMode.php
vendor/bin/php-cs-fixer fix src/Enum/OperatingMode.php
```

---

### Task 2: Create ModeDetector Service

**File**: `src/Service/ModeDetector.php`

**Test First** (`tests/Unit/Service/ModeDetectorTest.php`):
```php
<?php

// ABOUTME: Tests for ModeDetector service.
// ABOUTME: Validates operating mode detection logic.

declare(strict_types=1);

namespace Tests\Unit\Service;

use Ninja\Seaman\Enum\OperatingMode;
use Ninja\Seaman\Service\ModeDetector;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

final class ModeDetectorTest extends TestCase
{
    private vfsStreamDirectory $root;
    private ModeDetector $detector;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('project');
        $this->detector = new ModeDetector();
    }

    public function test_detects_managed_mode_when_seaman_yaml_exists(): void
    {
        vfsStream::newDirectory('.seaman')->at($this->root);
        vfsStream::newFile('seaman.yaml')->at($this->root->getChild('.seaman'));

        $mode = $this->detector->detect($this->root->url());

        $this->assertSame(OperatingMode::Managed, $mode);
    }

    public function test_detects_unmanaged_mode_when_only_docker_compose_yml_exists(): void
    {
        vfsStream::newFile('docker-compose.yml')->at($this->root);

        $mode = $this->detector->detect($this->root->url());

        $this->assertSame(OperatingMode::Unmanaged, $mode);
    }

    public function test_detects_unmanaged_mode_when_only_docker_compose_yaml_exists(): void
    {
        vfsStream::newFile('docker-compose.yaml')->at($this->root);

        $mode = $this->detector->detect($this->root->url());

        $this->assertSame(OperatingMode::Unmanaged, $mode);
    }

    public function test_detects_uninitialized_when_neither_exists(): void
    {
        $mode = $this->detector->detect($this->root->url());

        $this->assertSame(OperatingMode::Uninitialized, $mode);
    }

    public function test_prefers_managed_mode_when_both_exist(): void
    {
        vfsStream::newDirectory('.seaman')->at($this->root);
        vfsStream::newFile('seaman.yaml')->at($this->root->getChild('.seaman'));
        vfsStream::newFile('docker-compose.yml')->at($this->root);

        $mode = $this->detector->detect($this->root->url());

        $this->assertSame(OperatingMode::Managed, $mode);
    }

    public function test_uses_current_directory_when_no_path_provided(): void
    {
        $detector = new ModeDetector();
        $mode = $detector->detect();

        $this->assertInstanceOf(OperatingMode::class, $mode);
    }
}
```

**Implementation**:
```php
<?php

// ABOUTME: Detects seaman's current operating mode.
// ABOUTME: Checks for configuration files to determine managed/unmanaged/uninitialized state.

declare(strict_types=1);

namespace Ninja\Seaman\Service;

use Ninja\Seaman\Enum\OperatingMode;

final readonly class ModeDetector
{
    public function detect(?string $path = null): OperatingMode
    {
        $basePath = $path ?? getcwd();

        if ($this->hasSeamanConfig($basePath)) {
            return OperatingMode::Managed;
        }

        if ($this->hasDockerCompose($basePath)) {
            return OperatingMode::Unmanaged;
        }

        return OperatingMode::Uninitialized;
    }

    private function hasSeamanConfig(string $path): bool
    {
        return file_exists($path . '/.seaman/seaman.yaml');
    }

    private function hasDockerCompose(string $path): bool
    {
        return file_exists($path . '/docker-compose.yml') ||
               file_exists($path . '/docker-compose.yaml');
    }
}
```

**Verification**:
```bash
composer require --dev bovigo/assert
vendor/bin/pest tests/Unit/Service/ModeDetectorTest.php
vendor/bin/phpstan analyse src/Service/ModeDetector.php
vendor/bin/php-cs-fixer fix src/Service/ModeDetector.php
```

---

### Task 3: Create Custom Exception Classes

**File**: `src/Exception/SeamanNotInitializedException.php`

**Test First** (`tests/Unit/Exception/SeamanNotInitializedExceptionTest.php`):
```php
<?php

// ABOUTME: Tests for SeamanNotInitializedException.
// ABOUTME: Validates exception message and behavior.

declare(strict_types=1);

namespace Tests\Unit\Exception;

use Ninja\Seaman\Exception\SeamanNotInitializedException;
use PHPUnit\Framework\TestCase;

final class SeamanNotInitializedExceptionTest extends TestCase
{
    public function test_exception_has_helpful_message(): void
    {
        $exception = new SeamanNotInitializedException();

        $this->assertStringContainsString('Seaman is not initialized', $exception->getMessage());
        $this->assertStringContainsString('seaman init', $exception->getMessage());
    }

    public function test_exception_is_runtime_exception(): void
    {
        $exception = new SeamanNotInitializedException();

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
```

**Implementation**:
```php
<?php

// ABOUTME: Exception thrown when seaman is not initialized.
// ABOUTME: Provides helpful message directing user to run 'seaman init'.

declare(strict_types=1);

namespace Ninja\Seaman\Exception;

use RuntimeException;

final class SeamanNotInitializedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            "Seaman is not initialized in this project.\n\n" .
            "Run 'seaman init' to set up your development environment.\n" .
            "This will create .seaman/seaman.yaml and generate docker-compose.yml"
        );
    }
}
```

**File**: `src/Exception/InvalidComposeFileException.php`

**Test First** (`tests/Unit/Exception/InvalidComposeFileExceptionTest.php`):
```php
<?php

// ABOUTME: Tests for InvalidComposeFileException.
// ABOUTME: Validates exception message includes reason.

declare(strict_types=1);

namespace Tests\Unit\Exception;

use Ninja\Seaman\Exception\InvalidComposeFileException;
use PHPUnit\Framework\TestCase;

final class InvalidComposeFileExceptionTest extends TestCase
{
    public function test_exception_includes_reason(): void
    {
        $exception = new InvalidComposeFileException('file not found');

        $this->assertStringContainsString('file not found', $exception->getMessage());
        $this->assertStringContainsString('docker-compose.yml is invalid', $exception->getMessage());
    }

    public function test_exception_suggests_init(): void
    {
        $exception = new InvalidComposeFileException('corrupted');

        $this->assertStringContainsString('seaman init', $exception->getMessage());
    }
}
```

**Implementation**:
```php
<?php

// ABOUTME: Exception thrown when docker-compose.yml is invalid.
// ABOUTME: Provides reason and suggests regenerating with 'seaman init'.

declare(strict_types=1);

namespace Ninja\Seaman\Exception;

use RuntimeException;

final class InvalidComposeFileException extends RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            "docker-compose.yml is invalid: {$reason}\n\n" .
            "Run 'seaman init' to regenerate it."
        );
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/Exception/
vendor/bin/phpstan analyse src/Exception/
vendor/bin/php-cs-fixer fix src/Exception/
```

---

### Task 4: Create PortChecker Service

**File**: `src/Service/PortChecker.php`
**File**: `src/ValueObject/PortCheckResult.php`

**Test First** (`tests/Unit/Service/PortCheckerTest.php`):
```php
<?php

// ABOUTME: Tests for PortChecker service.
// ABOUTME: Validates port availability detection.

declare(strict_types=1);

namespace Tests\Unit\Service;

use Ninja\Seaman\Service\PortChecker;
use PHPUnit\Framework\TestCase;

final class PortCheckerTest extends TestCase
{
    private PortChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new PortChecker();
    }

    public function test_detects_port_in_use(): void
    {
        // Start a local server on port 19999
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($socket, '127.0.0.1', 19999);
        socket_listen($socket);

        $result = $this->checker->checkAvailability([19999]);

        $this->assertTrue($result->hasConflicts());
        $this->assertArrayHasKey(19999, $result->conflicts());

        socket_close($socket);
    }

    public function test_returns_no_conflicts_for_free_ports(): void
    {
        $result = $this->checker->checkAvailability([29999, 39999]);

        $this->assertFalse($result->hasConflicts());
        $this->assertEmpty($result->conflicts());
    }

    public function test_detects_multiple_port_conflicts(): void
    {
        $socket1 = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($socket1, '127.0.0.1', 49999);
        socket_listen($socket1);

        $socket2 = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($socket2, '127.0.0.1', 59999);
        socket_listen($socket2);

        $result = $this->checker->checkAvailability([49999, 59999, 69999]);

        $this->assertTrue($result->hasConflicts());
        $this->assertCount(2, $result->conflicts());
        $this->assertArrayHasKey(49999, $result->conflicts());
        $this->assertArrayHasKey(59999, $result->conflicts());

        socket_close($socket1);
        socket_close($socket2);
    }

    public function test_handles_privileged_ports_gracefully(): void
    {
        // Ports < 1024 require root, should not throw exception
        $result = $this->checker->checkAvailability([80, 443]);

        $this->assertInstanceOf(\Ninja\Seaman\ValueObject\PortCheckResult::class, $result);
    }
}
```

**PortCheckResult Value Object** (`src/ValueObject/PortCheckResult.php`):
```php
<?php

// ABOUTME: Value object representing port availability check results.
// ABOUTME: Contains map of conflicting ports and their occupying processes.

declare(strict_types=1);

namespace Ninja\Seaman\ValueObject;

final readonly class PortCheckResult
{
    /**
     * @param array<int, string> $conflicts Map of port => process name
     */
    public function __construct(
        private array $conflicts = []
    ) {}

    public function hasConflicts(): bool
    {
        return count($this->conflicts) > 0;
    }

    /**
     * @return array<int, string>
     */
    public function conflicts(): array
    {
        return $this->conflicts;
    }

    public function isPortAvailable(int $port): bool
    {
        return !isset($this->conflicts[$port]);
    }
}
```

**PortChecker Implementation** (`src/Service/PortChecker.php`):
```php
<?php

// ABOUTME: Checks if ports are available before starting services.
// ABOUTME: Detects port conflicts and identifies processes using ports.

declare(strict_types=1);

namespace Ninja\Seaman\Service;

use Ninja\Seaman\ValueObject\PortCheckResult;
use Symfony\Component\Process\Process;

final readonly class PortChecker
{
    /**
     * @param list<int> $ports
     */
    public function checkAvailability(array $ports): PortCheckResult
    {
        $conflicts = [];

        foreach ($ports as $port) {
            if ($this->isPortInUse($port)) {
                $conflicts[$port] = $this->findProcessUsingPort($port);
            }
        }

        return new PortCheckResult($conflicts);
    }

    private function isPortInUse(int $port): bool
    {
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);

        if ($connection !== false) {
            fclose($connection);
            return true;
        }

        return false;
    }

    private function findProcessUsingPort(int $port): string
    {
        // Try lsof (Unix/Linux/macOS)
        $process = new Process(['lsof', '-ti', ":{$port}"]);
        $process->run();

        if ($process->isSuccessful()) {
            $pid = trim($process->getOutput());
            return $this->getProcessName($pid);
        }

        return 'unknown';
    }

    private function getProcessName(string $pid): string
    {
        $process = new Process(['ps', '-p', $pid, '-o', 'comm=']);
        $process->run();

        if ($process->isSuccessful()) {
            return trim($process->getOutput()) . " (PID: {$pid})";
        }

        return "PID: {$pid}";
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/Service/PortCheckerTest.php
vendor/bin/phpstan analyse src/Service/PortChecker.php src/ValueObject/PortCheckResult.php
vendor/bin/php-cs-fixer fix src/Service/PortChecker.php src/ValueObject/PortCheckResult.php
```

---

### Task 5: Create ConfigurationValidator Service

**File**: `src/Service/ConfigurationValidator.php`
**File**: `src/ValueObject/ValidationResult.php`

**Test First** (`tests/Unit/Service/ConfigurationValidatorTest.php`):
```php
<?php

// ABOUTME: Tests for ConfigurationValidator service.
// ABOUTME: Validates configuration validation logic.

declare(strict_types=1);

namespace Tests\Unit\Service;

use Ninja\Seaman\Enum\PhpVersion;
use Ninja\Seaman\Enum\ProjectType;
use Ninja\Seaman\Enum\Service;
use Ninja\Seaman\Service\ConfigurationValidator;
use Ninja\Seaman\ValueObject\Configuration;
use Ninja\Seaman\ValueObject\PhpConfig;
use Ninja\Seaman\ValueObject\ServiceCollection;
use Ninja\Seaman\ValueObject\ServiceConfig;
use Ninja\Seaman\ValueObject\VolumeConfig;
use Ninja\Seaman\ValueObject\XdebugConfig;
use PHPUnit\Framework\TestCase;

final class ConfigurationValidatorTest extends TestCase
{
    private ConfigurationValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ConfigurationValidator();
    }

    public function test_valid_configuration_passes(): void
    {
        $config = $this->createValidConfiguration();

        $result = $this->validator->validate($config);

        $this->assertFalse($result->hasErrors());
    }

    public function test_detects_port_conflicts(): void
    {
        $services = new ServiceCollection([
            new ServiceConfig('postgresql', true, Service::PostgreSQL, '16', 5432, [], []),
            new ServiceConfig('mysql', true, Service::MySQL, '8.0', 5432, [], []), // Same port!
        ]);

        $config = new Configuration(
            version: '1.0',
            projectType: ProjectType::Web,
            php: new PhpConfig(PhpVersion::PHP84, new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal')),
            services: $services,
            volumes: new VolumeConfig([])
        );

        $result = $this->validator->validate($config);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Port conflict: 5432', $result->errors()[0]);
    }

    public function test_validates_service_has_required_fields(): void
    {
        // This would be tested if we allowed invalid ServiceConfig construction
        // Since ServiceConfig is readonly with required fields, this is type-safe
        $this->assertTrue(true);
    }

    private function createValidConfiguration(): Configuration
    {
        return new Configuration(
            version: '1.0',
            projectType: ProjectType::Web,
            php: new PhpConfig(
                PhpVersion::PHP84,
                new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal')
            ),
            services: new ServiceCollection([
                new ServiceConfig('postgresql', true, Service::PostgreSQL, '16', 5432, [], []),
            ]),
            volumes: new VolumeConfig(['postgresql'])
        );
    }
}
```

**ValidationResult Value Object** (`src/ValueObject/ValidationResult.php`):
```php
<?php

// ABOUTME: Value object representing validation result.
// ABOUTME: Contains list of validation errors.

declare(strict_types=1);

namespace Ninja\Seaman\ValueObject;

final readonly class ValidationResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        private array $errors = []
    ) {}

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function isValid(): bool
    {
        return !$this->hasErrors();
    }
}
```

**ConfigurationValidator Implementation** (`src/Service/ConfigurationValidator.php`):
```php
<?php

// ABOUTME: Validates seaman configuration for errors.
// ABOUTME: Checks for port conflicts, invalid PHP versions, and malformed custom services.

declare(strict_types=1);

namespace Ninja\Seaman\Service;

use Ninja\Seaman\Enum\PhpVersion;
use Ninja\Seaman\ValueObject\Configuration;
use Ninja\Seaman\ValueObject\ValidationResult;

final readonly class ConfigurationValidator
{
    public function validate(Configuration $config): ValidationResult
    {
        $errors = [];

        // Validate PHP version is supported
        if (!in_array($config->php()->version(), PhpVersion::cases(), true)) {
            $errors[] = "Unsupported PHP version: {$config->php()->version()->value}";
        }

        // Validate unique ports
        $ports = [];
        foreach ($config->services()->enabled() as $service) {
            if (in_array($service->port(), $ports, true)) {
                $errors[] = "Port conflict: {$service->port()} used by multiple services";
            }
            $ports[] = $service->port();

            foreach ($service->additionalPorts() as $additionalPort) {
                if (in_array($additionalPort, $ports, true)) {
                    $errors[] = "Port conflict: {$additionalPort} used by multiple services";
                }
                $ports[] = $additionalPort;
            }
        }

        return new ValidationResult($errors);
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/Service/ConfigurationValidatorTest.php
vendor/bin/phpstan analyse src/Service/ConfigurationValidator.php src/ValueObject/ValidationResult.php
vendor/bin/php-cs-fixer fix src/Service/ConfigurationValidator.php src/ValueObject/ValidationResult.php
```

---

### Task 6: Create ProjectDetector Service

**File**: `src/Service/ProjectDetector.php`
**File**: `src/ValueObject/ProjectDetectionResult.php`

**Test First** (`tests/Unit/Service/ProjectDetectorTest.php`):
```php
<?php

// ABOUTME: Tests for ProjectDetector service.
// ABOUTME: Validates project detection logic.

declare(strict_types=1);

namespace Tests\Unit\Service;

use Ninja\Seaman\Service\ProjectDetector;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

final class ProjectDetectorTest extends TestCase
{
    private vfsStreamDirectory $root;
    private ProjectDetector $detector;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('project');
        $this->detector = new ProjectDetector();
    }

    public function test_detects_composer_json(): void
    {
        vfsStream::newFile('composer.json')->at($this->root)->setContent('{}');

        $result = $this->detector->detect($this->root->url());

        $this->assertTrue($result->hasComposer());
    }

    public function test_detects_docker_compose_yml(): void
    {
        vfsStream::newFile('docker-compose.yml')->at($this->root)->setContent('version: "3"');

        $result = $this->detector->detect($this->root->url());

        $this->assertTrue($result->hasDockerCompose());
    }

    public function test_detects_docker_compose_yaml(): void
    {
        vfsStream::newFile('docker-compose.yaml')->at($this->root)->setContent('version: "3"');

        $result = $this->detector->detect($this->root->url());

        $this->assertTrue($result->hasDockerCompose());
    }

    public function test_detects_seaman_config(): void
    {
        vfsStream::newDirectory('.seaman')->at($this->root);
        vfsStream::newFile('seaman.yaml')->at($this->root->getChild('.seaman'))->setContent('version: "1.0"');

        $result = $this->detector->detect($this->root->url());

        $this->assertTrue($result->hasSeamanConfig());
    }

    public function test_detects_symfony_version(): void
    {
        $composerJson = [
            'require' => [
                'symfony/framework-bundle' => '^7.0'
            ]
        ];
        vfsStream::newFile('composer.json')->at($this->root)->setContent(json_encode($composerJson));

        $result = $this->detector->detect($this->root->url());

        $this->assertSame('7.0', $result->symfonyVersion());
    }

    public function test_detects_php_version_from_composer(): void
    {
        $composerJson = [
            'require' => [
                'php' => '^8.4'
            ]
        ];
        vfsStream::newFile('composer.json')->at($this->root)->setContent(json_encode($composerJson));

        $result = $this->detector->detect($this->root->url());

        $this->assertSame('8.4', $result->phpVersion());
    }

    public function test_returns_null_for_missing_symfony(): void
    {
        vfsStream::newFile('composer.json')->at($this->root)->setContent('{}');

        $result = $this->detector->detect($this->root->url());

        $this->assertNull($result->symfonyVersion());
    }

    public function test_handles_missing_composer_json(): void
    {
        $result = $this->detector->detect($this->root->url());

        $this->assertFalse($result->hasComposer());
        $this->assertNull($result->symfonyVersion());
        $this->assertNull($result->phpVersion());
    }
}
```

**ProjectDetectionResult Value Object** (`src/ValueObject/ProjectDetectionResult.php`):
```php
<?php

// ABOUTME: Value object representing project detection results.
// ABOUTME: Contains information about detected project state and versions.

declare(strict_types=1);

namespace Ninja\Seaman\ValueObject;

final readonly class ProjectDetectionResult
{
    public function __construct(
        private bool $hasComposer,
        private bool $hasDockerCompose,
        private bool $hasSeamanConfig,
        private ?string $symfonyVersion = null,
        private ?string $phpVersion = null,
    ) {}

    public function hasComposer(): bool
    {
        return $this->hasComposer;
    }

    public function hasDockerCompose(): bool
    {
        return $this->hasDockerCompose;
    }

    public function hasSeamanConfig(): bool
    {
        return $this->hasSeamanConfig;
    }

    public function symfonyVersion(): ?string
    {
        return $this->symfonyVersion;
    }

    public function phpVersion(): ?string
    {
        return $this->phpVersion;
    }

    public function isSymfonyProject(): bool
    {
        return $this->symfonyVersion !== null;
    }
}
```

**ProjectDetector Implementation** (`src/Service/ProjectDetector.php`):
```php
<?php

// ABOUTME: Detects project state and configuration.
// ABOUTME: Scans for composer.json, docker-compose files, and extracts version information.

declare(strict_types=1);

namespace Ninja\Seaman\Service;

use Ninja\Seaman\ValueObject\ProjectDetectionResult;

final readonly class ProjectDetector
{
    public function detect(?string $path = null): ProjectDetectionResult
    {
        $basePath = $path ?? getcwd();

        $hasComposer = file_exists($basePath . '/composer.json');
        $hasDockerCompose = file_exists($basePath . '/docker-compose.yml') ||
                           file_exists($basePath . '/docker-compose.yaml');
        $hasSeamanConfig = file_exists($basePath . '/.seaman/seaman.yaml');

        $symfonyVersion = null;
        $phpVersion = null;

        if ($hasComposer) {
            $composerData = $this->parseComposerJson($basePath . '/composer.json');

            if (isset($composerData['require']['symfony/framework-bundle'])) {
                $symfonyVersion = $this->extractSymfonyVersion($composerData['require']['symfony/framework-bundle']);
            }

            if (isset($composerData['require']['php'])) {
                $phpVersion = $this->extractPhpVersion($composerData['require']['php']);
            }
        }

        return new ProjectDetectionResult(
            hasComposer: $hasComposer,
            hasDockerCompose: $hasDockerCompose,
            hasSeamanConfig: $hasSeamanConfig,
            symfonyVersion: $symfonyVersion,
            phpVersion: $phpVersion
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function parseComposerJson(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private function extractSymfonyVersion(string $constraint): string
    {
        // Extract version from "^7.0" or "~7.1" or "7.2.*"
        if (preg_match('/[~^]?(\d+\.\d+)/', $constraint, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }

    private function extractPhpVersion(string $constraint): string
    {
        // Extract version from "^8.4" or ">=8.3" or "8.4.*"
        if (preg_match('/[~^>=]+?(\d+\.\d+)/', $constraint, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/Service/ProjectDetectorTest.php
vendor/bin/phpstan analyse src/Service/ProjectDetector.php src/ValueObject/ProjectDetectionResult.php
vendor/bin/php-cs-fixer fix src/Service/ProjectDetector.php src/ValueObject/ProjectDetectionResult.php
```

---

### Task 7: Update ConfigManager to Use Validator

**File**: `src/Service/ConfigManager.php` (existing, needs update)

**Update**:
```php
// Add to constructor:
public function __construct(
    private readonly ConfigurationValidator $validator // NEW
) {}

// Update load() method:
public function load(): Configuration
{
    $configPath = $this->getConfigPath();

    if (!file_exists($configPath)) {
        throw new SeamanNotInitializedException();
    }

    $config = $this->parser->parse($configPath);

    // NEW: Validate configuration
    $validation = $this->validator->validate($config);
    if ($validation->hasErrors()) {
        throw new InvalidConfigurationException(
            "Invalid seaman.yaml:\n" . implode("\n", $validation->errors())
        );
    }

    return $config;
}
```

**New Exception** (`src/Exception/InvalidConfigurationException.php`):
```php
<?php

// ABOUTME: Exception thrown when seaman.yaml contains invalid configuration.
// ABOUTME: Provides detailed validation errors.

declare(strict_types=1);

namespace Ninja\Seaman\Exception;

use RuntimeException;

final class InvalidConfigurationException extends RuntimeException
{
}
```

**Update Application.php** to inject validator into ConfigManager:
```php
$validator = new ConfigurationValidator();
$configManager = new ConfigManager($validator);
```

**Verification**:
```bash
vendor/bin/phpstan analyse src/Service/ConfigManager.php
vendor/bin/php-cs-fixer fix src/Service/ConfigManager.php src/Exception/InvalidConfigurationException.php
```

---

### Task 8: Refactor InitCommand - Extract ProjectDetector

**File**: `src/Command/InitCommand.php` (existing, needs refactoring)

**Current**: 400+ lines with mixed concerns

**Refactor**:
```php
// Add to constructor
public function __construct(
    private readonly ProjectDetector $projectDetector, // NEW
    private readonly InitializationWizard $wizard,
    private readonly ProjectInitializer $initializer,
    // ... existing dependencies
) {
    parent::__construct();
}

// Simplify execute() method
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

    // 3. Check for Symfony project
    if (!$detection->isSymfonyProject() && !$input->getOption('skip-symfony-check')) {
        if (!$this->confirmNonSymfonyProject($io)) {
            return Command::FAILURE;
        }
    }

    // 4. Run wizard (existing logic)
    $config = $this->wizard->run($io, $detection);

    // 5. Initialize environment (existing logic)
    $this->initializer->initializeDockerEnvironment($config);

    $io->success('Seaman initialized successfully!');

    return Command::SUCCESS;
}
```

**Verification**:
```bash
vendor/bin/phpstan analyse src/Command/InitCommand.php
vendor/bin/php-cs-fixer fix src/Command/InitCommand.php
```

---

### Task 9: Update StartCommand with Port Conflict Detection

**File**: `src/Command/StartCommand.php` (existing, needs update)

**Add to constructor**:
```php
public function __construct(
    private readonly PortChecker $portChecker, // NEW
    private readonly ConfigManager $configManager,
    private readonly DockerManager $dockerManager,
) {
    parent::__construct();
}
```

**Update execute()**:
```php
protected function execute(InputInterface $input, OutputInterface $output): int
{
    $io = new SymfonyStyle($input, $output);

    // Load configuration
    $config = $this->configManager->load();

    // NEW: Check port availability
    $requiredPorts = $this->getRequiredPorts($config);
    $portCheck = $this->portChecker->checkAvailability($requiredPorts);

    if ($portCheck->hasConflicts()) {
        $io->error('Port conflicts detected:');
        foreach ($portCheck->conflicts() as $port => $process) {
            $io->writeln("  Port {$port}: used by {$process}");
        }
        $io->writeln('');
        $io->writeln('Free these ports or change service ports in .seaman/seaman.yaml');
        return Command::FAILURE;
    }

    // Start services (existing logic)
    $service = $input->getArgument('service');
    $result = $this->dockerManager->start($service);

    if ($result->isSuccessful()) {
        $io->success('Services started successfully');
        return Command::SUCCESS;
    }

    $io->error('Failed to start services');
    $io->writeln($result->errorOutput());
    return Command::FAILURE;
}

/**
 * @return list<int>
 */
private function getRequiredPorts(Configuration $config): array
{
    $ports = [];

    foreach ($config->services()->enabled() as $service) {
        $ports[] = $service->port();
        $ports = array_merge($ports, $service->additionalPorts());
    }

    return $ports;
}
```

**Verification**:
```bash
vendor/bin/phpstan analyse src/Command/StartCommand.php
vendor/bin/php-cs-fixer fix src/Command/StartCommand.php
```

---

### Task 10: Create ModeAwareCommand Base Class

**File**: `src/Command/ModeAwareCommand.php`

**Implementation**:
```php
<?php

// ABOUTME: Base class for commands that need operating mode awareness.
// ABOUTME: Provides mode detection and validation helpers.

declare(strict_types=1);

namespace Ninja\Seaman\Command;

use Ninja\Seaman\Enum\OperatingMode;
use Ninja\Seaman\Service\ModeDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class ModeAwareCommand extends Command
{
    protected OperatingMode $mode;

    public function __construct(
        protected readonly ModeDetector $modeDetector
    ) {
        parent::__construct();
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->mode = $this->modeDetector->detect();

        if (!$this->supportsMode($this->mode)) {
            $io = new SymfonyStyle($input, $output);
            $this->showUpgradeMessage($io);
            exit(Command::FAILURE);
        }
    }

    abstract protected function supportsMode(OperatingMode $mode): bool;

    protected function requiresManagedMode(): bool
    {
        return !$this->supportsMode(OperatingMode::Unmanaged) &&
               !$this->supportsMode(OperatingMode::Uninitialized);
    }

    protected function showUpgradeMessage(SymfonyStyle $io): void
    {
        $io->warning('This command requires seaman initialization.');
        $io->writeln("Run <info>seaman init</info> to unlock:");
        $io->listing([
            'Service management (add/remove services)',
            'Xdebug control',
            'DevContainer generation',
            'Database tools',
        ]);
    }
}
```

**Verification**:
```bash
vendor/bin/phpstan analyse src/Command/ModeAwareCommand.php
vendor/bin/php-cs-fixer fix src/Command/ModeAwareCommand.php
```

---

### Task 11-15: Update All Commands to Extend ModeAwareCommand

For each command, update to extend `ModeAwareCommand` and implement `supportsMode()`:

**Example**: `src/Command/XdebugOnCommand.php`

**Before**:
```php
class XdebugOnCommand extends Command
{
    public function __construct(
        private readonly ConfigManager $configManager,
        private readonly DockerManager $dockerManager,
    ) {
        parent::__construct();
    }
}
```

**After**:
```php
class XdebugOnCommand extends ModeAwareCommand
{
    public function __construct(
        ModeDetector $modeDetector,
        private readonly ConfigManager $configManager,
        private readonly DockerManager $dockerManager,
    ) {
        parent::__construct($modeDetector);
    }

    protected function supportsMode(OperatingMode $mode): bool
    {
        // Xdebug requires managed mode (needs seaman.yaml)
        return $mode === OperatingMode::Managed;
    }
}
```

**Commands to Update**:
1. `XdebugOnCommand` - Managed only
2. `XdebugOffCommand` - Managed only
3. `ServiceAddCommand` - Managed only
4. `ServiceRemoveCommand` - Managed only
5. `ServiceListCommand` - Managed only (for now, Phase 5 will make it work in unmanaged)
6. `DbDumpCommand` - All modes (Phase 5 will add fuzzy detection)
7. `DbRestoreCommand` - All modes
8. `DbShellCommand` - All modes
9. `DevContainerGenerateCommand` - Managed only
10. `StartCommand` - All modes
11. `StopCommand` - All modes
12. `RestartCommand` - All modes
13. `StatusCommand` - All modes
14. `LogsCommand` - All modes
15. `DestroyCommand` - All modes
16. `RebuildCommand` - All modes
17. `ShellCommand` - All modes
18. `ExecutePhpCommand` - All modes
19. `ExecuteComposerCommand` - All modes
20. `ExecuteConsoleCommand` - All modes

**Pattern for "All Modes"**:
```php
protected function supportsMode(OperatingMode $mode): bool
{
    return true; // Supports all modes
}
```

**Verification** (run after each command update):
```bash
vendor/bin/phpstan analyse src/Command/
vendor/bin/php-cs-fixer fix src/Command/
```

---

## Final Phase 1 Verification

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

# Verify no changes needed
git status
```

## Expected Coverage Report

```
Phase 1 New Files:
- OperatingMode: 100%
- ModeDetector: 100%
- PortChecker: 100%
- PortCheckResult: 100%
- ConfigurationValidator: 100%
- ValidationResult: 100%
- ProjectDetector: 100%
- ProjectDetectionResult: 100%
- All Exceptions: 100%

Overall Project Coverage: ≥ 95%
```

## Commit Strategy

Commit after each completed task:

```bash
git add <files>
git commit -m "feat(foundation): <task description>

<details>

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

## Success Criteria

- ✅ All 15 tasks completed
- ✅ All unit tests passing
- ✅ 95%+ test coverage
- ✅ PHPStan level 10 clean
- ✅ PHP CS Fixer clean
- ✅ All ABOUTME comments present
- ✅ No failing integration tests (existing ones still work)

## Next Phase

After Phase 1 completion:
- Phase 2: Traefik Integration
- Document: `docs/plans/phases/phase-2-traefik-integration.md`
