<?php

declare(strict_types=1);

// ABOUTME: Tests for ComposeImporter service.
// ABOUTME: Validates docker-compose.yaml import and service categorization.

namespace Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use Seaman\Enum\Confidence;
use Seaman\Enum\Service;
use Seaman\Service\ComposeImporter;
use Seaman\Service\Detector\ServiceDetector;
use Seaman\Tests\TestCase;
use Seaman\ValueObject\DetectedService;

final class ComposeImporterTest extends TestCase
{
    private ComposeImporter $importer;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new ComposeImporter(new ServiceDetector());
        $this->tempDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Cleanup temp directory
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    #[Test]
    public function it_imports_recognized_services(): void
    {
        $composeContent = <<<YAML
services:
  postgres:
    image: postgres:16
  redis:
    image: redis:7-alpine
YAML;
        $composePath = $this->tempDir . '/docker-compose.yml';
        file_put_contents($composePath, $composeContent);

        $result = $this->importer->import($composePath);

        $this->assertCount(2, $result->recognized);
        $this->assertSame(0, $result->custom->count());
        $this->assertArrayHasKey('postgres', $result->recognized);
        $this->assertArrayHasKey('redis', $result->recognized);
    }

    #[Test]
    public function it_separates_custom_services(): void
    {
        $composeContent = <<<YAML
services:
  postgres:
    image: postgres:16
  my-app:
    image: myapp:latest
    ports:
      - "8080:80"
YAML;
        $composePath = $this->tempDir . '/docker-compose.yml';
        file_put_contents($composePath, $composeContent);

        $result = $this->importer->import($composePath);

        $this->assertCount(1, $result->recognized);
        $this->assertSame(1, $result->custom->count());
        $this->assertArrayHasKey('postgres', $result->recognized);
        $this->assertTrue($result->custom->has('my-app'));
    }

    #[Test]
    public function it_extracts_detected_service_with_version(): void
    {
        $composeContent = <<<YAML
services:
  postgres:
    image: postgres:16
    ports:
      - "5432:5432"
    environment:
      POSTGRES_PASSWORD: secret
YAML;
        $composePath = $this->tempDir . '/docker-compose.yml';
        file_put_contents($composePath, $composeContent);

        $result = $this->importer->import($composePath);

        $this->assertArrayHasKey('postgres', $result->recognized);
        $recognized = $result->recognized['postgres'];

        $this->assertInstanceOf(DetectedService::class, $recognized->detected);
        $this->assertSame(Service::PostgreSQL, $recognized->detected->type);
        $this->assertSame('16', $recognized->detected->version);
        $this->assertSame(Confidence::High, $recognized->detected->confidence);
    }

    #[Test]
    public function it_preserves_original_config_for_recognized_services(): void
    {
        $composeContent = <<<YAML
services:
  postgres:
    image: postgres:16
    ports:
      - "5432:5432"
    environment:
      POSTGRES_PASSWORD: secret
      POSTGRES_USER: admin
YAML;
        $composePath = $this->tempDir . '/docker-compose.yml';
        file_put_contents($composePath, $composeContent);

        $result = $this->importer->import($composePath);

        $recognized = $result->recognized['postgres'];
        $this->assertArrayHasKey('image', $recognized->config);
        $this->assertArrayHasKey('ports', $recognized->config);
        $this->assertArrayHasKey('environment', $recognized->config);
        $this->assertSame('postgres:16', $recognized->config['image']);
    }

    #[Test]
    public function it_preserves_full_config_for_custom_services(): void
    {
        $composeContent = <<<YAML
services:
  my-app:
    image: myapp:latest
    ports:
      - "8080:80"
    environment:
      API_KEY: secret123
    volumes:
      - ./data:/app/data
YAML;
        $composePath = $this->tempDir . '/docker-compose.yml';
        file_put_contents($composePath, $composeContent);

        $result = $this->importer->import($composePath);

        $customConfig = $result->custom->get('my-app');
        $this->assertSame('myapp:latest', $customConfig['image']);
        $this->assertContains('8080:80', $customConfig['ports']);
        $this->assertSame('secret123', $customConfig['environment']['API_KEY']);
        $this->assertContains('./data:/app/data', $customConfig['volumes']);
    }

    #[Test]
    public function it_throws_exception_for_missing_file(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('docker-compose file not found');

        $this->importer->import($this->tempDir . '/non-existent.yml');
    }

    #[Test]
    public function it_throws_exception_for_invalid_yaml(): void
    {
        $composePath = $this->tempDir . '/docker-compose.yml';
        file_put_contents($composePath, "invalid: yaml: :\n  bad content");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse');

        $this->importer->import($composePath);
    }

    #[Test]
    public function it_throws_exception_when_no_services_found(): void
    {
        $composeContent = <<<YAML
version: "3.8"
networks:
  default:
    driver: bridge
YAML;
        $composePath = $this->tempDir . '/docker-compose.yml';
        file_put_contents($composePath, $composeContent);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No services found');

        $this->importer->import($composePath);
    }

    #[Test]
    public function it_handles_docker_compose_yaml_extension(): void
    {
        $composeContent = <<<YAML
services:
  redis:
    image: redis:7
YAML;
        $composePath = $this->tempDir . '/docker-compose.yaml';
        file_put_contents($composePath, $composeContent);

        $result = $this->importer->import($composePath);

        $this->assertCount(1, $result->recognized);
        $this->assertArrayHasKey('redis', $result->recognized);
    }

    #[Test]
    public function it_detects_multiple_service_types(): void
    {
        $composeContent = <<<YAML
services:
  db:
    image: postgres:16
  cache:
    image: redis:7-alpine
  queue:
    image: rabbitmq:3.13-management
  mail:
    image: axllent/mailpit:latest
  search:
    image: elasticsearch:8.12.0
YAML;
        $composePath = $this->tempDir . '/docker-compose.yml';
        file_put_contents($composePath, $composeContent);

        $result = $this->importer->import($composePath);

        $this->assertCount(5, $result->recognized);
        $this->assertSame(Service::PostgreSQL, $result->recognized['db']->detected->type);
        $this->assertSame(Service::Redis, $result->recognized['cache']->detected->type);
        $this->assertSame(Service::RabbitMq, $result->recognized['queue']->detected->type);
        $this->assertSame(Service::Mailpit, $result->recognized['mail']->detected->type);
        $this->assertSame(Service::Elasticsearch, $result->recognized['search']->detected->type);
    }

    #[Test]
    public function it_returns_empty_recognized_when_all_services_are_custom(): void
    {
        $composeContent = <<<YAML
services:
  my-app:
    image: myapp:latest
  my-worker:
    image: myworker:latest
YAML;
        $composePath = $this->tempDir . '/docker-compose.yml';
        file_put_contents($composePath, $composeContent);

        $result = $this->importer->import($composePath);

        $this->assertCount(0, $result->recognized);
        $this->assertSame(2, $result->custom->count());
    }
}
