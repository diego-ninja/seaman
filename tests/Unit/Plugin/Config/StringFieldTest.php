<?php

declare(strict_types=1);

// ABOUTME: Unit tests for StringField configuration field.
// ABOUTME: Validates string field creation and metadata access.

namespace Seaman\Tests\Unit\Plugin\Config;

use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\Config\StringField;

test('StringField supports label method', function () {
    $schema = ConfigSchema::create()
        ->string('version', default: '8.0')
            ->label('MySQL Version');

    $fields = $schema->getFields();
    expect($fields['version']->getMetadata()->label)->toBe('MySQL Version');
});

test('StringField supports description method', function () {
    $schema = ConfigSchema::create()
        ->string('version', default: '8.0')
            ->description('Docker image tag');

    $fields = $schema->getFields();
    expect($fields['version']->getMetadata()->description)->toBe('Docker image tag');
});

test('StringField supports secret method', function () {
    $schema = ConfigSchema::create()
        ->string('password', default: 'secret')
            ->secret();

    $fields = $schema->getFields();
    expect($fields['password']->getMetadata()->isSecret)->toBeTrue();
});

test('StringField generates label from field name if not set', function () {
    $schema = ConfigSchema::create()
        ->string('root_password', default: 'secret');

    $fields = $schema->getFields();
    expect($fields['root_password']->getMetadata()->label)->toBe('Root Password');
});

test('StringField chains metadata methods fluently', function () {
    $schema = ConfigSchema::create()
        ->string('password', default: 'secret')
            ->label('Root Password')
            ->description('Database root password')
            ->secret();

    $fields = $schema->getFields();
    $metadata = $fields['password']->getMetadata();

    expect($metadata->label)->toBe('Root Password')
        ->and($metadata->description)->toBe('Database root password')
        ->and($metadata->isSecret)->toBeTrue();
});

test('StringField supports enum method', function () {
    $schema = ConfigSchema::create()
        ->string('log_level', default: 'info')
            ->enum(['debug', 'info', 'warn', 'error'])
            ->label('Log Level');

    $fields = $schema->getFields();
    /** @var StringField $field */
    $field = $fields['log_level'];

    expect($field->getEnum())->toBe(['debug', 'info', 'warn', 'error']);
});

test('StringField returns correct type', function () {
    $schema = ConfigSchema::create()
        ->string('name', default: 'test');

    $fields = $schema->getFields();
    expect($fields['name']->getType())->toBe('string');
});

test('StringField returns correct name', function () {
    $schema = ConfigSchema::create()
        ->string('database_name', default: 'seaman');

    $fields = $schema->getFields();
    expect($fields['database_name']->getName())->toBe('database_name');
});

test('StringField returns correct default', function () {
    $schema = ConfigSchema::create()
        ->string('version', default: '8.0');

    $fields = $schema->getFields();
    expect($fields['version']->getDefault())->toBe('8.0');
});
