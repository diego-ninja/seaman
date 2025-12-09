<?php

declare(strict_types=1);

// ABOUTME: Tests for DnsConfigurationHelper service.
// ABOUTME: Validates DNS configuration logic with fake command executor.

namespace Seaman\Tests\Unit\Service;

use Seaman\Contract\CommandExecutor;
use Seaman\Enum\DnsProvider;
use Seaman\Service\DnsConfigurationHelper;
use Seaman\ValueObject\DnsConfigurationResult;
use Seaman\ValueObject\ProcessResult;

// Fake CommandExecutor for testing
final readonly class FakeDnsCommandExecutor implements CommandExecutor
{
    public function __construct(
        private bool $hasDnsmasq = false,
        private bool $hasSystemdResolved = false,
        private bool $hasNetworkManager = false,
        private bool $isDnsmasqRunning = false,
        private bool $isPort53Occupied = false,
    ) {}

    public function execute(array $command): ProcessResult
    {
        // Simulate 'which dnsmasq' check
        if ($command[0] === 'which' && $command[1] === 'dnsmasq') {
            return new ProcessResult(
                exitCode: $this->hasDnsmasq ? 0 : 1,
            );
        }

        // Simulate 'systemctl is-active' checks
        if ($command[0] === 'systemctl' && $command[1] === 'is-active') {
            if ($command[2] === 'systemd-resolved') {
                return new ProcessResult(
                    exitCode: $this->hasSystemdResolved ? 0 : 1,
                );
            }
            if ($command[2] === 'NetworkManager') {
                return new ProcessResult(
                    exitCode: $this->hasNetworkManager ? 0 : 1,
                );
            }
            if ($command[2] === 'dnsmasq') {
                return new ProcessResult(
                    exitCode: $this->isDnsmasqRunning ? 0 : 1,
                );
            }
        }

        // Simulate 'ss -tlnp' for port 53 check
        if ($command[0] === 'ss' && in_array('-tlnp', $command, true)) {
            $output = $this->isPort53Occupied ? '127.0.0.53:53' : '';
            return new ProcessResult(exitCode: 0, output: $output);
        }

        return new ProcessResult(exitCode: 0);
    }
}

test('detects dnsmasq and returns automatic configuration', function () {
    // dnsmasq available and running (port 53 can be used)
    $executor = new FakeDnsCommandExecutor(hasDnsmasq: true, isDnsmasqRunning: true);
    $helper = new DnsConfigurationHelper($executor);

    $result = $helper->configure('myproject');

    expect($result)->toBeInstanceOf(DnsConfigurationResult::class)
        ->and($result->type)->toBe('dnsmasq')
        ->and($result->automatic)->toBeTrue()
        ->and($result->requiresSudo)->toBeTrue()
        ->and($result->configPath)->toContain('dnsmasq')
        ->and($result->configContent)->toContain('.myproject.local');
});

test('detects systemd-resolved when dnsmasq not available', function () {
    // systemd-resolved active without stub listener (port 53 not occupied)
    $executor = new FakeDnsCommandExecutor(
        hasDnsmasq: false,
        hasSystemdResolved: true,
        isPort53Occupied: false,
    );
    $helper = new DnsConfigurationHelper($executor);

    $result = $helper->configure('testproject');

    expect($result)->toBeInstanceOf(DnsConfigurationResult::class)
        ->and($result->type)->toBe('systemd-resolved')
        ->and($result->automatic)->toBeTrue()
        ->and($result->requiresSudo)->toBeTrue()
        ->and($result->configPath)->toContain('systemd')
        ->and($result->configContent)->toContain('testproject.local');
});

test('returns hosts-file configuration when no other providers available', function () {
    // No dnsmasq, no systemd-resolved, no NetworkManager - falls back to /etc/hosts
    $executor = new FakeDnsCommandExecutor(hasDnsmasq: false, hasSystemdResolved: false);
    $helper = new DnsConfigurationHelper($executor);

    $result = $helper->configure('myproject');

    expect($result)->toBeInstanceOf(DnsConfigurationResult::class)
        ->and($result->type)->toBe('hosts-file')
        ->and($result->automatic)->toBeTrue()
        ->and($result->requiresSudo)->toBeTrue()
        ->and($result->configPath)->toBe('/etc/hosts')
        ->and($result->configContent)->toContain('myproject.local');
});

