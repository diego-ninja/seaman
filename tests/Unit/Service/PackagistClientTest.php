<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Service;

use Seaman\Exception\PackagistException;
use Seaman\Service\PackagistClient;

beforeEach(function () {
    $this->cacheDir = sys_get_temp_dir() . '/seaman-packagist-test-' . uniqid();
    mkdir($this->cacheDir, 0755, true);
});

afterEach(function () {
    foreach (glob($this->cacheDir . '/*') ?: [] as $file) {
        unlink($file);
    }

    if (is_dir($this->cacheDir)) {
        rmdir($this->cacheDir);
    }
});

test('searches all pages and normalizes package data', function () {
    $requests = [];
    $responses = [
        json_encode([
            'results' => [
                [
                    'name' => 'vendor/first',
                    'description' => 'First plugin',
                    'url' => 'https://example.com/first',
                    'downloads' => 12,
                    'favers' => 3,
                ],
                ['name' => ''],
            ],
            'next' => 'next-page',
        ], JSON_THROW_ON_ERROR),
        json_encode([
            'results' => [
                ['name' => 'vendor/minimal', 'downloads' => 'invalid'],
                ['name' => 'seaman/redis'],
            ],
        ], JSON_THROW_ON_ERROR),
    ];

    $client = new PackagistClient(
        httpGet: function (string $url) use (&$requests, &$responses): array {
            $requests[] = $url;

            return ['body' => array_shift($responses), 'statusCode' => 200];
        },
    );

    expect($client->searchPlugins('cache store'))->toBe([
        [
            'name' => 'vendor/first',
            'description' => 'First plugin',
            'url' => 'https://example.com/first',
            'downloads' => 12,
            'favers' => 3,
        ],
        [
            'name' => 'vendor/minimal',
            'description' => '',
            'url' => '',
            'downloads' => 0,
            'favers' => 0,
        ],
        [
            'name' => 'seaman/redis',
            'description' => '',
            'url' => '',
            'downloads' => 0,
            'favers' => 0,
        ],
    ])->and($requests)->toBe([
        'https://packagist.org/search.json?type=seaman-plugin&page=1&q=cache+store',
        'https://packagist.org/search.json?type=seaman-plugin&page=2&q=cache+store',
    ]);
});

test('merges a known plugin returned by direct lookup', function () {
    $requests = [];
    $responses = [
        ['body' => '{"results":[]}', 'statusCode' => 200],
        [
            'body' => json_encode([
                'package' => [
                    'name' => 'seaman/redis',
                    'type' => 'seaman-plugin',
                    'description' => 'Redis plugin',
                    'repository' => 'https://github.com/seaman/redis',
                    'downloads' => ['total' => 42],
                    'favers' => 7,
                ],
            ], JSON_THROW_ON_ERROR),
            'statusCode' => 200,
        ],
    ];

    $client = new PackagistClient(
        httpGet: function (string $url) use (&$requests, &$responses): array {
            $requests[] = $url;

            return array_shift($responses);
        },
    );

    expect($client->searchPlugins('redis'))->toBe([
        [
            'name' => 'seaman/redis',
            'description' => 'Redis plugin',
            'url' => 'https://github.com/seaman/redis',
            'downloads' => 42,
            'favers' => 7,
        ],
    ])->and($requests)->toBe([
        'https://packagist.org/search.json?type=seaman-plugin&page=1&q=redis',
        'https://packagist.org/packages/seaman/redis.json',
    ]);
});

test('does not look up a known plugin that does not match the query', function () {
    $requests = [];
    $client = new PackagistClient(
        httpGet: function (string $url) use (&$requests): array {
            $requests[] = $url;

            return ['body' => '{"results":[]}', 'statusCode' => 200];
        },
    );

    expect($client->searchPlugins('mysql'))->toBe([])
        ->and($requests)->toHaveCount(1);
});

test('returns cached search results without making another request', function () {
    $requestCount = 0;
    $responses = [
        ['body' => '{"results":[{"name":"seaman/redis"}]}', 'statusCode' => 200],
    ];
    $client = new PackagistClient(
        cacheDir: $this->cacheDir,
        httpGet: function () use (&$requestCount, &$responses): array {
            ++$requestCount;

            return array_shift($responses);
        },
    );

    $first = $client->searchPlugins();
    $second = $client->searchPlugins();

    expect($second)->toBe($first)
        ->and($requestCount)->toBe(1)
        ->and(glob($this->cacheDir . '/*.json'))->toHaveCount(1);
});

test('ignores an expired cache entry', function () {
    $cacheFile = $this->cacheDir . '/packagist_plugins_' . md5('mysql') . '.json';
    file_put_contents($cacheFile, '[{"name":"stale"}]');
    touch($cacheFile, time() - 3601);

    $requestCount = 0;
    $client = new PackagistClient(
        cacheDir: $this->cacheDir,
        httpGet: function () use (&$requestCount): array {
            ++$requestCount;

            return ['body' => '{"results":[]}', 'statusCode' => 200];
        },
    );

    expect($client->searchPlugins('mysql'))->toBe([])
        ->and($requestCount)->toBe(1);
});

test('gets and normalizes a plugin package', function () {
    $client = new PackagistClient(
        httpGet: static fn(): array => [
            'body' => '{"package":{"name":"vendor/plugin","type":"seaman-plugin","downloads":{}}}',
            'statusCode' => 200,
        ],
    );

    expect($client->getPackage('vendor/plugin'))->toBe([
        'name' => 'vendor/plugin',
        'description' => '',
        'url' => '',
        'downloads' => 0,
        'favers' => 0,
    ]);
});

test('returns null when package is missing or has a different type', function (string $body) {
    $client = new PackagistClient(
        httpGet: static fn(): array => ['body' => $body, 'statusCode' => 200],
    );

    expect($client->getPackage('vendor/package'))->toBeNull();
})->with([
    'missing package' => ['{}'],
    'invalid package data' => ['{"package":"invalid"}'],
    'missing package name' => ['{"package":{"type":"seaman-plugin"}}'],
    'different package type' => ['{"package":{"name":"vendor/package","type":"library"}}'],
]);

test('returns null when Packagist responds with not found', function () {
    $client = new PackagistClient(
        httpGet: static fn(): array => [
            'body' => '{"status":"error","message":"Package not found"}',
            'statusCode' => 404,
        ],
    );

    expect($client->getPackage('vendor/missing'))->toBeNull();
});

test('reports transport and response errors', function (string|false $body, int $statusCode, string $message, int $code) {
    $client = new PackagistClient(
        httpGet: static fn(): array => ['body' => $body, 'statusCode' => $statusCode],
    );

    expect(fn() => $client->searchPlugins('mysql'))
        ->toThrow(PackagistException::class, $message, $code);
})->with([
    'connection failure' => [false, 0, 'Failed to connect to Packagist API', 0],
    'invalid json' => ['not-json', 200, 'Invalid JSON response from Packagist API', 0],
    'api error' => ['{"status":"error","message":"Rate limited"}', 429, 'Rate limited', 429],
    'api error without message' => ['{"status":"error"}', 500, 'Packagist API error', 500],
]);
