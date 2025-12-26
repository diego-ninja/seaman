<?php

declare(strict_types=1);

use Seaman\Enum\PhpVersion;
use Seaman\Service\ConfigParser\PhpConfigParser;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;

beforeEach(function () {
    $this->parser = new PhpConfigParser();
});

test('parses PHP configuration with all settings', function () {
    $data = [
        'php' => [
            'version' => '8.4',
            'xdebug' => [
                'enabled' => true,
                'ide_key' => 'VSCODE',
                'client_host' => '192.168.1.1',
            ],
        ],
    ];

    $result = $this->parser->parse($data);

    expect($result)->toBeInstanceOf(PhpConfig::class);
    expect($result->version)->toBe(PhpVersion::Php84);
    expect($result->xdebug->enabled)->toBeTrue();
    expect($result->xdebug->ideKey)->toBe('VSCODE');
    expect($result->xdebug->clientHost)->toBe('192.168.1.1');
});

test('parses PHP configuration with defaults', function () {
    $data = [
        'php' => [
            'xdebug' => [
                'enabled' => false,
            ],
        ],
    ];

    $result = $this->parser->parse($data);

    expect($result->version)->toBe(PhpVersion::Php84);
    expect($result->xdebug->enabled)->toBeFalse();
    expect($result->xdebug->ideKey)->toBe('PHPSTORM');
    expect($result->xdebug->clientHost)->toBe('host.docker.internal');
});

test('throws exception for invalid PHP configuration', function () {
    $data = ['php' => 'invalid'];

    $this->parser->parse($data);
})->throws(RuntimeException::class, 'Invalid PHP configuration');

test('uses default when xdebug enabled value is invalid type', function () {
    $data = [
        'php' => [
            'xdebug' => [
                'enabled' => 'yes',
            ],
        ],
    ];

    $result = $this->parser->parse($data);

    // When invalid type provided, falls back to default (false)
    expect($result->xdebug->enabled)->toBeFalse();
});

test('merges PHP configuration preserving base values', function () {
    $base = new PhpConfig(
        version: PhpVersion::Php83,
        xdebug: new XdebugConfig(
            enabled: true,
            ideKey: 'PHPSTORM',
            clientHost: 'host.docker.internal',
        ),
    );

    $overrides = [
        'php' => [
            'version' => '8.4',
        ],
    ];

    $result = $this->parser->merge($overrides, $base);

    expect($result->version)->toBe(PhpVersion::Php84);
    expect($result->xdebug->enabled)->toBeTrue();
    expect($result->xdebug->ideKey)->toBe('PHPSTORM');
});

test('merges xdebug configuration partially', function () {
    $base = new PhpConfig(
        version: PhpVersion::Php84,
        xdebug: new XdebugConfig(
            enabled: false,
            ideKey: 'PHPSTORM',
            clientHost: 'host.docker.internal',
        ),
    );

    $overrides = [
        'php' => [
            'xdebug' => [
                'enabled' => true,
            ],
        ],
    ];

    $result = $this->parser->merge($overrides, $base);

    expect($result->xdebug->enabled)->toBeTrue();
    expect($result->xdebug->ideKey)->toBe('PHPSTORM');
    expect($result->xdebug->clientHost)->toBe('host.docker.internal');
});
