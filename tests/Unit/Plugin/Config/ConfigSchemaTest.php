<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Config;

use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\Config\ConfigValidationException;

test('ConfigSchema can define integer fields', function (): void {
    $schema = ConfigSchema::create()
        ->integer('nodes', default: 3, min: 1, max: 10);

    expect($schema->validate(['nodes' => 5]))->toBe(['nodes' => 5]);
    expect($schema->validate([]))->toBe(['nodes' => 3]);
});

test('ConfigSchema validates integer constraints', function (): void {
    $schema = ConfigSchema::create()
        ->integer('nodes', default: 3, min: 1, max: 10);

    $schema->validate(['nodes' => 15]);
})->throws(ConfigValidationException::class);

test('ConfigSchema can define string fields', function (): void {
    $schema = ConfigSchema::create()
        ->string('name', default: 'default-name');

    expect($schema->validate(['name' => 'custom']))->toBe(['name' => 'custom']);
    expect($schema->validate([]))->toBe(['name' => 'default-name']);
});

test('ConfigSchema can define boolean fields', function (): void {
    $schema = ConfigSchema::create()
        ->boolean('enabled', default: true);

    expect($schema->validate(['enabled' => false]))->toBe(['enabled' => false]);
    expect($schema->validate([]))->toBe(['enabled' => true]);
});

test('ConfigSchema can define nullable fields', function (): void {
    $schema = ConfigSchema::create()
        ->string('password', default: null, nullable: true);

    expect($schema->validate(['password' => null]))->toBe(['password' => null]);
    expect($schema->validate(['password' => 'secret']))->toBe(['password' => 'secret']);
});

test('ConfigSchema rejects unknown fields', function (): void {
    $schema = ConfigSchema::create()
        ->integer('nodes', default: 3);

    $schema->validate(['unknown' => 'value']);
})->throws(ConfigValidationException::class);
