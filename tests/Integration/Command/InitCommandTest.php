<?php

// ABOUTME: Integration tests for InitCommand.
// ABOUTME: Tests command instantiation and service integration.

declare(strict_types=1);

namespace Tests\Integration\Command;

use Seaman\Application;
use Seaman\Enum\ProjectType;
use Seaman\Service\Detector\SymfonyDetector;
use Seaman\Service\SymfonyProjectBootstrapper;
use Seaman\UI\HeadlessMode;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function (): void {
    HeadlessMode::reset();
    $this->tempDir = sys_get_temp_dir() . '/seaman-init-test-' . uniqid();
    $this->originalDir = getcwd();
});

afterEach(function (): void {
    HeadlessMode::reset();
    if (isset($this->tempDir) && is_string($this->tempDir) && is_dir($this->tempDir)) {
        if (isset($this->originalDir) && is_string($this->originalDir)) {
            chdir($this->originalDir);
        }
        exec('rm -rf ' . escapeshellarg($this->tempDir));
    }
});

test('init command is registered in application', function (): void {
    $app = new Application();
    $command = $app->find('init');

    expect($command)->toBeInstanceOf(\Seaman\Command\InitCommand::class);
    expect($command->getName())->toBe('seaman:init');
});

test('init command has correct aliases', function (): void {
    $app = new Application();
    $command = $app->find('init');

    expect($command->getAliases())->toContain('init');
});

test('symfony detector works correctly', function (): void {
    $detector = new SymfonyDetector();

    $tempDir = sys_get_temp_dir() . '/test-detector-' . uniqid();
    mkdir($tempDir);

    $result = $detector->detect($tempDir);
    expect($result->isSymfonyProject)->toBeFalse();

    rmdir($tempDir);
});

test('project bootstrapper generates correct commands', function (): void {
    $bootstrapper = new SymfonyProjectBootstrapper();

    $command = $bootstrapper->getBootstrapCommand(
        ProjectType::WebApplication,
        'test-app',
        '/tmp',
    );

    expect($command)->toContain('symfony');
    expect($command)->toContain('new');
    expect($command)->toContain('test-app');
});

test('init command creates configuration with preset responses', function (): void {
    // Create minimal Symfony project structure
    assert(is_string($this->tempDir));
    mkdir($this->tempDir, 0755, true);
    chdir($this->tempDir);

    file_put_contents($this->tempDir . '/composer.json', json_encode([
        'require' => ['symfony/framework-bundle' => '^7.0'],
    ]));
    mkdir($this->tempDir . '/src');
    mkdir($this->tempDir . '/config');

    HeadlessMode::enable();
    HeadlessMode::preset([
        'Select PHP version (default: 8.4)' => '8.4',
        'Select database (default: postgresql)' => 'mysql',
        'Select additional services' => ['redis'],
        'Do you want to enable Xdebug?' => false,
        'Use Traefik as reverse proxy?' => false,
        'Do you want to enable DevContainer support?' => false,
        'Continue with this configuration?' => true,
    ]);

    $app = new Application();
    $tester = new CommandTester($app->find('init'));
    $tester->execute([]);

    expect(file_exists($this->tempDir . '/.seaman/seaman.yaml'))->toBeTrue();
});

test('init command uses the generated child directory as the effective root', function (): void {
    assert(is_string($this->tempDir));
    mkdir($this->tempDir, 0755, true);
    file_put_contents($this->tempDir . '/README.md', 'parent directory content');
    chdir($this->tempDir);

    $binDir = $this->tempDir . '/bin';
    mkdir($binDir);
    file_put_contents($binDir . '/docker', "#!/bin/sh\nexit 0\n");
    file_put_contents($binDir . '/symfony', <<<'SH'
#!/bin/sh
if [ "$1" = "new" ]; then
    mkdir -p "$2/src" "$2/config"
    printf '%s' '{"require":{"symfony/framework-bundle":"^7.0"}}' > "$2/composer.json"
fi
exit 0
SH);
    chmod($binDir . '/docker', 0755);
    chmod($binDir . '/symfony', 0755);

    $originalPath = getenv('PATH');
    putenv('PATH=' . $binDir . ':' . ($originalPath === false ? '' : $originalPath));

    HeadlessMode::enable();
    HeadlessMode::preset([
        'No Symfony application detected. Create new project?' => true,
        'Select project type' => 'skeleton',
        'Project directory name' => 'generated-app',
        'Select PHP version (default: 8.4)' => '8.4',
        'Select database (default: postgresql)' => 'postgresql',
        'Select additional services' => [],
        'Do you want to enable Xdebug?' => false,
        'Use Traefik as reverse proxy?' => false,
        'Do you want to enable DevContainer support?' => false,
        'Continue with this configuration?' => true,
    ]);

    try {
        $app = new Application();
        $tester = new CommandTester($app->find('init'));
        $tester->execute([]);
    } finally {
        $originalPath === false ? putenv('PATH') : putenv('PATH=' . $originalPath);
    }

    $targetRoot = $this->tempDir . '/generated-app';
    expect($tester->getStatusCode())->toBe(0)
        ->and(file_exists($targetRoot . '/.seaman/seaman.yaml'))->toBeTrue()
        ->and(file_exists($targetRoot . '/docker-compose.yml'))->toBeTrue()
        ->and(file_exists($this->tempDir . '/.seaman/seaman.yaml'))->toBeFalse()
        ->and(file_get_contents($targetRoot . '/.seaman/seaman.yaml'))->toContain('project_name: generated-app');
});
