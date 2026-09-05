<?php

declare(strict_types=1);

// ABOUTME: Tests for DockerManager service.
// ABOUTME: Validates docker-compose command execution and error handling.

namespace Seaman\Tests\Unit\Service;

use Seaman\Service\DockerManager;
use Seaman\ValueObject\LogOptions;
use Seaman\ValueObject\ProcessResult;
use Symfony\Component\Yaml\Yaml;

/**
 * @property string $tempDir
 * @property DockerManager $manager
 */
beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->tempDir . '/.seaman', 0755, true);

    // Create a minimal docker-compose.yml for testing (now in root)
    $composeContent = <<<YAML
services:
  web:
    image: nginx:latest
  db:
    image: mysql:8.0
YAML;

    file_put_contents($this->tempDir . '/docker-compose.yml', $composeContent);
    $this->originalPath = getenv('PATH');
    $binDir = $this->tempDir . '/bin';
    mkdir($binDir);
    file_put_contents($binDir . '/docker', "#!/bin/sh\nexit 1\n");
    chmod($binDir . '/docker', 0755);
    putenv('PATH=' . $binDir . ':' . ($this->originalPath === false ? '' : $this->originalPath));
    $this->manager = new DockerManager($this->tempDir);
});

afterEach(function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    $this->originalPath === false ? putenv('PATH') : putenv('PATH=' . $this->originalPath);
    if (is_dir($tempDir)) {
        // Remove all files recursively
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($tempDir);
    }
});

test('start returns ProcessResult for all services', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->start();

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
    expect($result->output)->toBeString();
    expect($result->errorOutput)->toBeString();
});

