<?php

declare(strict_types=1);

use Seaman\Service\ConfigParser\VolumeConfigParser;
use Seaman\ValueObject\VolumeConfig;

beforeEach(function () {
    $this->parser = new VolumeConfigParser();
});

test('parses volume configuration', function () {
    $data = [
        'volumes' => [
            'persist' => ['mysql_data', 'redis_data'],
        ],
    ];

    $result = $this->parser->parse($data);

    expect($result)->toBeInstanceOf(VolumeConfig::class);
    expect($result->persist)->toBe(['mysql_data', 'redis_data']);
});

test('parses empty persist list', function () {
    $data = [
        'volumes' => [
            'persist' => [],
        ],
    ];

    $result = $this->parser->parse($data);

    expect($result->persist)->toBe([]);
});

test('throws exception for invalid volumes configuration', function () {
    $data = ['volumes' => 'invalid'];

    $this->parser->parse($data);
})->throws(RuntimeException::class, 'Invalid volumes configuration');

test('throws exception for invalid persist configuration', function () {
    $data = [
        'volumes' => [
            'persist' => 'invalid',
        ],
    ];

    $this->parser->parse($data);
})->throws(RuntimeException::class, 'Invalid persist configuration');

test('filters non-string values from persist list', function () {
    $data = [
        'volumes' => [
            'persist' => ['mysql_data', 123, 'redis_data', null],
        ],
    ];

    $result = $this->parser->parse($data);

    expect($result->persist)->toBe(['mysql_data', 'redis_data']);
});

test('merges volumes with base configuration', function () {
    $basePersist = ['mysql_data'];

    $overrides = [
        'volumes' => [
            'persist' => ['redis_data', 'postgres_data'],
        ],
    ];

    $result = $this->parser->merge($overrides, $basePersist);

    expect($result->persist)->toBe(['redis_data', 'postgres_data']);
});

test('merge preserves base persist when not overridden', function () {
    $basePersist = ['mysql_data', 'redis_data'];

    $overrides = [];

    $result = $this->parser->merge($overrides, $basePersist);

    expect($result->persist)->toBe(['mysql_data', 'redis_data']);
});
