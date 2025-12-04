<?php

declare(strict_types=1);

// ABOUTME: Detects seaman service types from docker-compose service configurations.
// ABOUTME: Uses fuzzy matching (image, name patterns, ports) with confidence levels.

namespace Seaman\Service;

use Seaman\Enum\Service;
use Seaman\ValueObject\DetectedService;

final readonly class ServiceDetector
{
    /**
     * @param array<string, mixed> $composeService
     */
    public function detectService(string $serviceName, array $composeService): ?DetectedService
    {
        // Strategy 1: Match by image name (highest confidence)
        $imageDetection = $this->detectByImage($composeService);
        if ($imageDetection !== null) {
            return $imageDetection;
        }

        // Strategy 2: Match by service name patterns (medium confidence)
        $nameDetection = $this->detectByServiceName($serviceName, $composeService);
        if ($nameDetection !== null) {
            return $nameDetection;
        }

        // Strategy 3: Match by exposed ports (medium confidence)
        $portDetection = $this->detectByPort($composeService);
        if ($portDetection !== null) {
            return $portDetection;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $composeService
     */
    private function detectByImage(array $composeService): ?DetectedService
    {
        if (!isset($composeService['image']) || !is_string($composeService['image'])) {
            return null;
        }

        $image = $composeService['image'];
        $version = $this->extractVersion($image);

        return match (true) {
            str_contains($image, 'postgres') => new DetectedService(Service::PostgreSQL, $version, 'high'),
            str_contains($image, 'mysql') => new DetectedService(Service::MySQL, $version, 'high'),
            str_contains($image, 'mariadb') => new DetectedService(Service::MariaDB, $version, 'high'),
            str_contains($image, 'mongo') => new DetectedService(Service::MongoDB, $version, 'high'),
            str_contains($image, 'redis') => new DetectedService(Service::Redis, $version, 'high'),
            str_contains($image, 'memcached') => new DetectedService(Service::Memcached, $version, 'high'),
            str_contains($image, 'rabbitmq') => new DetectedService(Service::RabbitMq, $version, 'high'),
            str_contains($image, 'mailpit') || str_contains($image, 'axllent/mailpit') => new DetectedService(Service::Mailpit, $version, 'high'),
            str_contains($image, 'elasticsearch') => new DetectedService(Service::Elasticsearch, $version, 'high'),
            str_contains($image, 'minio') => new DetectedService(Service::MinIO, $version, 'high'),
            str_contains($image, 'dozzle') => new DetectedService(Service::Dozzle, $version, 'high'),
            str_contains($image, 'kafka') => new DetectedService(Service::Kafka, $version, 'high'),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $composeService
     */
    private function detectByServiceName(string $serviceName, array $composeService): ?DetectedService
    {
        $name = strtolower($serviceName);

        return match (true) {
            in_array($name, ['postgres', 'postgresql', 'pgsql'], true) => new DetectedService(Service::PostgreSQL, 'latest', 'medium'),
            in_array($name, ['mysql', 'mariadb'], true) => new DetectedService(Service::MySQL, 'latest', 'medium'),
            in_array($name, ['mongo', 'mongodb'], true) => new DetectedService(Service::MongoDB, 'latest', 'medium'),
            in_array($name, ['redis', 'cache'], true) => new DetectedService(Service::Redis, 'latest', 'medium'),
            in_array($name, ['memcached'], true) => new DetectedService(Service::Memcached, 'latest', 'medium'),
            in_array($name, ['rabbitmq', 'rabbit', 'queue'], true) => new DetectedService(Service::RabbitMq, 'latest', 'medium'),
            in_array($name, ['mailpit', 'mail', 'mailhog'], true) => new DetectedService(Service::Mailpit, 'latest', 'medium'),
            in_array($name, ['elasticsearch', 'elastic', 'search'], true) => new DetectedService(Service::Elasticsearch, 'latest', 'medium'),
            in_array($name, ['minio', 's3'], true) => new DetectedService(Service::MinIO, 'latest', 'medium'),
            in_array($name, ['kafka'], true) => new DetectedService(Service::Kafka, 'latest', 'medium'),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $composeService
     */
    private function detectByPort(array $composeService): ?DetectedService
    {
        if (!isset($composeService['ports'])) {
            return null;
        }

        if (!is_array($composeService['ports'])) {
            return null;
        }

        /** @var array<mixed> $portsArray */
        $portsArray = $composeService['ports'];
        $ports = $this->extractPorts($portsArray);

        return match (true) {
            in_array(5432, $ports, true) => new DetectedService(Service::PostgreSQL, 'latest', 'medium'),
            in_array(3306, $ports, true) => new DetectedService(Service::MySQL, 'latest', 'medium'),
            in_array(27017, $ports, true) => new DetectedService(Service::MongoDB, 'latest', 'medium'),
            in_array(6379, $ports, true) => new DetectedService(Service::Redis, 'latest', 'medium'),
            in_array(11211, $ports, true) => new DetectedService(Service::Memcached, 'latest', 'medium'),
            in_array(5672, $ports, true) || in_array(15672, $ports, true) => new DetectedService(Service::RabbitMq, 'latest', 'medium'),
            in_array(8025, $ports, true) || in_array(1025, $ports, true) => new DetectedService(Service::Mailpit, 'latest', 'medium'),
            in_array(9200, $ports, true) => new DetectedService(Service::Elasticsearch, 'latest', 'medium'),
            in_array(9000, $ports, true) || in_array(9001, $ports, true) => new DetectedService(Service::MinIO, 'latest', 'medium'),
            in_array(9092, $ports, true) => new DetectedService(Service::Kafka, 'latest', 'medium'),
            default => null,
        };
    }

    private function extractVersion(string $image): string
    {
        if (preg_match('/:(.+)$/', $image, $matches)) {
            return $matches[1];
        }

        return 'latest';
    }

    /**
     * @param array<mixed> $ports
     * @return list<int>
     */
    private function extractPorts(array $ports): array
    {
        $extractedPorts = [];

        foreach ($ports as $port) {
            if (is_string($port)) {
                // Format: "8080:80" or "80"
                $parts = explode(':', $port);
                $extractedPorts[] = (int) $parts[0];
            } elseif (is_array($port) && isset($port['published']) && (is_int($port['published']) || is_string($port['published']))) {
                // Long format: {published: 8080, target: 80}
                $extractedPorts[] = (int) $port['published'];
            }
        }

        return $extractedPorts;
    }
}
