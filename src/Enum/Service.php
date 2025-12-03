<?php

declare(strict_types=1);

namespace Seaman\Enum;

enum Service: string
{
    case App = 'app';

    case MySQL = 'mysql';
    case SQLite = 'sqlite';
    case PostgreSQL = 'postgresql';
    case MariaDB = 'mariadb';
    case MongoDB = 'mongodb';

    case Redis = 'redis';
    case Memcached = 'memcached';
    case Mailpit = 'mailpit';
    case MinIO = 'minio';
    case Elasticsearch = 'elasticsearch';
    case Kafka = 'kafka';
    case RabbitMq = 'rabbitmq';
    case Dozzle = 'dozzle';

    case None = 'none';

    /**
     * @throws \Exception
     */
    public function description(): string
    {
        return match ($this) {
            self::App => 'Symfony 7+ application',
            self::MySQL => 'MySQL relational database',
            self::SQLite => 'SQLite file-based relational database',
            self::PostgreSQL => 'PostgreSQL relational database',
            self::MariaDB => 'MariaDB relational database',
            self::MongoDB => 'MongoDB NoSQL database',
            self::Redis => 'Redis cache and session storage',
            self::Memcached => 'Memcached cache storage',
            self::Mailpit => 'Email testing tool - captures and displays emails',
            self::MinIO => 'S3-compatible object storage',
            self::Dozzle => 'Realtime log viewer for containers',
            self::Elasticsearch => 'Elasticsearch search engine',
            self::RabbitMq => 'RabbitMQ message queue',
            self::Kafka => 'Apache Kafka distributed event streaming platform',
            self::None => throw new \Exception('To be implemented'),
        };
    }

    public function port(): int
    {
        return match ($this) {
            self::App => 8000,
            self::MySQL, self::MariaDB => 3306,
            self::SQLite, self::None => 0,
            self::PostgreSQL => 5432,
            self::MongoDB => 27017,
            self::Redis => 6379,
            self::Memcached => 11211,
            self::Dozzle => 8080,
            self::Elasticsearch => 9200,
            self::Mailpit => 8025,
            self::MinIO => 9000,
            self::Kafka => 9092,
            self::RabbitMq => 5672,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::App => '📦',
            self::Redis => '🧵',
            self::RabbitMq => '🐰',
            self::Mailpit => '📧',
            self::PostgreSQL => '🐘',
            self::Dozzle => '🗒️ ',
            self::Elasticsearch => '🔎',
            self::MySQL, self::MariaDB => '🐬',
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
            self::Memcached->value,
            self::Mailpit->value,
            self::MinIO->value,
            self::Elasticsearch->value,
            self::Kafka->value,
            self::RabbitMq->value,
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

}
