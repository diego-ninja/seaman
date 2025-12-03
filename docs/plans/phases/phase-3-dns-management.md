# Phase 3: DNS Management - Implementation Plan

**Phase**: 3 of 6
**Goal**: Help users configure DNS for *.local domains
**Dependencies**: Phase 1 (Foundation), Phase 2 (Traefik Integration)
**Estimated Tasks**: 6 tasks
**Testing Strategy**: TDD for services, 95%+ unit test coverage

## Overview

This phase adds smart DNS configuration to make Traefik domains work seamlessly. Automatically detects dnsmasq/systemd-resolved or provides clear manual instructions.

## Prerequisites

- Phase 1 and 2 completed and committed
- All previous tests passing
- Working in `.worktrees/dual-mode-traefik-import` branch

## Implementation Tasks

### Task 1: Create DnsConfigurationResult Value Object

**File**: `src/ValueObject/DnsConfigurationResult.php`

**Test First** (`tests/Unit/ValueObject/DnsConfigurationResultTest.php`):
```php
<?php

// ABOUTME: Tests for DnsConfigurationResult value object.
// ABOUTME: Validates DNS configuration result data.

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use Ninja\Seaman\ValueObject\DnsConfigurationResult;
use PHPUnit\Framework\TestCase;

final class DnsConfigurationResultTest extends TestCase
{
    public function test_creates_dnsmasq_result(): void
    {
        $result = new DnsConfigurationResult(
            type: 'dnsmasq',
            automatic: true,
            requiresSudo: true,
            configPath: '/etc/dnsmasq.d/seaman-test.conf',
            configContent: 'address=/.test.local/127.0.0.1'
        );

        $this->assertSame('dnsmasq', $result->type);
        $this->assertTrue($result->automatic);
        $this->assertTrue($result->requiresSudo);
        $this->assertSame('/etc/dnsmasq.d/seaman-test.conf', $result->configPath);
    }

    public function test_creates_manual_result(): void
    {
        $hostsEntries = [
            '127.0.0.1 app.test.local',
            '127.0.0.1 traefik.test.local'
        ];

        $result = new DnsConfigurationResult(
            type: 'manual',
            automatic: false,
            hostsEntries: $hostsEntries
        );

        $this->assertSame('manual', $result->type);
        $this->assertFalse($result->automatic);
        $this->assertSame($hostsEntries, $result->hostsEntries);
    }

    public function test_is_dnsmasq_helper(): void
    {
        $dnsmasq = new DnsConfigurationResult('dnsmasq', true, true);
        $manual = new DnsConfigurationResult('manual', false);

        $this->assertTrue($dnsmasq->isDnsmasq());
        $this->assertFalse($manual->isDnsmasq());
    }

    public function test_is_manual_helper(): void
    {
        $dnsmasq = new DnsConfigurationResult('dnsmasq', true, true);
        $manual = new DnsConfigurationResult('manual', false);

        $this->assertFalse($dnsmasq->isManual());
        $this->assertTrue($manual->isManual());
    }
}
```

**Implementation**:
```php
<?php

// ABOUTME: Value object representing DNS configuration result.
// ABOUTME: Contains configuration type, paths, and content for different DNS strategies.

declare(strict_types=1);

namespace Ninja\Seaman\ValueObject;

final readonly class DnsConfigurationResult
{
    /**
     * @param list<string> $hostsEntries
     */
    public function __construct(
        public string $type,
        public bool $automatic,
        public bool $requiresSudo = false,
        public ?string $configPath = null,
        public ?string $configContent = null,
        public array $hostsEntries = [],
    ) {}

    public function isDnsmasq(): bool
    {
        return $this->type === 'dnsmasq';
    }

    public function isSystemdResolved(): bool
    {
        return $this->type === 'systemd-resolved';
    }

    public function isManual(): bool
    {
        return $this->type === 'manual';
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/ValueObject/DnsConfigurationResultTest.php
vendor/bin/phpstan analyse src/ValueObject/DnsConfigurationResult.php
vendor/bin/php-cs-fixer fix src/ValueObject/DnsConfigurationResult.php
```

---

### Task 2: Create DnsConfigurationHelper Service

**File**: `src/Service/DnsConfigurationHelper.php`