test('hasDnsmasq returns true when dnsmasq is available', function () {
    $executor = new FakeDnsCommandExecutor(hasDnsmasq: true);
    $helper = new DnsConfigurationHelper($executor);

    expect($helper->hasDnsmasq())->toBeTrue();
});

test('hasDnsmasq returns false when dnsmasq is not available', function () {
    $executor = new FakeDnsCommandExecutor(hasDnsmasq: false);
    $helper = new DnsConfigurationHelper($executor);

    expect($helper->hasDnsmasq())->toBeFalse();
});

test('hasSystemdResolved returns true when systemd-resolved is active', function () {
    $executor = new FakeDnsCommandExecutor(hasSystemdResolved: true);
    $helper = new DnsConfigurationHelper($executor);

    expect($helper->hasSystemdResolved())->toBeTrue();
});

test('hasSystemdResolved returns false when systemd-resolved is not active', function () {
    $executor = new FakeDnsCommandExecutor(hasSystemdResolved: false);
    $helper = new DnsConfigurationHelper($executor);

    expect($helper->hasSystemdResolved())->toBeFalse();
});

test('DnsConfigurationHelper is readonly', function () {
    $executor = new FakeDnsCommandExecutor();
    $helper = new DnsConfigurationHelper($executor);

    $reflection = new \ReflectionClass($helper);
    expect($reflection->isReadOnly())->toBeTrue();
});

test('detectAvailableProviders always includes HostsFile as fallback', function () {
    // No DNS providers available - but /etc/hosts is always available
    $executor = new FakeDnsCommandExecutor();
    $helper = new DnsConfigurationHelper($executor);

    $providers = $helper->detectAvailableProviders('testproject');

    expect($providers)->toBeArray()
        ->toHaveCount(1);

    $hostsFile = array_filter($providers, fn($p) => $p->provider === DnsProvider::HostsFile);
    expect($hostsFile)->toHaveCount(1);
});

test('detectAvailableProviders detects dnsmasq when running', function () {
    // dnsmasq installed and running (can use port 53)
    $executor = new FakeDnsCommandExecutor(hasDnsmasq: true, isDnsmasqRunning: true);
    $helper = new DnsConfigurationHelper($executor);

    $providers = $helper->detectAvailableProviders('testproject');

    expect($providers)->not->toBeEmpty();
    $dnsmasq = array_filter($providers, fn($p) => $p->provider === DnsProvider::Dnsmasq);
    expect($dnsmasq)->not->toBeEmpty();
});

test('detectAvailableProviders detects dnsmasq when port 53 is free', function () {
    // dnsmasq installed, not running, but port 53 is free
    $executor = new FakeDnsCommandExecutor(hasDnsmasq: true, isDnsmasqRunning: false, isPort53Occupied: false);
    $helper = new DnsConfigurationHelper($executor);

    $providers = $helper->detectAvailableProviders('testproject');

    $dnsmasq = array_filter($providers, fn($p) => $p->provider === DnsProvider::Dnsmasq);
    expect($dnsmasq)->not->toBeEmpty();
});

test('detectAvailableProviders excludes dnsmasq when port 53 is occupied', function () {
    // dnsmasq installed but can't use port 53 (blocked by something else)
    $executor = new FakeDnsCommandExecutor(hasDnsmasq: true, isDnsmasqRunning: false, isPort53Occupied: true);
    $helper = new DnsConfigurationHelper($executor);

    $providers = $helper->detectAvailableProviders('testproject');

    $dnsmasq = array_filter($providers, fn($p) => $p->provider === DnsProvider::Dnsmasq);
    expect($dnsmasq)->toBeEmpty();
});