test('start returns ProcessResult for specific service', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->start('web');

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('stop returns ProcessResult for all services', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->stop();

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('stop returns ProcessResult for specific service', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->stop('web');

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('restart returns ProcessResult for all services', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->restart();

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('restart returns ProcessResult for specific service', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->restart('db');

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('start and restart migrate legacy Traefik config without losing content', function (string $operation): void {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    $traefikDir = $tempDir . '/.seaman/traefik';
    $traefikConfig = $traefikDir . '/traefik.yml';
    mkdir($traefikDir);
    file_put_contents($traefikConfig, <<<YAML
api:
  dashboard: true
providers:
  docker:
    exposedByDefault: false
log:
  level: DEBUG
YAML);

    /** @var DockerManager $manager */
    $manager = $this->manager;
    $manager->{$operation}();

    $migratedConfig = file_get_contents($traefikConfig);

    expect($migratedConfig)
        ->toContain('dashboard: true')
        ->toContain('exposedByDefault: false')
        ->toContain('level: DEBUG')
        ->toContain('ping: {}')
        ->and(substr_count($migratedConfig, 'ping:'))
        ->toBe(1);

    $manager->{$operation}();

    expect(file_get_contents($traefikConfig))->toBe($migratedConfig);
})->with(['start', 'restart']);

test('start and restart leave quoted top-level ping config unchanged', function (
    string $operation,
    string $pingEntry,
): void {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    $traefikDir = $tempDir . '/.seaman/traefik';
    $traefikConfig = $traefikDir . '/traefik.yml';
    mkdir($traefikDir);
    $originalConfig = <<<YAML
api:
  dashboard: true
{$pingEntry}
YAML;
    file_put_contents($traefikConfig, $originalConfig);

    /** @var DockerManager $manager */
    $manager = $this->manager;
    $manager->{$operation}();

    expect(file_get_contents($traefikConfig))->toBe($originalConfig);
})->with([
    'start with double-quoted ping' => ['start', '"ping": {}'],
    'start with single-quoted ping' => ['start', "'ping': {}"],
    'restart with double-quoted ping' => ['restart', '"ping": {}'],
    'restart with single-quoted ping' => ['restart', "'ping': {}"],
]);

test('start and restart insert ping before a YAML document end marker', function (
    string $operation,
    string $suffix,
): void {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    $traefikDir = $tempDir . '/.seaman/traefik';
    $traefikConfig = $traefikDir . '/traefik.yml';
    mkdir($traefikDir);
    $legacyContent = <<<YAML
api:
  dashboard: true
providers:
  docker:
    exposedByDefault: false
YAML;
    file_put_contents($traefikConfig, $legacyContent . "\n" . $suffix);

    /** @var DockerManager $manager */
    $manager = $this->manager;
    $manager->{$operation}();

    $migratedConfig = (string) file_get_contents($traefikConfig);

    expect($migratedConfig)
        ->toStartWith($legacyContent)
        ->toEndWith($suffix)
        ->and(substr_count($migratedConfig, 'ping: {}'))
        ->toBe(1)
        ->and(strpos($migratedConfig, 'ping: {}'))
        ->toBeLessThan(strlen($migratedConfig) - strlen($suffix));

    $markerLineLength = strpos($suffix, "\n") + 1;
    $parseableConfig = substr($migratedConfig, 0, -strlen($suffix))
        . substr($suffix, $markerLineLength);

    expect(Yaml::parse($parseableConfig))->toBe([
        'api' => ['dashboard' => true],
        'providers' => ['docker' => ['exposedByDefault' => false]],
        'ping' => [],
    ]);

    $manager->{$operation}();

    expect(file_get_contents($traefikConfig))->toBe($migratedConfig);
})->with([
    'start with terminal marker' => ['start', "...\n"],
    'start with trailing blank line' => ['start', "...\n\n"],
    'start with inline marker comment' => ['start', "... # end\n"],
    'start with trailing comment' => ['start', "...\n# comentario\n"],
    'restart with terminal marker' => ['restart', "...\n"],
    'restart with trailing blank line' => ['restart', "...\n\n"],
    'restart with inline marker comment' => ['restart', "... # end\n"],
    'restart with trailing comment' => ['restart', "...\n# comentario\n"],
]);

test('executeInService runs command in service container', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->executeInService('web', ['ls', '-la']);

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('executeInService handles single command argument', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->executeInService('web', ['pwd']);

    expect($result)->toBeInstanceOf(ProcessResult::class);
});

test('executeInteractive returns an integer exit code', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    // We can't test interactivity in a unit test,
    // but we can check if it returns an exit code.
    // We run a non-blocking command.
    $exitCode = $manager->executeInteractive('web', ['/bin/true']);

    expect($exitCode)->toBeInt();
});

test('logs returns ProcessResult with default options', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->logs('web', new LogOptions());

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('logs returns ProcessResult with follow option', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->logs('web', new LogOptions(follow: true));

    expect($result)->toBeInstanceOf(ProcessResult::class);
});

test('logs returns ProcessResult with tail option', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->logs('web', new LogOptions(tail: 100));

    expect($result)->toBeInstanceOf(ProcessResult::class);
});

test('logs returns ProcessResult with since option', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->logs('web', new LogOptions(since: '1h'));

    expect($result)->toBeInstanceOf(ProcessResult::class);
});

test('logs returns ProcessResult with all options combined', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->logs('web', new LogOptions(
        follow: true,
        tail: 50,
        since: '30m',
    ));

    expect($result)->toBeInstanceOf(ProcessResult::class);
});

test('throws exception when docker-compose.yml does not exist', function () {
    $invalidDir = sys_get_temp_dir() . '/seaman-invalid-' . uniqid();
    mkdir($invalidDir . '/.seaman', 0755, true);

    $manager = new DockerManager($invalidDir);

    try {
        $manager->start();
    } finally {
        // Cleanup
        rmdir($invalidDir . '/.seaman');
        rmdir($invalidDir);
    }
})->throws(\RuntimeException::class);

test('handles process failure gracefully', function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;

    // Create an invalid docker-compose.yml that will fail
    $invalidContent = <<<YAML
invalid yaml content [[[
  this will cause docker-compose to fail
YAML;

    file_put_contents($tempDir . '/docker-compose.yml', $invalidContent);

    $manager = new DockerManager($tempDir);
    $result = $manager->start();

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->isSuccessful())->toBeFalse();
});

test('status returns array with container status', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->status();

    expect($result)->toBeArray();
});

test('rebuild returns ProcessResult for all services', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->rebuild();

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('rebuild returns ProcessResult for specific service', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->rebuild('web');

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('destroy returns ProcessResult', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->destroy();

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('accepts docker compose yaml extension', function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    rename($tempDir . '/docker-compose.yml', $tempDir . '/docker-compose.yaml');

    $manager = new DockerManager($tempDir);

    expect($manager->start())->toBeInstanceOf(ProcessResult::class);
});

test('uses Docker Compose V2 command', function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    $binDir = $tempDir . '/bin';
    $argumentsFile = $tempDir . '/arguments';
    file_put_contents($binDir . '/docker', "#!/bin/sh\nprintf '%s' \"\$*\" > " . escapeshellarg($argumentsFile) . "\n");
    chmod($binDir . '/docker', 0755);

    $originalPath = getenv('PATH');
    putenv('PATH=' . $binDir . ':' . ($originalPath === false ? '' : $originalPath));

    try {
        $manager = new DockerManager($tempDir);
        $result = $manager->start('web');
    } finally {
        $originalPath === false ? putenv('PATH') : putenv('PATH=' . $originalPath);
    }

    expect($result->isSuccessful())->toBeTrue()
        ->and(file_get_contents($argumentsFile))->toBe(
            'compose -f ' . $tempDir . '/docker-compose.yml up -d web',
        );
});

test('keeps the final status record without a trailing newline', function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    $binDir = $tempDir . '/bin';
    file_put_contents(
        $binDir . '/docker',
        "#!/bin/sh\nprintf '%s' '{\"Service\":\"web\",\"State\":\"running\"}'\n",
    );
    chmod($binDir . '/docker', 0755);

    $originalPath = getenv('PATH');
    putenv('PATH=' . $binDir . ':' . ($originalPath === false ? '' : $originalPath));

    try {
        $manager = new DockerManager($tempDir);
        $status = $manager->status();
    } finally {
        $originalPath === false ? putenv('PATH') : putenv('PATH=' . $originalPath);
    }

    expect($status)->toBe([['Service' => 'web', 'State' => 'running']]);
});

test('reports non-follow process timeouts as failures', function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    $binDir = $tempDir . '/bin';
    file_put_contents($binDir . '/docker', "#!/bin/sh\nsleep 1\n");
    chmod($binDir . '/docker', 0755);

    $originalPath = getenv('PATH');
    putenv('PATH=' . $binDir . ':' . ($originalPath === false ? '' : $originalPath));

    try {
        $manager = new DockerManager($tempDir);
        $result = $manager->executeInService('web', ['true'], timeout: 0.01);
    } finally {
        $originalPath === false ? putenv('PATH') : putenv('PATH=' . $originalPath);
    }

    expect($result->isSuccessful())->toBeFalse()
        ->and($result->exitCode)->toBe(124);
});