**Test First** (`tests/Unit/Service/DnsConfigurationHelperTest.php`):
```php
<?php

// ABOUTME: Tests for DnsConfigurationHelper service.
// ABOUTME: Validates DNS configuration detection and helper generation.

declare(strict_types=1);

namespace Tests\Unit\Service;

use Ninja\Seaman\Service\DnsConfigurationHelper;
use Ninja\Seaman\ValueObject\Configuration;
use Ninja\Seaman\ValueObject\ProxyConfig;
use PHPUnit\Framework\TestCase;

final class DnsConfigurationHelperTest extends TestCase
{
    private DnsConfigurationHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new DnsConfigurationHelper();
    }

    public function test_detects_dnsmasq_when_available(): void
    {
        if (!$this->isDnsmasqInstalled()) {
            $this->markTestSkipped('dnsmasq not installed');
        }

        $this->assertTrue($this->helper->hasDnsmasq());
    }

    public function test_generates_manual_instructions_when_no_tools_available(): void
    {
        // Most test environments won't have dnsmasq
        $result = $this->helper->configure('testproject', ['app', 'traefik', 'mailpit']);

        if (!$this->isDnsmasqInstalled()) {
            $this->assertTrue($result->isManual());
            $this->assertFalse($result->automatic);
            $this->assertCount(3, $result->hostsEntries);
            $this->assertContains('127.0.0.1 app.testproject.local', $result->hostsEntries);
        }
    }

    public function test_generates_dnsmasq_config_content(): void
    {
        $result = $this->helper->offerDnsmasqSetup('myproject');

        $this->assertSame('dnsmasq', $result->type);
        $this->assertTrue($result->automatic);
        $this->assertTrue($result->requiresSudo);
        $this->assertStringContainsString('address=/.myproject.local/127.0.0.1', $result->configContent);
    }

    public function test_gets_correct_dnsmasq_path_for_linux(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('Linux-specific test');
        }

        $path = $this->helper->getDnsmasqConfigPath('testproject');

        $this->assertStringContainsString('/etc/dnsmasq.d/seaman-testproject.conf', $path);
    }

    public function test_gets_correct_dnsmasq_path_for_darwin(): void
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            $this->markTestSkipped('macOS-specific test');
        }

        $path = $this->helper->getDnsmasqConfigPath('testproject');

        $this->assertStringContainsString('/usr/local/etc/dnsmasq.d/seaman-testproject.conf', $path);
    }

    private function isDnsmasqInstalled(): bool
    {
        return $this->helper->hasDnsmasq();
    }
}
```

**Implementation**:
```php
<?php

// ABOUTME: Manages DNS configuration for Traefik domains.
// ABOUTME: Detects dnsmasq/systemd-resolved or provides manual instructions.

declare(strict_types=1);

namespace Ninja\Seaman\Service;

use Ninja\Seaman\ValueObject\DnsConfigurationResult;
use Symfony\Component\Process\Process;

final readonly class DnsConfigurationHelper
{
    /**
     * @param list<string> $services Service names that need DNS entries
     */
    public function configure(string $projectName, array $services = []): DnsConfigurationResult
    {
        // Default services if none provided
        if (empty($services)) {
            $services = ['app', 'traefik'];
        }

        // Check for dnsmasq
        if ($this->hasDnsmasq()) {
            return $this->offerDnsmasqSetup($projectName);
        }

        // Check for systemd-resolved (Linux)
        if ($this->hasSystemdResolved()) {
            return $this->offerSystemdResolvedSetup($projectName);
        }

        // Fallback to manual instructions
        return $this->showManualInstructions($projectName, $services);
    }

    public function hasDnsmasq(): bool
    {
        $process = new Process(['which', 'dnsmasq']);
        $process->run();
        return $process->isSuccessful();
    }

    public function hasSystemdResolved(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return false;
        }

        $process = new Process(['systemctl', 'is-active', 'systemd-resolved']);
        $process->run();
        return $process->isSuccessful();
    }

    public function offerDnsmasqSetup(string $projectName): DnsConfigurationResult
    {
        $configPath = $this->getDnsmasqConfigPath($projectName);
        $configContent = "address=/.{$projectName}.local/127.0.0.1\n";

        return new DnsConfigurationResult(
            type: 'dnsmasq',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: $configContent
        );
    }

    public function offerSystemdResolvedSetup(string $projectName): DnsConfigurationResult
    {
        $configPath = "/etc/systemd/resolved.conf.d/seaman-{$projectName}.conf";
        $configContent = "[Resolve]\nDNS=127.0.0.1\nDomains=~{$projectName}.local\n";

        return new DnsConfigurationResult(
            type: 'systemd-resolved',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: $configContent
        );
    }

    /**
     * @param list<string> $services
     */
    public function showManualInstructions(string $projectName, array $services): DnsConfigurationResult
    {
        $hostsEntries = array_map(
            fn($service) => "127.0.0.1 {$service}.{$projectName}.local",
            $services
        );

        return new DnsConfigurationResult(
            type: 'manual',
            automatic: false,
            hostsEntries: $hostsEntries
        );
    }

    public function getDnsmasqConfigPath(string $projectName): string
    {
        return match (PHP_OS_FAMILY) {
            'Linux' => "/etc/dnsmasq.d/seaman-{$projectName}.conf",
            'Darwin' => "/usr/local/etc/dnsmasq.d/seaman-{$projectName}.conf",
            default => throw new \RuntimeException('Unsupported platform for automatic DNS')
        };
    }

    public function writeDnsmasqConfig(DnsConfigurationResult $result): void
    {
        if (!$result->isDnsmasq() || $result->configPath === null || $result->configContent === null) {
            throw new \InvalidArgumentException('Invalid DNS configuration result for dnsmasq');
        }

        $dir = dirname($result->configPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($result->configPath, $result->configContent);
    }

    public function removeDnsmasqConfig(string $projectName): bool
    {
        $configPath = $this->getDnsmasqConfigPath($projectName);

        if (file_exists($configPath)) {
            return unlink($configPath);
        }

        return false;
    }

    public function restartDnsmasq(): void
    {
        $process = match (PHP_OS_FAMILY) {
            'Linux' => new Process(['sudo', 'systemctl', 'restart', 'dnsmasq']),
            'Darwin' => new Process(['sudo', 'brew', 'services', 'restart', 'dnsmasq']),
            default => throw new \RuntimeException('Unsupported platform')
        };

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Failed to restart dnsmasq: ' . $process->getErrorOutput());
        }
    }
}
```

