<?php

declare(strict_types=1);

namespace Seaman\Enum;

enum Service: string
{
    case MySQL = 'mysql';
    case SQLite = 'sqlite';
    case PostgreSQL = 'pgsql';
    case MariaDB = 'mariadb';
    case MongoDB = 'mongodb';

    case Redis = 'redis';
    case Memcached = 'memcached';
    case Mailpit = 'mailpit';
    case Minio = 'minio';
    case Elasticsearch = 'elasticsearch';
    case Kafka = 'kafka';
    case RabbitMq = 'rabbitmq';

    case None = 'none';

    /**
     * @return list<string>
     */
    public static function services(): array
    {
        return [
            self::Redis->value,
            self::Memcached->value,
            self::Mailpit->value,
            self::Minio->value,
            self::Elasticsearch->value,
            self::Kafka->value,
            self::RabbitMq->value,
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
            self::None->value,
        ];
    }

}
