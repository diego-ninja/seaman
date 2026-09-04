<?php

// ABOUTME: Integration tests for InitCommand.
// ABOUTME: Tests command instantiation and service integration.

declare(strict_types=1);

namespace Seaman\Tests\Integration\Command;

use Seaman\Application;
use Seaman\Command\InitCommand;
use Seaman\Enum\ProjectType;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\OnLifecycle;
use Seaman\Plugin\LifecycleEventData;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\PluginLifecycleDispatcher;
use Seaman\Plugin\PluginRegistry;
use Seaman\Service\ConfigurationFactory;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\Detector\SymfonyDetector;
use Seaman\Service\Detector\PhpVersionDetector;
use Seaman\Service\Detector\ProjectDetector;
use Seaman\Service\DnsManager;
use Seaman\Service\InitializationSummary;
use Seaman\Service\InitializationWizard;
use Seaman\Service\Process\RealCommandExecutor;
use Seaman\Service\ProjectInitializer;
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

test('import dispatches before init before creating the Compose backup', function (): void {
    assert(is_string($this->tempDir));
    mkdir($this->tempDir, 0755, true);
    chdir($this->tempDir);
    file_put_contents($this->tempDir . '/docker-compose.yml', <<<'YAML'
services:
  redis:
    image: redis:7-alpine
YAML);

    $backupExistsWhenHookRan = null;
    $plugin = new #[AsSeamanPlugin(name: 'backup-order-test')] class ($backupExistsWhenHookRan) implements PluginInterface {
        public function __construct(private ?bool &$backupExistsWhenHookRan) {}

        public function getName(): string
        {
            return 'backup-order-test';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Observes backup creation order';
        }

        #[OnLifecycle('before:init')]
        public function observeBackupState(LifecycleEventData $data): void
        {
            $backups = glob($data->projectRoot . '/docker-compose.yml.backup-*');
            $this->backupExistsWhenHookRan = $backups !== false && $backups !== [];

            throw new \RuntimeException('stop-after-before-init');
        }
    };

    $pluginRegistry = new PluginRegistry();
    $pluginRegistry->register($plugin, []);
    $serviceRegistry = ServiceRegistry::create();
    $executor = new RealCommandExecutor();
    $command = new InitCommand(
        projectDetector: new ProjectDetector(new SymfonyDetector()),
        bootstrapper: new SymfonyProjectBootstrapper(),
        configFactory: new ConfigurationFactory($serviceRegistry),
        summary: new InitializationSummary(),
        wizard: new InitializationWizard(new PhpVersionDetector()),
        initializer: new ProjectInitializer($serviceRegistry),
        dnsManager: new DnsManager($executor),
        lifecycleDispatcher: new PluginLifecycleDispatcher($pluginRegistry),
        projectRoot: $this->tempDir,
    );

    HeadlessMode::enable();
    HeadlessMode::preset([
        'How would you like to proceed?' => 'import',
        'Import these services?' => true,
        'Configure DNS for local development?' => false,
        'Continue with this configuration?' => true,
    ]);

    $tester = new CommandTester($command);

    expect(fn(): int => $tester->execute([]))
        ->toThrow(\RuntimeException::class, 'stop-after-before-init');
    expect($backupExistsWhenHookRan)->toBeFalse();
});

test('import fails safely when the Compose backup cannot be created', function (): void {
    assert(is_string($this->tempDir));
    mkdir($this->tempDir, 0755, true);
    chdir($this->tempDir);
    $composePath = $this->tempDir . '/docker-compose.yml';
    $originalCompose = <<<'YAML'
services:
  redis:
    image: redis:7-alpine
YAML;
    file_put_contents($composePath, $originalCompose);

    $pluginRegistry = new PluginRegistry();
    $serviceRegistry = ServiceRegistry::create();
    $executor = new RealCommandExecutor();
    $command = new InitCommand(
        projectDetector: new ProjectDetector(new SymfonyDetector()),
        bootstrapper: new SymfonyProjectBootstrapper(),
        configFactory: new ConfigurationFactory($serviceRegistry),
        summary: new InitializationSummary(),
        wizard: new InitializationWizard(new PhpVersionDetector()),
        initializer: new ProjectInitializer($serviceRegistry),
        dnsManager: new DnsManager($executor),
        lifecycleDispatcher: new PluginLifecycleDispatcher($pluginRegistry),
        projectRoot: $this->tempDir,
        fileCopier: static fn(string $source, string $target): bool => false,
    );

    HeadlessMode::enable();
    HeadlessMode::preset([
        'How would you like to proceed?' => 'import',
        'Import these services?' => true,
        'Configure DNS for local development?' => false,
        'Continue with this configuration?' => true,
    ]);

    $tester = new CommandTester($command);
    $status = $tester->execute([]);
    $backups = glob($composePath . '.backup-*');

    expect($status)->toBe(1)
        ->and(strtolower($tester->getDisplay()))->toContain('backup')
        ->and(file_get_contents($composePath))->toBe($originalCompose)
        ->and($backups)->toBe([])
        ->and(is_dir($this->tempDir . '/.seaman'))->toBeFalse();
});