**Verification**:
```bash
vendor/bin/pest tests/Unit/Service/DnsConfigurationHelperTest.php
vendor/bin/phpstan analyse src/Service/DnsConfigurationHelper.php
vendor/bin/php-cs-fixer fix src/Service/DnsConfigurationHelper.php
```

---

### Task 3: Update InitCommand with DNS Configuration Flow

**File**: `src/Command/InitCommand.php` (existing, needs update)

**Add to constructor**:
```php
public function __construct(
    // ... existing
    private readonly DnsConfigurationHelper $dnsHelper, // NEW
) {
    parent::__construct();
}
```

**Add after Traefik configuration**:
```php
// Configure DNS
$io->section('DNS Configuration');

$services = array_map(
    fn($s) => $s->name(),
    iterator_to_array($config->services()->enabled())
);
$services[] = 'app';
$services[] = 'traefik';

$dnsResult = $this->dnsHelper->configure($config->proxy()->domainPrefix(), $services);

if ($dnsResult->isDnsmasq()) {
    $this->configureDnsmasq($io, $dnsResult);
} elseif ($dnsResult->isSystemdResolved()) {
    $this->configureSystemdResolved($io, $dnsResult);
} else {
    $this->showManualDnsInstructions($io, $dnsResult);
}
```

**Add methods**:
```php
private function configureDnsmasq(SymfonyStyle $io, DnsConfigurationResult $result): void
{
    $io->note('dnsmasq detected - automatic DNS configuration available');

    $choice = $io->choice(
        'Configure DNS automatically?',
        ['Automatic (requires sudo)', 'Manual (show instructions)', 'Skip'],
        'Automatic (requires sudo)'
    );

    if ($choice === 'Skip') {
        $io->info('Skipped DNS configuration. Run "seaman proxy:configure-dns" later.');
        return;
    }

    if ($choice === 'Manual (show instructions)') {
        $this->showManualDnsInstructions($io, $result);
        return;
    }

    // Automatic configuration
    $io->writeln('');
    $io->writeln('This requires sudo access to write to:');
    $io->writeln("  {$result->configPath}");
    $io->writeln('');
    $io->writeln('Content:');
    $io->writeln("  {$result->configContent}");
    $io->writeln('');

    if (!$io->confirm('Continue?', true)) {
        $this->showManualDnsInstructions($io, $result);
        return;
    }

    try {
        $this->dnsHelper->writeDnsmasqConfig($result);
        $this->dnsHelper->restartDnsmasq();
        $io->success("DNS configured! All *.{$result->configContent} domains now resolve to 127.0.0.1");
    } catch (\Exception $e) {
        $io->error('Failed to configure DNS: ' . $e->getMessage());
        $this->showManualDnsInstructions($io, $result);
    }
}

private function configureSystemdResolved(SymfonyStyle $io, DnsConfigurationResult $result): void
{
    // Similar to dnsmasq but for systemd-resolved
    $io->note('systemd-resolved detected - automatic DNS configuration available');
    // Implementation similar to configureDnsmasq
}

private function showManualDnsInstructions(SymfonyStyle $io, DnsConfigurationResult $result): void
{
    $io->section('Manual DNS Configuration');
    $io->writeln('Add these entries to /etc/hosts:');
    $io->writeln('');

    foreach ($result->hostsEntries as $entry) {
        $io->writeln("  {$entry}");
    }

    $io->writeln('');
    $io->writeln('On Linux/macOS:');
    $io->writeln('  sudo nano /etc/hosts');
    $io->writeln('');
    $io->writeln('On Windows:');
    $io->writeln('  notepad C:\\Windows\\System32\\drivers\\etc\\hosts');
    $io->writeln('');
}
```

