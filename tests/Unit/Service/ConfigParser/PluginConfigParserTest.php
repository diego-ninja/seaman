<?php

declare(strict_types=1);

// ABOUTME: Tests for PluginConfigParser.
// ABOUTME: Validates plugin configuration parsing from YAML data.

namespace Seaman\Tests\Unit\Service\ConfigParser;

use Seaman\Service\ConfigParser\PluginConfigParser;

test('parses empty plugins configuration', function () {
    $parser = new PluginConfigParser();

    $result = $parser->parse([]);

    expect($result)->toBe([]);
});

test('parses plugins configuration with single plugin', function () {
    $parser = new PluginConfigParser();

    $data = [
        'plugins' => [
            'my-plugin' => [
                'setting1' => 'value1',
                'setting2' => 42,
            ],
        ],
    ];

    $result = $parser->parse($data);

    expect($result)->toBe([
        'my-plugin' => [
            'setting1' => 'value1',
            'setting2' => 42,
        ],
    ]);
});

test('parses plugins configuration with multiple plugins', function () {
    $parser = new PluginConfigParser();

    $data = [
        'plugins' => [
            'plugin-one' => [
                'enabled' => true,
                'option' => 'test',
            ],
            'plugin-two' => [
                'enabled' => false,
                'timeout' => 30,
            ],
        ],
    ];

    $result = $parser->parse($data);

    expect($result)->toBe([
        'plugin-one' => [
            'enabled' => true,
            'option' => 'test',
        ],
        'plugin-two' => [
            'enabled' => false,
            'timeout' => 30,
        ],
    ]);
});

test('returns empty array when plugins is not an array', function () {
    $parser = new PluginConfigParser();

    $data = [
        'plugins' => 'invalid',
    ];

    $result = $parser->parse($data);

    expect($result)->toBe([]);
});

test('filters out non-string keys', function () {
    $parser = new PluginConfigParser();

    $data = [
        'plugins' => [
            'valid-plugin' => [
                'setting' => 'value',
            ],
            0 => [
                'should' => 'be filtered',
            ],
        ],
    ];

    $result = $parser->parse($data);

    expect($result)->toBe([
        'valid-plugin' => [
            'setting' => 'value',
        ],
    ]);
});

test('filters out non-array values', function () {
    $parser = new PluginConfigParser();

    $data = [
        'plugins' => [
            'valid-plugin' => [
                'setting' => 'value',
            ],
            'invalid-plugin' => 'not an array',
        ],
    ];

    $result = $parser->parse($data);

    expect($result)->toBe([
        'valid-plugin' => [
            'setting' => 'value',
        ],
    ]);
});

test('handles nested arrays in plugin configuration', function () {
    $parser = new PluginConfigParser();

    $data = [
        'plugins' => [
            'complex-plugin' => [
                'simple' => 'value',
                'nested' => [
                    'level1' => [
                        'level2' => 'deep',
                    ],
                ],
                'list' => [1, 2, 3],
            ],
        ],
    ];

    $result = $parser->parse($data);

    expect($result)->toBe([
        'complex-plugin' => [
            'simple' => 'value',
            'nested' => [
                'level1' => [
                    'level2' => 'deep',
                ],
            ],
            'list' => [1, 2, 3],
        ],
    ]);
});
