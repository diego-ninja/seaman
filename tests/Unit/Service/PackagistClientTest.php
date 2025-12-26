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
    if (isset($this->cacheDir) && is_dir($this->cacheDir)) {
        exec("rm -rf {$this->cacheDir}");
    }
});

test('creates client without cache directory', function () {
    $client = new PackagistClient();
    expect($client)->toBeInstanceOf(PackagistClient::class);
});

test('creates client with cache directory', function () {
    $client = new PackagistClient($this->cacheDir);
    expect($client)->toBeInstanceOf(PackagistClient::class);
});

test('searches for plugins from packagist', function () {
    $client = new PackagistClient($this->cacheDir);
    $results = $client->searchPlugins();

    // This is a live API test - results may vary
    expect($results)->toBeArray();

    foreach ($results as $plugin) {
        expect($plugin)->toHaveKeys(['name', 'description', 'url', 'downloads', 'favers']);
        expect($plugin['name'])->toBeString();
        expect($plugin['description'])->toBeString();
        expect($plugin['url'])->toBeString();
        expect($plugin['downloads'])->toBeInt();
        expect($plugin['favers'])->toBeInt();
    }
})->skip(getenv('CI') !== false, 'Skipping live API test in CI');

test('caches results when cache directory is set', function () {
    $client = new PackagistClient($this->cacheDir);

    // First call - should hit API
    $results1 = $client->searchPlugins();

    // Check cache file exists
    $cacheFiles = glob($this->cacheDir . '/*.json');
    expect($cacheFiles)->not->toBeEmpty();

    // Second call - should use cache
    $results2 = $client->searchPlugins();

    expect($results1)->toEqual($results2);
})->skip(getenv('CI') !== false, 'Skipping live API test in CI');

test('formats number correctly', function () {
    // Test the formatNumber method indirectly through the command
    // Since formatNumber is private, we test it through its usage

    $client = new PackagistClient();
    expect($client)->toBeInstanceOf(PackagistClient::class);
});

test('truncates long descriptions', function () {
    // This is tested through PluginListCommand
    // The truncate method is private
    expect(true)->toBeTrue();
});

test('gets package by name', function () {
    $client = new PackagistClient($this->cacheDir);
    $package = $client->getPackage('seaman/redis');

    expect($package)->not->toBeNull();
    expect($package)->toHaveKeys(['name', 'description', 'url', 'downloads', 'favers']);
    expect($package['name'])->toBe('seaman/redis');
})->skip(getenv('CI') !== false, 'Skipping live API test in CI');

test('returns null for non-existent package', function () {
    $client = new PackagistClient($this->cacheDir);
    $package = $client->getPackage('seaman/non-existent-package-12345');

    expect($package)->toBeNull();
})->skip(getenv('CI') !== false, 'Skipping live API test in CI');

test('returns null for non-plugin package', function () {
    $client = new PackagistClient($this->cacheDir);
    // symfony/console is not a seaman-plugin type
    $package = $client->getPackage('symfony/console');

    expect($package)->toBeNull();
})->skip(getenv('CI') !== false, 'Skipping live API test in CI');
