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
            'version' => '1.0',
            'project_type' => 'web',
            'php' => [
                'version' => '8.4',
                'xdebug' => [
                    'enabled' => true,
                    'ide_key' => 'PHPSTORM',
                    'client_host' => 'host.docker.internal',
                ],
            ],
            'services' => [],
            'volumes' => [
                'persist' => ['database'],
            ],
        ];

        $this->expectNotToPerformAssertions();
        $this->validator->validate($config);
    }

    public function test_validates_minimal_configuration(): void
    {
        $config = [
            'project_name' => 'minimal-project',
            'php' => [
                'version' => '8.4',
            ],
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
            'php' => ['version' => '8.4'],
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
            'php' => ['version' => '8.4'],
            'services' => [],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('project_name can only contain letters, numbers, dashes, and underscores');
        $this->validator->validate($config);
    }

    public function test_throws_when_php_missing(): void
    {
        $config = [
            'project_name' => 'my-project',
            'services' => [],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Missing required field: php');
        $this->validator->validate($config);
    }

    public function test_throws_when_php_version_missing(): void
    {
        $config = [
            'project_name' => 'my-project',
            'php' => [],
            'services' => [],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Missing required php field: version');
        $this->validator->validate($config);
    }

    public function test_throws_when_services_missing(): void
    {
        $config = [
            'project_name' => 'my-project',
            'php' => ['version' => '8.4'],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Missing required field: services');
        $this->validator->validate($config);
    }

    public function test_throws_when_services_not_array(): void
    {
        $config = [
            'project_name' => 'my-project',
            'php' => ['version' => '8.4'],
            'services' => 'invalid',
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('services must be an array');
        $this->validator->validate($config);
    }

    public function test_throws_when_xdebug_enabled_not_boolean(): void
    {
        $config = [
            'project_name' => 'my-project',
            'php' => [
                'version' => '8.4',
                'xdebug' => [
                    'enabled' => 'invalid',
                ],
            ],
            'services' => [],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('php.xdebug.enabled must be a boolean');
        $this->validator->validate($config);
    }

    public function test_throws_when_volumes_not_array(): void
    {
        $config = [
            'project_name' => 'my-project',
            'php' => ['version' => '8.4'],
            'services' => [],
            'volumes' => 'invalid',
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('volumes must be an array');
        $this->validator->validate($config);
    }

    public function test_allows_valid_project_name_with_dashes(): void
    {
        $config = [
            'project_name' => 'my-valid-project',
            'php' => ['version' => '8.4'],
            'services' => [],
        ];

        $this->expectNotToPerformAssertions();
        $this->validator->validate($config);
    }

    public function test_allows_valid_project_name_with_underscores(): void
    {
        $config = [
            'project_name' => 'my_valid_project',
            'php' => ['version' => '8.4'],
            'services' => [],
        ];

        $this->expectNotToPerformAssertions();
        $this->validator->validate($config);
    }

    public function test_allows_valid_project_name_with_numbers(): void
    {
        $config = [
            'project_name' => 'my-project-2024',
            'php' => ['version' => '8.4'],
            'services' => [],
        ];

        $this->expectNotToPerformAssertions();
        $this->validator->validate($config);
    }
}