test('detectAvailableProviders does not detect NetworkManager without dnsmasq plugin', function () {
    // NetworkManager is active, but we can't verify dnsmasq plugin without real files
    // The hasNetworkManagerWithDnsmasq() checks for dns=dnsmasq in NetworkManager.conf
    $executor = new FakeDnsCommandExecutor(hasNetworkManager: true);
    $helper = new DnsConfigurationHelper($executor);

    $providers = $helper->detectAvailableProviders('testproject');

    // NetworkManager won't be detected because we can't simulate the config file check
    // But HostsFile should always be present
    $hostsFile = array_filter($providers, fn($p) => $p->provider === DnsProvider::HostsFile);
    expect($hostsFile)->not->toBeEmpty();
});

test('detectAvailableProviders returns providers sorted by priority', function () {
    // dnsmasq running (can use port 53), systemd-resolved without stub listener
    $executor = new FakeDnsCommandExecutor(
        hasDnsmasq: true,
        hasSystemdResolved: true,
        hasNetworkManager: true,
        isDnsmasqRunning: true,
        isPort53Occupied: false,
    );
    $helper = new DnsConfigurationHelper($executor);

    $providers = $helper->detectAvailableProviders('testproject');

    // Dnsmasq should be first (priority 2), then systemd-resolved (priority 4), then HostsFile (priority 5)
    expect($providers[0]->provider)->toBe(DnsProvider::Dnsmasq);
});

test('getRecommendedProvider returns first provider by priority', function () {
    // dnsmasq running, systemd-resolved without stub listener
    $executor = new FakeDnsCommandExecutor(
        hasDnsmasq: true,
        hasSystemdResolved: true,
        isDnsmasqRunning: true,
        isPort53Occupied: false,
    );
    $helper = new DnsConfigurationHelper($executor);

    $recommended = $helper->getRecommendedProvider('testproject');

    expect($recommended)->not->toBeNull();
    /** @var \Seaman\ValueObject\DetectedDnsProvider $recommended */
    expect($recommended->provider)->toBe(DnsProvider::Dnsmasq);
});

test('getRecommendedProvider returns HostsFile when no other providers available', function () {
    // No DNS providers available - HostsFile is always the fallback
    $executor = new FakeDnsCommandExecutor();
    $helper = new DnsConfigurationHelper($executor);

    $recommended = $helper->getRecommendedProvider('testproject');

    expect($recommended)->not->toBeNull();
    /** @var \Seaman\ValueObject\DetectedDnsProvider $recommended */
    expect($recommended->provider)->toBe(DnsProvider::HostsFile);
});

test('configureProvider returns correct result for dnsmasq', function () {
    $executor = new FakeDnsCommandExecutor(hasDnsmasq: true);
    $helper = new DnsConfigurationHelper($executor);

    $result = $helper->configureProvider('testproject', DnsProvider::Dnsmasq);

    expect($result->type)->toBe('dnsmasq')
        ->and($result->automatic)->toBeTrue()
        ->and($result->configContent)->toContain('.testproject.local')
        ->and($result->restartCommand)->toContain('dnsmasq');
});

test('configureProvider returns correct result for NetworkManager', function () {
    $executor = new FakeDnsCommandExecutor(hasNetworkManager: true);
    $helper = new DnsConfigurationHelper($executor);

    $result = $helper->configureProvider('testproject', DnsProvider::NetworkManager);

    expect($result->type)->toBe('networkmanager')
        ->and($result->automatic)->toBeTrue()
        ->and($result->configPath)->toContain('NetworkManager')
        ->and($result->restartCommand)->toContain('NetworkManager');
});

test('hasNetworkManager returns true when NetworkManager is active', function () {
    $executor = new FakeDnsCommandExecutor(hasNetworkManager: true);
    $helper = new DnsConfigurationHelper($executor);

    expect($helper->hasNetworkManager())->toBeTrue();
});

test('hasNetworkManager returns false when NetworkManager is not active', function () {
    $executor = new FakeDnsCommandExecutor(hasNetworkManager: false);
    $helper = new DnsConfigurationHelper($executor);

    expect($helper->hasNetworkManager())->toBeFalse();
});

