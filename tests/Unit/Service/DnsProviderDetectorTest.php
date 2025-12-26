<?php

// ABOUTME: Tests for DnsProviderDetector service.
// ABOUTME: Validates DNS provider detection across different platforms.

declare(strict_types=1);

use Seaman\Contract\CommandExecutor;
use Seaman\Enum\DnsProvider;
use Seaman\Service\DnsProviderDetector;
use Seaman\ValueObject\DetectedDnsProvider;
use Seaman\ValueObject\ProcessResult;

beforeEach(function (): void {
    $this->executor = Mockery::mock(CommandExecutor::class);
    $this->detector = new DnsProviderDetector($this->executor);
});

describe('hasDnsmasq', function (): void {
    test('returns true when dnsmasq is installed', function (): void {
        $this->executor->expects('execute')
            ->with(['which', 'dnsmasq'])
            ->andReturn(new ProcessResult(0, '/usr/sbin/dnsmasq', ''));

        expect($this->detector->hasDnsmasq())->toBeTrue();
    });

    test('returns false when dnsmasq is not installed', function (): void {
        $this->executor->expects('execute')
            ->with(['which', 'dnsmasq'])
            ->andReturn(new ProcessResult(1, '', 'dnsmasq not found'));

        expect($this->detector->hasDnsmasq())->toBeFalse();
    });
});

describe('hasSystemdResolved', function (): void {
    test('returns true when systemd-resolved is active', function (): void {
        $this->executor->expects('execute')
            ->with(['systemctl', 'is-active', 'systemd-resolved'])
            ->andReturn(new ProcessResult(0, 'active', ''));

        expect($this->detector->hasSystemdResolved())->toBeTrue();
    });

    test('returns false when systemd-resolved is not active', function (): void {
        $this->executor->expects('execute')
            ->with(['systemctl', 'is-active', 'systemd-resolved'])
            ->andReturn(new ProcessResult(1, 'inactive', ''));

        expect($this->detector->hasSystemdResolved())->toBeFalse();
    });
});

describe('hasNetworkManager', function (): void {
    test('returns true when NetworkManager is active and config exists', function (): void {
        $this->executor->expects('execute')
            ->with(['systemctl', 'is-active', 'NetworkManager'])
            ->andReturn(new ProcessResult(0, 'active', ''));

        expect($this->detector->hasNetworkManager())->toBe(is_dir('/etc/NetworkManager'));
    });

    test('returns false when NetworkManager is not active', function (): void {
        $this->executor->expects('execute')
            ->with(['systemctl', 'is-active', 'NetworkManager'])
            ->andReturn(new ProcessResult(1, 'inactive', ''));

        expect($this->detector->hasNetworkManager())->toBeFalse();
    });
});

describe('isPort53Occupied', function (): void {
    test('returns true when port 53 is in use by resolved stub', function (): void {
        $this->executor->expects('execute')
            ->with(['ss', '-tlnp'])
            ->andReturn(new ProcessResult(0, 'LISTEN 0 4096 127.0.0.53:53 *:*', ''));

        expect($this->detector->isPort53Occupied())->toBeTrue();
    });

    test('returns false when port 53 is free', function (): void {
        $this->executor->expects('execute')
            ->with(['ss', '-tlnp'])
            ->andReturn(new ProcessResult(0, 'LISTEN 0 128 *:22 *:*', ''));

        expect($this->detector->isPort53Occupied())->toBeFalse();
    });

    test('returns false when ss command fails', function (): void {
        $this->executor->expects('execute')
            ->with(['ss', '-tlnp'])
            ->andReturn(new ProcessResult(1, '', 'error'));

        expect($this->detector->isPort53Occupied())->toBeFalse();
    });
});

