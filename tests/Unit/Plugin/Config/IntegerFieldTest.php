<?php

declare(strict_types=1);

// ABOUTME: Unit tests for IntegerField configuration field.
// ABOUTME: Validates integer field creation and metadata access.

namespace Seaman\Tests\Unit\Plugin\Config;

use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\Config\IntegerField;

test('IntegerField supports label method', function () {
    $schema = ConfigSchema::create()
        ->integer('port', default: 3306)
            ->label('External Port');

    $fields = $schema->getFields();
    expect($fields['port']->getMetadata()->label)->toBe('External Port');
});

test('IntegerField supports description method', function () {
    $schema = ConfigSchema::create()
        ->integer('port', default: 3306)
            ->description('Port to expose on host');

    $fields = $schema->getFields();
    expect($fields['port']->getMetadata()->description)->toBe('Port to expose on host');
});

test('IntegerField generates label from field name if not set', function () {
    $schema = ConfigSchema::create()
        ->integer('max_connections', default: 100);

    $fields = $schema->getFields();
    expect($fields['max_connections']->getMetadata()->label)->toBe('Max Connections');
});

test('IntegerField chains metadata methods fluently', function () {
    $schema = ConfigSchema::create()
        ->integer('port', default: 3306)
            ->label('External Port')
            ->description('Port exposed on host machine');

    $fields = $schema->getFields();
    $metadata = $fields['port']->getMetadata();

    expect($metadata->label)->toBe('External Port')
        ->and($metadata->description)->toBe('Port exposed on host machine')
        ->and($metadata->isSecret)->toBeFalse();
});

test('IntegerField returns correct type', function () {
    $schema = ConfigSchema::create()
        ->integer('port', default: 3306);

    $fields = $schema->getFields();
    expect($fields['port']->getType())->toBe('integer');
});

test('IntegerField returns correct name', function () {
    $schema = ConfigSchema::create()
        ->integer('max_connections', default: 100);

    $fields = $schema->getFields();
    expect($fields['max_connections']->getName())->toBe('max_connections');
});

test('IntegerField returns correct default', function () {
    $schema = ConfigSchema::create()
        ->integer('port', default: 3306);

    $fields = $schema->getFields();
    expect($fields['port']->getDefault())->toBe(3306);
});

test('IntegerField returns min and max constraints', function () {
    $schema = ConfigSchema::create()
        ->integer('port', default: 3306, min: 1, max: 65535);

    $fields = $schema->getFields();
    /** @var IntegerField $field */
    $field = $fields['port'];

    expect($field->getMin())->toBe(1)
        ->and($field->getMax())->toBe(65535);
});