test('configureProvider returns correct result for HostsFile', function () {
    $executor = new FakeDnsCommandExecutor();
    $helper = new DnsConfigurationHelper($executor);

    $result = $helper->configureProvider('testproject', DnsProvider::HostsFile);

    expect($result->type)->toBe('hosts-file')
        ->and($result->automatic)->toBeTrue()
        ->and($result->requiresSudo)->toBeTrue()
        ->and($result->configPath)->toBe('/etc/hosts')
        ->and($result->configContent)->toContain('app.testproject.local')
        ->and($result->configContent)->toContain('traefik.testproject.local')
        ->and($result->restartCommand)->toBeNull();
});

test('configureProvider returns correct result for Manual', function () {
    $executor = new FakeDnsCommandExecutor();
    $helper = new DnsConfigurationHelper($executor);

    $result = $helper->configureProvider('testproject', DnsProvider::Manual);

    expect($result->type)->toBe('manual')
        ->and($result->automatic)->toBeFalse()
        ->and($result->requiresSudo)->toBeFalse()
        ->and($result->configPath)->toBeNull()
        ->and($result->configContent)->toBeNull()
        ->and($result->instructions)->not->toBeEmpty();
});

test('canUseDnsmasq returns true when dnsmasq is running', function () {
    $executor = new FakeDnsCommandExecutor(hasDnsmasq: true, isDnsmasqRunning: true);
    $helper = new DnsConfigurationHelper($executor);

    expect($helper->canUseDnsmasq())->toBeTrue();
});

test('canUseDnsmasq returns true when port 53 is free', function () {
    $executor = new FakeDnsCommandExecutor(hasDnsmasq: true, isDnsmasqRunning: false, isPort53Occupied: false);
    $helper = new DnsConfigurationHelper($executor);

    expect($helper->canUseDnsmasq())->toBeTrue();
});

test('canUseDnsmasq returns false when port 53 is occupied', function () {
    $executor = new FakeDnsCommandExecutor(hasDnsmasq: true, isDnsmasqRunning: false, isPort53Occupied: true);
    $helper = new DnsConfigurationHelper($executor);

    expect($helper->canUseDnsmasq())->toBeFalse();
});

test('hasSystemdResolvedStubListener returns true when port 53 is occupied', function () {
    $executor = new FakeDnsCommandExecutor(hasSystemdResolved: true, isPort53Occupied: true);
    $helper = new DnsConfigurationHelper($executor);

    expect($helper->hasSystemdResolvedStubListener())->toBeTrue();
});

test('hasSystemdResolvedStubListener returns false when port 53 is free', function () {
    $executor = new FakeDnsCommandExecutor(hasSystemdResolved: true, isPort53Occupied: false);
    $helper = new DnsConfigurationHelper($executor);

    expect($helper->hasSystemdResolvedStubListener())->toBeFalse();
});

test('hasSystemdResolvedStubListener returns false when systemd-resolved is not active', function () {
    $executor = new FakeDnsCommandExecutor(hasSystemdResolved: false, isPort53Occupied: true);
    $helper = new DnsConfigurationHelper($executor);

    expect($helper->hasSystemdResolvedStubListener())->toBeFalse();
});

test('detectAvailableProviders excludes systemd-resolved when stub listener is active', function () {
    // systemd-resolved is active but its stub listener blocks port 53
    $executor = new FakeDnsCommandExecutor(hasSystemdResolved: true, isPort53Occupied: true);
    $helper = new DnsConfigurationHelper($executor);

    $providers = $helper->detectAvailableProviders('testproject');

    $resolved = array_filter($providers, fn($p) => $p->provider === DnsProvider::SystemdResolved);
    expect($resolved)->toBeEmpty();

    // But HostsFile should still be available
    $hostsFile = array_filter($providers, fn($p) => $p->provider === DnsProvider::HostsFile);
    expect($hostsFile)->not->toBeEmpty();
});
