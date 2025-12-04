<?php

declare(strict_types=1);

// ABOUTME: Tests for CertificateResult value object.
// ABOUTME: Validates certificate generation result properties.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\CertificateResult;

test('creates CertificateResult with all properties', function () {
    $result = new CertificateResult(
        type: 'mkcert',
        certPath: '.seaman/certs/cert.pem',
        keyPath: '.seaman/certs/key.pem',
        trusted: true
    );

    expect($result->type)->toBe('mkcert')
        ->and($result->certPath)->toBe('.seaman/certs/cert.pem')
        ->and($result->keyPath)->toBe('.seaman/certs/key.pem')
        ->and($result->trusted)->toBeTrue();
});

test('creates self-signed CertificateResult', function () {
    $result = new CertificateResult(
        type: 'self-signed',
        certPath: '.seaman/certs/cert.pem',
        keyPath: '.seaman/certs/key.pem',
        trusted: false
    );

    expect($result->type)->toBe('self-signed')
        ->and($result->certPath)->toBe('.seaman/certs/cert.pem')
        ->and($result->keyPath)->toBe('.seaman/certs/key.pem')
        ->and($result->trusted)->toBeFalse();
});

test('CertificateResult is immutable', function () {
    $result = new CertificateResult(
        type: 'mkcert',
        certPath: '.seaman/certs/cert.pem',
        keyPath: '.seaman/certs/key.pem',
        trusted: true
    );

    $reflection = new \ReflectionClass($result);
    expect($reflection->isReadOnly())->toBeTrue();
});
