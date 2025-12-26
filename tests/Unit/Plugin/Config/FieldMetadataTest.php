<?php

declare(strict_types=1);

// ABOUTME: Unit tests for FieldMetadata value object.
// ABOUTME: Validates metadata storage for configuration fields.

namespace Seaman\Tests\Unit\Plugin\Config;

use Seaman\Plugin\Config\FieldMetadata;

test('FieldMetadata stores label', function () {
    $metadata = new FieldMetadata(label: 'MySQL Version');

    expect($metadata->label)->toBe('MySQL Version');
});

test('FieldMetadata stores description', function () {
    $metadata = new FieldMetadata(
        label: 'Port',
        description: 'External port to expose',
    );

    expect($metadata->description)->toBe('External port to expose');
});

test('FieldMetadata defaults description to empty string', function () {
    $metadata = new FieldMetadata(label: 'Port');

    expect($metadata->description)->toBe('');
});

test('FieldMetadata stores secret flag', function () {
    $metadata = new FieldMetadata(
        label: 'Password',
        isSecret: true,
    );

    expect($metadata->isSecret)->toBeTrue();
});

test('FieldMetadata defaults secret to false', function () {
    $metadata = new FieldMetadata(label: 'Username');

    expect($metadata->isSecret)->toBeFalse();
});
