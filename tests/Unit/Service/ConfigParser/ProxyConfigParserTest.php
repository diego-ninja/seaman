<?php

declare(strict_types=1);

use Seaman\Service\ConfigParser\ProxyConfigParser;
use Seaman\ValueObject\ProxyConfig;

beforeEach(function () {
    $this->parser = new ProxyConfigParser();
});

test('parses proxy configuration with all settings', function () {
    $data = [
        'proxy' => [
            'enabled' => true,
            'domain_prefix' => 'myapp',
            'cert_resolver' => 'letsencrypt',
            'dashboard' => false,
        ],
    ];

    $result = $this->parser->parse($data, 'defaultapp');

    expect($result)->toBeInstanceOf(ProxyConfig::class);
    expect($result->enabled)->toBeTrue();
    expect($result->domainPrefix)->toBe('myapp');
    expect($result->certResolver)->toBe('letsencrypt');
    expect($result->dashboard)->toBeFalse();
});

test('returns default proxy config when not provided', function () {
    $data = [];

    $result = $this->parser->parse($data, 'myproject');

    expect($result->enabled)->toBeTrue();
    expect($result->domainPrefix)->toBe('myproject');
    expect($result->certResolver)->toBe('selfsigned');
    expect($result->dashboard)->toBeTrue();
});

test('returns default proxy config for invalid proxy value', function () {
    $data = ['proxy' => 'invalid'];

    $result = $this->parser->parse($data, 'myproject');

    expect($result->domainPrefix)->toBe('myproject');
});

test('uses defaults for missing proxy settings', function () {
    $data = [
        'proxy' => [
            'enabled' => false,
        ],
    ];

    $result = $this->parser->parse($data, 'myproject');

    expect($result->enabled)->toBeFalse();
    expect($result->domainPrefix)->toBe('myproject');
    expect($result->certResolver)->toBe('selfsigned');
    expect($result->dashboard)->toBeTrue();
});

test('handles invalid value types with defaults', function () {
    $data = [
        'proxy' => [
            'enabled' => 'yes',
            'domain_prefix' => 123,
            'cert_resolver' => null,
            'dashboard' => 'true',
        ],
    ];

    $result = $this->parser->parse($data, 'myproject');

    expect($result->enabled)->toBeTrue();
    expect($result->domainPrefix)->toBe('myproject');
    expect($result->certResolver)->toBe('selfsigned');
    expect($result->dashboard)->toBeTrue();
});
