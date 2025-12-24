<?php

declare(strict_types=1);

// ABOUTME: Unit tests for BooleanField configuration field.
// ABOUTME: Validates boolean field creation and metadata access.

namespace Seaman\Tests\Unit\Plugin\Config;

use Seaman\Plugin\Config\ConfigSchema;

test('BooleanField supports label method', function () {
    $schema = ConfigSchema::create()
        ->boolean('enabled', default: true)
            ->label('Enable Service');

    $fields = $schema->getFields();
    expect($fields['enabled']->getMetadata()->label)->toBe('Enable Service');
});

test('BooleanField supports description method', function () {
    $schema = ConfigSchema::create()
        ->boolean('metrics', default: false)
            ->description('Enable Prometheus metrics');

    $fields = $schema->getFields();
    expect($fields['metrics']->getMetadata()->description)->toBe('Enable Prometheus metrics');
});

test('BooleanField generates label from field name if not set', function () {
    $schema = ConfigSchema::create()
        ->boolean('enable_ssl', default: false);

    $fields = $schema->getFields();
    expect($fields['enable_ssl']->getMetadata()->label)->toBe('Enable Ssl');
});

test('BooleanField chains metadata methods fluently', function () {
    $schema = ConfigSchema::create()
        ->boolean('metrics', default: false)
            ->label('Enable Metrics')
            ->description('Enable Prometheus metrics endpoint');

    $fields = $schema->getFields();
    $metadata = $fields['metrics']->getMetadata();

    expect($metadata->label)->toBe('Enable Metrics')
        ->and($metadata->description)->toBe('Enable Prometheus metrics endpoint')
        ->and($metadata->isSecret)->toBeFalse();
});

test('BooleanField returns correct type', function () {
    $schema = ConfigSchema::create()
        ->boolean('enabled', default: true);

    $fields = $schema->getFields();
    expect($fields['enabled']->getType())->toBe('boolean');
});

test('BooleanField returns correct name', function () {
    $schema = ConfigSchema::create()
        ->boolean('enable_metrics', default: false);

    $fields = $schema->getFields();
    expect($fields['enable_metrics']->getName())->toBe('enable_metrics');
});

test('BooleanField returns correct default', function () {
    $schema = ConfigSchema::create()
        ->boolean('enabled', default: true);

    $fields = $schema->getFields();
    expect($fields['enabled']->getDefault())->toBe(true);
});