**Verification**:
```bash
vendor/bin/phpstan analyse src/Command/InitCommand.php
vendor/bin/php-cs-fixer fix src/Command/InitCommand.php
```

---

### Task 4: Create ProxyConfigureDnsCommand

**File**: `src/Command/ProxyConfigureDnsCommand.php`

**Implementation** (no unit test, this is a command):
```php
<?php

// ABOUTME: Command to configure DNS for Traefik domains after initialization.
// ABOUTME: Provides interactive DNS setup with automatic or manual options.

declare(strict_types=1);

namespace Ninja\Seaman\Command;

use Ninja\Seaman\Enum\OperatingMode;
use Ninja\Seaman\Service\ConfigManager;
use Ninja\Seaman\Service\DnsConfigurationHelper;
use Ninja\Seaman\Service\ModeDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ProxyConfigureDnsCommand extends ModeAwareCommand
{
    public function __construct(
        ModeDetector $modeDetector,
        private readonly ConfigManager $configManager,
        private readonly DnsConfigurationHelper $dnsHelper,
    ) {
        parent::__construct($modeDetector);
    }

    protected function configure(): void
    {
        $this
            ->setName('proxy:configure-dns')
            ->setDescription('Configure DNS for Traefik domains')
            ->setHelp('Sets up DNS resolution for *.{project}.local domains using dnsmasq, systemd-resolved, or manual /etc/hosts entries.');
    }

    protected function supportsMode(OperatingMode $mode): bool
    {
        return $mode === OperatingMode::Managed;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $config = $this->configManager->load();
        $projectName = $config->proxy()->domainPrefix();

        $services = array_map(
            fn($s) => $s->name(),
            iterator_to_array($config->services()->enabled())
        );
        $services[] = 'app';
        $services[] = 'traefik';

        $dnsResult = $this->dnsHelper->configure($projectName, $services);

        if ($dnsResult->isDnsmasq()) {
            $this->configureDnsmasq($io, $dnsResult);
        } elseif ($dnsResult->isSystemdResolved()) {
            $this->configureSystemdResolved($io, $dnsResult);
        } else {
            $this->showManualInstructions($io, $dnsResult);
        }

        return Command::SUCCESS;
    }

    private function configureDnsmasq(SymfonyStyle $io, $result): void
    {
        $io->title('Configure DNS with dnsmasq');
        $io->writeln("Configuration file: {$result->configPath}");
        $io->writeln("Content: {$result->configContent}");

        if (!$io->confirm('Write configuration and restart dnsmasq? (requires sudo)', true)) {
            $this->showManualInstructions($io, $result);
            return;
        }

        try {
            $this->dnsHelper->writeDnsmasqConfig($result);
            $this->dnsHelper->restartDnsmasq();
            $io->success('DNS configured successfully!');
        } catch (\Exception $e) {
            $io->error('Failed: ' . $e->getMessage());
            $this->showManualInstructions($io, $result);
        }
    }

    private function configureSystemdResolved(SymfonyStyle $io, $result): void
    {
        // Similar implementation for systemd-resolved
        $io->warning('systemd-resolved configuration not yet implemented');
        $this->showManualInstructions($io, $result);
    }

    private function showManualInstructions(SymfonyStyle $io, $result): void
    {
        $io->section('Manual DNS Configuration');
        $io->writeln('Add these entries to /etc/hosts:');
        $io->newLine();
        $io->listing($result->hostsEntries);
        $io->newLine();
        $io->writeln('<comment>Linux/macOS:</comment> sudo nano /etc/hosts');
        $io->writeln('<comment>Windows:</comment> notepad C:\\Windows\\System32\\drivers\\etc\\hosts');
    }
}
```

