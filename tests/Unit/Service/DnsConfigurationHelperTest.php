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
        }

        return new ProcessResult(exitCode: 0);
    }
}

test('detects dnsmasq and returns automatic configuration', function () {
    $executor = new FakeDnsCommandExecutor(hasDnsmasq: true);
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
    $executor = new FakeDnsCommandExecutor(hasDnsmasq: false, hasSystemdResolved: true);
    $helper = new DnsConfigurationHelper($executor);

    $result = $helper->configure('testproject');

    expect($result)->toBeInstanceOf(DnsConfigurationResult::class)
        ->and($result->type)->toBe('systemd-resolved')
        ->and($result->automatic)->toBeTrue()
        ->and($result->requiresSudo)->toBeTrue()
        ->and($result->configPath)->toContain('systemd')
        ->and($result->configContent)->toContain('testproject.local');
});

test('returns manual instructions when no automatic option available', function () {
    $executor = new FakeDnsCommandExecutor(hasDnsmasq: false, hasSystemdResolved: false);
    $helper = new DnsConfigurationHelper($executor);

    $result = $helper->configure('myproject');

    expect($result)->toBeInstanceOf(DnsConfigurationResult::class)
        ->and($result->type)->toBe('manual')
        ->and($result->automatic)->toBeFalse()
        ->and($result->requiresSudo)->toBeFalse()
        ->and($result->configPath)->toBeNull()
        ->and($result->configContent)->toBeNull()
        ->and($result->instructions)->not->toBeEmpty();
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

test('detectAvailableProviders returns empty array when no providers available', function () {
    $executor = new FakeDnsCommandExecutor();
    $helper = new DnsConfigurationHelper($executor);

    $providers = $helper->detectAvailableProviders('testproject');

    expect($providers)->toBeArray()->toBeEmpty();
});

test('detectAvailableProviders detects dnsmasq', function () {
    $executor = new FakeDnsCommandExecutor(hasDnsmasq: true);
    $helper = new DnsConfigurationHelper($executor);

    $providers = $helper->detectAvailableProviders('testproject');

    expect($providers)->not->toBeEmpty();
    $dnsmasq = array_filter($providers, fn($p) => $p->provider === DnsProvider::Dnsmasq);
    expect($dnsmasq)->not->toBeEmpty();
});

test('detectAvailableProviders detects NetworkManager', function () {
    $executor = new FakeDnsCommandExecutor(hasNetworkManager: true);
    $helper = new DnsConfigurationHelper($executor);

    $providers = $helper->detectAvailableProviders('testproject');

    $nm = array_filter($providers, fn($p) => $p->provider === DnsProvider::NetworkManager);
    expect($nm)->not->toBeEmpty();
});

test('detectAvailableProviders returns providers sorted by priority', function () {
    $executor = new FakeDnsCommandExecutor(
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
    $executor = new FakeDnsCommandExecutor(
        hasDnsmasq: true,
        hasSystemdResolved: true,
    );
    $helper = new DnsConfigurationHelper($executor);

    $recommended = $helper->getRecommendedProvider('testproject');

    expect($recommended)->not->toBeNull()
        ->and($recommended->provider)->toBe(DnsProvider::Dnsmasq);
});

test('getRecommendedProvider returns null when no providers available', function () {
    $executor = new FakeDnsCommandExecutor();
    $helper = new DnsConfigurationHelper($executor);

    $recommended = $helper->getRecommendedProvider('testproject');

    expect($recommended)->toBeNull();
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
