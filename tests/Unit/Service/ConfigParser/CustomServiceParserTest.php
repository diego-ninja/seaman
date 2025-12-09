<?php

declare(strict_types=1);

use Seaman\Service\ConfigParser\CustomServiceParser;
use Seaman\ValueObject\CustomServiceCollection;

beforeEach(function () {
    $this->parser = new CustomServiceParser();
});

test('parses custom services', function () {
    $data = [
        'custom_services' => [
            'caddy' => [
                'image' => 'caddy:latest',
                'ports' => ['80:80'],
            ],
            'pgadmin' => [
                'image' => 'dpage/pgadmin4',
                'environment' => ['PGADMIN_DEFAULT_EMAIL' => 'admin@example.com'],
            ],
        ],
    ];

    $result = $this->parser->parse($data);

    expect($result)->toBeInstanceOf(CustomServiceCollection::class);
    expect($result->has('caddy'))->toBeTrue();
    expect($result->has('pgadmin'))->toBeTrue();
    expect($result->get('caddy'))->toBe([
        'image' => 'caddy:latest',
        'ports' => ['80:80'],
    ]);
});

test('returns empty collection when custom_services not provided', function () {
    $data = [];

    $result = $this->parser->parse($data);

    expect($result->isEmpty())->toBeTrue();
});

test('returns empty collection for invalid custom_services value', function () {
    $data = ['custom_services' => 'invalid'];

    $result = $this->parser->parse($data);

    expect($result->isEmpty())->toBeTrue();
});

test('skips invalid custom service entries', function () {
    $data = [
        'custom_services' => [
            'valid' => ['image' => 'nginx:latest'],
            123 => ['invalid' => 'key'],
            'invalid' => 'not-an-array',
        ],
    ];

    $result = $this->parser->parse($data);

    expect(count($result->names()))->toBe(1);
    expect($result->has('valid'))->toBeTrue();
});
