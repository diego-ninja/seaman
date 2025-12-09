<?php

declare(strict_types=1);

namespace Seaman\Enum;

enum Service: string
{
    case App = 'app';
    case Traefik = 'traefik';

    case MySQL = 'mysql';
    case SQLite = 'sqlite';
    case PostgreSQL = 'postgresql';
    case MariaDB = 'mariadb';
    case MongoDB = 'mongodb';

    case Redis = 'redis';
    case Valkey = 'valkey';
    case Memcached = 'memcached';
    case Mailpit = 'mailpit';
    case MinIO = 'minio';
    case Elasticsearch = 'elasticsearch';
    case OpenSearch = 'opensearch';
    case Kafka = 'kafka';
    case RabbitMq = 'rabbitmq';
    case Mercure = 'mercure';
    case Soketi = 'soketi';
    case Dozzle = 'dozzle';

    case None = 'none';

    /**
     * @throws \Exception
     */
    public function description(): string
    {
        return match ($this) {
            self::App => 'Symfony 7+ application',
            self::Traefik => 'Traefik reverse proxy with HTTPS support',
            self::MySQL => 'MySQL relational database',
            self::SQLite => 'SQLite file-based relational database',
            self::PostgreSQL => 'PostgreSQL relational database',
            self::MariaDB => 'MariaDB relational database',
            self::MongoDB => 'MongoDB NoSQL database',
            self::Redis => 'Redis cache and session storage',
            self::Valkey => 'Valkey cache and session storage (Redis fork)',
            self::Memcached => 'Memcached cache storage',
            self::Mailpit => 'Email testing tool - captures and displays emails',
            self::MinIO => 'S3-compatible object storage',
            self::Dozzle => 'Realtime log viewer for containers',
            self::Elasticsearch => 'Elasticsearch search engine',
            self::OpenSearch => 'OpenSearch search and analytics engine',
            self::RabbitMq => 'RabbitMQ message queue',
            self::Kafka => 'Apache Kafka distributed event streaming platform',
            self::Mercure => 'Mercure real-time updates hub for Symfony',
            self::Soketi => 'Soketi WebSocket server (Pusher compatible)',
            self::None => throw new \Exception('To be implemented'),
        };
    }

    public function port(): int
    {
        return match ($this) {
            self::App => 8000,
            self::Traefik => 443,
            self::MySQL, self::MariaDB => 3306,
            self::SQLite, self::None => 0,
            self::PostgreSQL => 5432,
            self::MongoDB => 27017,
            self::Redis, self::Valkey => 6379,
            self::Memcached => 11211,
            self::Dozzle => 9080,
            self::Elasticsearch, self::OpenSearch => 9200,
            self::Mailpit => 8025,
            self::MinIO => 9000,
            self::Kafka => 9092,
            self::RabbitMq => 5672,
            self::Mercure => 3000,
            self::Soketi => 6001,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::App => '📦',
            self::Traefik => '🔀',
            self::Redis, self::Valkey => '🧵',
            self::RabbitMq => '🐰',
            self::Mailpit => '📧',
            self::PostgreSQL => '🐘',
            self::Dozzle => '🗒️ ',
            self::Elasticsearch, self::OpenSearch => '🔎',
            self::MySQL, self::MariaDB => '🐬',
            self::Mercure => '📡',
            self::Soketi => '🔌',
            default => '⚙️ ',
        };
    }

    /**
     * @return list<string>
     */
    public static function services(): array
    {
        return [
            self::Redis->value,
            self::Valkey->value,
            self::Memcached->value,
            self::Mailpit->value,
            self::MinIO->value,
            self::Elasticsearch->value,
            self::OpenSearch->value,
            self::Kafka->value,
            self::RabbitMq->value,
            self::Mercure->value,
            self::Soketi->value,
            self::Dozzle->value,
            self::None->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function databases(): array
    {
        return [
            self::MySQL->value,
            self::MariaDB->value,
            self::MongoDB->value,
            self::PostgreSQL->value,
            self::SQLite->value,
        ];
    }

    /**
     * Check if this service is required and cannot be disabled.
     */
    public function isRequired(): bool
    {
        return $this === self::Traefik;
    }

}