test('standard initialization does not dispatch after init when confirmation is declined', function (): void {
    assert(is_string($this->tempDir));
    mkdir($this->tempDir, 0755, true);
    mkdir($this->tempDir . '/config');
    file_put_contents($this->tempDir . '/composer.json', json_encode([
        'require' => ['symfony/framework-bundle' => '^7.0'],
    ], JSON_THROW_ON_ERROR));
    chdir($this->tempDir);

    $afterInitCalls = 0;
    $plugin = new #[AsSeamanPlugin(name: 'cancelled-standard-test')] class ($afterInitCalls) implements PluginInterface {
        public function __construct(private int &$afterInitCalls) {}

        public function getName(): string
        {
            return 'cancelled-standard-test';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Observes standard initialization cancellation';
        }

        #[OnLifecycle('after:init')]
        public function recordAfterInit(): void
        {
            ++$this->afterInitCalls;
        }
    };

    $pluginRegistry = new PluginRegistry();
    $pluginRegistry->register($plugin, []);
    $serviceRegistry = ServiceRegistry::create();
    $command = new InitCommand(
        projectDetector: new ProjectDetector(new SymfonyDetector()),
        bootstrapper: new SymfonyProjectBootstrapper(),
        configFactory: new ConfigurationFactory($serviceRegistry),
        summary: new InitializationSummary(),
        wizard: new InitializationWizard(new PhpVersionDetector()),
        initializer: new ProjectInitializer($serviceRegistry),
        dnsManager: new DnsManager(new RealCommandExecutor()),
        lifecycleDispatcher: new PluginLifecycleDispatcher($pluginRegistry),
        projectRoot: $this->tempDir,
    );

    HeadlessMode::enable();
    HeadlessMode::preset([
        'Use Traefik as reverse proxy?' => false,
        'Continue with this configuration?' => false,
    ]);

    $tester = new CommandTester($command);
    $status = $tester->execute([]);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('Initialization cancelled')
        ->and($afterInitCalls)->toBe(0);
});

test('import initialization does not dispatch after init when confirmation is declined', function (): void {
    assert(is_string($this->tempDir));
    mkdir($this->tempDir, 0755, true);
    file_put_contents($this->tempDir . '/docker-compose.yml', <<<'YAML'
services:
  redis:
    image: redis:7-alpine
YAML);
    chdir($this->tempDir);

    $afterInitCalls = 0;
    $plugin = new #[AsSeamanPlugin(name: 'cancelled-import-test')] class ($afterInitCalls) implements PluginInterface {
        public function __construct(private int &$afterInitCalls) {}

        public function getName(): string
        {
            return 'cancelled-import-test';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Observes import initialization cancellation';
        }

        #[OnLifecycle('after:init')]
        public function recordAfterInit(): void
        {
            ++$this->afterInitCalls;
        }
    };

    $pluginRegistry = new PluginRegistry();
    $pluginRegistry->register($plugin, []);
    $serviceRegistry = ServiceRegistry::create();
    $command = new InitCommand(
        projectDetector: new ProjectDetector(new SymfonyDetector()),
        bootstrapper: new SymfonyProjectBootstrapper(),
        configFactory: new ConfigurationFactory($serviceRegistry),
        summary: new InitializationSummary(),
        wizard: new InitializationWizard(new PhpVersionDetector()),
        initializer: new ProjectInitializer($serviceRegistry),
        dnsManager: new DnsManager(new RealCommandExecutor()),
        lifecycleDispatcher: new PluginLifecycleDispatcher($pluginRegistry),
        projectRoot: $this->tempDir,
    );

    HeadlessMode::enable();
    HeadlessMode::preset([
        'How would you like to proceed?' => 'import',
        'Import these services?' => true,
        'Configure DNS for local development?' => false,
        'Continue with this configuration?' => false,
    ]);

    $tester = new CommandTester($command);
    $status = $tester->execute([]);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('Initialization cancelled')
        ->and($afterInitCalls)->toBe(0)
        ->and(glob($this->tempDir . '/docker-compose.yml.backup-*'))->toBe([]);
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

    $binDir = $this->tempDir . '/bin';
    mkdir($binDir);
    file_put_contents($binDir . '/docker', "#!/bin/sh\nexit 0\n");
    chmod($binDir . '/docker', 0755);
    $originalPath = getenv('PATH');
    putenv('PATH=' . $binDir . ':' . ($originalPath === false ? '' : $originalPath));

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

    try {
        $app = new Application();
        $tester = new CommandTester($app->find('init'));
        $tester->execute([]);
    } finally {
        $originalPath === false ? putenv('PATH') : putenv('PATH=' . $originalPath);
    }

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