describe('canUseDnsmasq', function (): void {
    test('returns true when dnsmasq is already running', function (): void {
        $this->executor->expects('execute')
            ->with(['systemctl', 'is-active', 'dnsmasq'])
            ->andReturn(new ProcessResult(0, 'active', ''));

        expect($this->detector->canUseDnsmasq())->toBeTrue();
    });

    test('returns true when dnsmasq is not running but port 53 is free', function (): void {
        $this->executor->expects('execute')
            ->with(['systemctl', 'is-active', 'dnsmasq'])
            ->andReturn(new ProcessResult(1, 'inactive', ''));

        $this->executor->expects('execute')
            ->with(['ss', '-tlnp'])
            ->andReturn(new ProcessResult(0, '', ''));

        expect($this->detector->canUseDnsmasq())->toBeTrue();
    });

    test('returns false when dnsmasq is not running and port 53 is occupied', function (): void {
        $this->executor->expects('execute')
            ->with(['systemctl', 'is-active', 'dnsmasq'])
            ->andReturn(new ProcessResult(1, 'inactive', ''));

        $this->executor->expects('execute')
            ->with(['ss', '-tlnp'])
            ->andReturn(new ProcessResult(0, '127.0.0.53:53', ''));

        expect($this->detector->canUseDnsmasq())->toBeFalse();
    });
});

describe('getDnsmasqConfigPath', function (): void {
    test('returns Linux path on Linux', function (): void {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('Only runs on Linux');
        }

        $path = $this->detector->getDnsmasqConfigPath('myproject');
        expect($path)->toBe('/etc/dnsmasq.d/seaman-myproject.conf');
    });

    test('returns Darwin path on macOS', function (): void {
        if (PHP_OS_FAMILY !== 'Darwin') {
            $this->markTestSkipped('Only runs on macOS');
        }

        $path = $this->detector->getDnsmasqConfigPath('myproject');
        expect($path)->toBe('/usr/local/etc/dnsmasq.d/seaman-myproject.conf');
    });
});

describe('detectAvailableProviders', function (): void {
    test('always includes hosts file as fallback', function (): void {
        // Mock all detection methods to return false
        $this->executor->expects('execute')
            ->with(['which', 'dnsmasq'])
            ->andReturn(new ProcessResult(1, '', ''));

        $this->executor->expects('execute')
            ->with(['systemctl', 'is-active', 'NetworkManager'])
            ->andReturn(new ProcessResult(1, '', ''));

        $this->executor->expects('execute')
            ->with(['systemctl', 'is-active', 'systemd-resolved'])
            ->andReturn(new ProcessResult(1, '', ''));

        $providers = $this->detector->detectAvailableProviders('test');

        $hostsProvider = array_filter(
            $providers,
            fn(DetectedDnsProvider $p) => $p->provider === DnsProvider::HostsFile,
        );

        expect($hostsProvider)->not->toBeEmpty();
    });

    test('providers are sorted by priority', function (): void {
        // Mock all detection methods to return false
        $this->executor->allows('execute')->andReturn(new ProcessResult(1, '', ''));

        $providers = $this->detector->detectAvailableProviders('test');

        if (count($providers) > 1) {
            for ($i = 0; $i < count($providers) - 1; $i++) {
                expect($providers[$i]->provider->getPriority())
                    ->toBeLessThanOrEqual($providers[$i + 1]->provider->getPriority());
            }
        }
    });
});

describe('getRecommendedProvider', function (): void {
    test('returns first provider from sorted list', function (): void {
        // Mock to only detect hosts file
        $this->executor->allows('execute')->andReturn(new ProcessResult(1, '', ''));

        $recommended = $this->detector->getRecommendedProvider('test');

        expect($recommended)->not->toBeNull();
    });

    test('returns provider based on platform priority', function (): void {
        // Mock all detection methods to return false (so dnsmasq/NM/resolved don't appear)
        $this->executor->allows('execute')->andReturn(new ProcessResult(1, '', ''));

        $recommended = $this->detector->getRecommendedProvider('test');

        // Should return something (macOS resolver on Darwin, hosts file on others)
        expect($recommended)->not->toBeNull();

        // On macOS, MacOSResolver should be first, on other platforms HostsFile
        if (PHP_OS_FAMILY === 'Darwin') {
            expect($recommended?->provider)->toBe(DnsProvider::MacOSResolver);
        } else {
            expect($recommended?->provider)->toBe(DnsProvider::HostsFile);
        }
    });
});