**Register in Application.php**:
```php
$application->add(new ProxyConfigureDnsCommand($modeDetector, $configManager, $dnsHelper));
```

**Verification**:
```bash
vendor/bin/phpstan analyse src/Command/ProxyConfigureDnsCommand.php
vendor/bin/php-cs-fixer fix src/Command/ProxyConfigureDnsCommand.php
```

---

### Task 5: Update DestroyCommand with DNS Cleanup

**File**: `src/Command/DestroyCommand.php` (existing, needs update)

**Add to constructor**:
```php
public function __construct(
    // ... existing
    private readonly DnsConfigurationHelper $dnsHelper, // NEW
    private readonly ConfigManager $configManager, // NEW (if not already there)
) {
    parent::__construct($modeDetector);
}
```

**Update execute() - add after destroy completion**:
```php
// Offer DNS cleanup
$config = $this->configManager->load();
$projectName = $config->proxy()->domainPrefix();

if ($this->dnsHelper->hasDnsmasq()) {
    $dnsConfigPath = $this->dnsHelper->getDnsmasqConfigPath($projectName);

    if (file_exists($dnsConfigPath)) {
        $io->newLine();
        $io->writeln("DNS configuration still exists:");
        $io->writeln("  {$dnsConfigPath}");
        $io->newLine();

        if ($io->confirm('Remove DNS configuration?', false)) {
            try {
                $this->dnsHelper->removeDnsmasqConfig($projectName);
                $this->dnsHelper->restartDnsmasq();
                $io->success('DNS configuration removed');
            } catch (\Exception $e) {
                $io->error('Failed to remove DNS configuration: ' . $e->getMessage());
                $io->note("Manually remove: {$dnsConfigPath}");
            }
        }
    }
}

$io->success('Environment destroyed completely');
```

**Verification**:
```bash
vendor/bin/phpstan analyse src/Command/DestroyCommand.php
vendor/bin/php-cs-fixer fix src/Command/DestroyCommand.php
```

---

### Task 6: Add DNS Helpers to ConfigurationFactory

**File**: `src/Factory/ConfigurationFactory.php` (existing, might need updates)

Ensure that when loading or creating configurations, DNS/Proxy defaults are set correctly.

**Update** (if needed):
```php
public function createDefault(string $projectName): Configuration
{
    return new Configuration(
        version: '1.0',
        projectType: ProjectType::Web,
        php: PhpConfig::default(),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        proxy: ProxyConfig::default($projectName), // Ensure this is set
    );
}
```

**Verification**:
```bash
vendor/bin/phpstan analyse src/Factory/ConfigurationFactory.php
```

---

## Final Phase 3 Verification

After all tasks complete:

```bash
# Run all unit tests
vendor/bin/pest tests/Unit --coverage

# Verify 95% coverage
vendor/bin/pest tests/Unit --coverage --min=95

# Run PHPStan
vendor/bin/phpstan analyse

# Test DNS configuration manually
seaman init
# Should offer DNS configuration options

seaman proxy:configure-dns
# Should work in managed mode

seaman destroy
# Should offer to clean up DNS config
```

## Expected Coverage Report

```
Phase 3 New Files:
- DnsConfigurationResult: 100%
- DnsConfigurationHelper: 95%+ (process execution mocked)
- ProxyConfigureDnsCommand: N/A (command, not unit tested)

Overall Project Coverage: ≥ 95%
```

## Commit Strategy

Commit after each completed task:

```bash
git add <files>
git commit -m "feat(dns): <task description>

<details>

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

## Success Criteria

- ✅ All 6 tasks completed
- ✅ DNS configuration detects dnsmasq/systemd-resolved
- ✅ Automatic DNS setup works with sudo
- ✅ Manual instructions clear and helpful
- ✅ DNS cleanup on destroy works
- ✅ proxy:configure-dns command functional
- ✅ All unit tests passing (95%+ coverage)
- ✅ PHPStan level 10 clean

## Next Phase

After Phase 3 completion:
- Phase 4: Import Mechanism
- Document: `docs/plans/phases/phase-4-import-mechanism.md`
