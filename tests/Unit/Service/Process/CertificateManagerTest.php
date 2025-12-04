<?php

declare(strict_types=1);

// ABOUTME: Tests for CertificateManager service.
// ABOUTME: Validates certificate generation logic with fake command executor.

namespace Seaman\Tests\Unit\Service\Process;

use Seaman\Contract\CommandExecutor;
use Seaman\Service\Process\CertificateManager;
use Seaman\ValueObject\CertificateResult;
use Seaman\ValueObject\ProcessResult;

// Fake CommandExecutor for testing
final readonly class FakeCommandExecutor implements CommandExecutor
{
    public function __construct(
        private bool $mkcertAvailable = true,
    ) {}

    public function execute(array $command): ProcessResult
    {
        // Simulate 'which mkcert' check
        if ($command[0] === 'which' && $command[1] === 'mkcert') {
            return new ProcessResult(
                exitCode: $this->mkcertAvailable ? 0 : 1,
                successful: $this->mkcertAvailable
            );
        }

        // All other commands succeed (mkcert, openssl)
        return new ProcessResult(exitCode: 0, successful: true);
    }
}

test('generates certificates with mkcert when available', function () {
    $executor = new FakeCommandExecutor(mkcertAvailable: true);
    $manager = new CertificateManager($executor);

    $result = $manager->generateCertificates('myproject');

    expect($result)->toBeInstanceOf(CertificateResult::class)
        ->and($result->type)->toBe('mkcert')
        ->and($result->certPath)->toBe('.seaman/certs/cert.pem')
        ->and($result->keyPath)->toBe('.seaman/certs/key.pem')
        ->and($result->trusted)->toBeTrue();
});

test('generates self-signed certificates when mkcert not available', function () {
    $executor = new FakeCommandExecutor(mkcertAvailable: false);
    $manager = new CertificateManager($executor);

    $result = $manager->generateCertificates('myproject');

    expect($result)->toBeInstanceOf(CertificateResult::class)
        ->and($result->type)->toBe('self-signed')
        ->and($result->certPath)->toBe('.seaman/certs/cert.pem')
        ->and($result->keyPath)->toBe('.seaman/certs/key.pem')
        ->and($result->trusted)->toBeFalse();
});

test('hasMkcert returns true when mkcert is available', function () {
    $executor = new FakeCommandExecutor(mkcertAvailable: true);
    $manager = new CertificateManager($executor);

    expect($manager->hasMkcert())->toBeTrue();
});

test('hasMkcert returns false when mkcert is not available', function () {
    $executor = new FakeCommandExecutor(mkcertAvailable: false);
    $manager = new CertificateManager($executor);

    expect($manager->hasMkcert())->toBeFalse();
});

test('CertificateManager is readonly', function () {
    $executor = new FakeCommandExecutor();
    $manager = new CertificateManager($executor);

    $reflection = new \ReflectionClass($manager);
    expect($reflection->isReadOnly())->toBeTrue();
});
