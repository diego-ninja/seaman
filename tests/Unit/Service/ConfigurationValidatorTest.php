<?php

// ABOUTME: Tests for ConfigurationValidator service.
// ABOUTME: Validates configuration validation logic and error cases.

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Seaman\Exception\InvalidConfigurationException;
use Seaman\Service\ConfigurationValidator;

final class ConfigurationValidatorTest extends TestCase
{
    private ConfigurationValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ConfigurationValidator();
    }

    public function test_validates_complete_configuration(): void
    {
        $config = [
            'project_name' => 'my-project',
            'services' => ['php', 'nginx'],
            'database' => [
                'type' => 'mysql',
                'version' => '8.0',
            ],
            'xdebug' => [
                'enabled' => true,
            ],
        ];

        $this->expectNotToPerformAssertions();
        $this->validator->validate($config);
    }

    public function test_validates_minimal_configuration(): void
    {
        $config = [
            'project_name' => 'minimal-project',
            'services' => [],
        ];

        $this->expectNotToPerformAssertions();
        $this->validator->validate($config);
    }

    public function test_throws_when_project_name_missing(): void
    {
        $config = [
            'services' => [],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Missing required field: project_name');
        $this->validator->validate($config);
    }

    public function test_throws_when_project_name_empty(): void
    {
        $config = [
            'project_name' => '',
            'services' => [],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('project_name must be a non-empty string');
        $this->validator->validate($config);
    }

    public function test_throws_when_project_name_invalid_format(): void
    {
        $config = [
            'project_name' => 'invalid project!',
            'services' => [],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('project_name can only contain letters, numbers, dashes, and underscores');
        $this->validator->validate($config);
    }

    public function test_throws_when_services_missing(): void
    {
        $config = [
            'project_name' => 'my-project',
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Missing required field: services');
        $this->validator->validate($config);
    }

    public function test_throws_when_services_not_array(): void
    {
        $config = [
            'project_name' => 'my-project',
            'services' => 'invalid',
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('services must be an array');
        $this->validator->validate($config);
    }

    public function test_throws_when_database_not_array(): void
    {
        $config = [
            'project_name' => 'my-project',
            'services' => [],
            'database' => 'invalid',
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('database must be an array');
        $this->validator->validate($config);
    }

    public function test_throws_when_database_missing_type(): void
    {
        $config = [
            'project_name' => 'my-project',
            'services' => [],
            'database' => [
                'version' => '8.0',
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Missing required database field: type');
        $this->validator->validate($config);
    }

    public function test_throws_when_database_missing_version(): void
    {
        $config = [
            'project_name' => 'my-project',
            'services' => [],
            'database' => [
                'type' => 'mysql',
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Missing required database field: version');
        $this->validator->validate($config);
    }

    public function test_throws_when_xdebug_not_array(): void
    {
        $config = [
            'project_name' => 'my-project',
            'services' => [],
            'xdebug' => 'invalid',
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('xdebug must be an array');
        $this->validator->validate($config);
    }

    public function test_allows_valid_project_name_with_dashes(): void
    {
        $config = [
            'project_name' => 'my-valid-project',
            'services' => [],
        ];

        $this->expectNotToPerformAssertions();
        $this->validator->validate($config);
    }

    public function test_allows_valid_project_name_with_underscores(): void
    {
        $config = [
            'project_name' => 'my_valid_project',
            'services' => [],
        ];

        $this->expectNotToPerformAssertions();
        $this->validator->validate($config);
    }

    public function test_allows_valid_project_name_with_numbers(): void
    {
        $config = [
            'project_name' => 'my-project-2024',
            'services' => [],
        ];

        $this->expectNotToPerformAssertions();
        $this->validator->validate($config);
    }
}
