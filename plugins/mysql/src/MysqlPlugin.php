<?php

declare(strict_types=1);

// ABOUTME: MySQL bundled plugin for Seaman.
// ABOUTME: Provides MySQL database service with health checks and data persistence.

namespace Seaman\Plugin\Mysql;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\DatabaseOperations;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/mysql',
    version: '1.0.0',
    description: 'MySQL database service for Seaman',
)]
final class MysqlPlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: '8.0')
            ->integer('port', default: 3306, min: 1, max: 65535)
            ->string('database', default: 'seaman')
            ->string('user', default: 'seaman')
            ->string('password', default: 'seaman')
            ->string('root_password', default: 'root');

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/mysql';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'MySQL database service for Seaman';
    }

    public function configSchema(): ConfigSchema
    {
        return $this->schema;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function configure(array $values): void
    {
        $this->config = $this->schema->validate($values);
    }

    #[ProvidesService(name: 'mysql', category: ServiceCategory::Database)]
    public function mysqlService(): ServiceDefinition
    {
        $port = $this->config['port'];
        assert(is_int($port));

        return new ServiceDefinition(
            name: 'mysql',
            template: __DIR__ . '/../templates/mysql.yaml.twig',
            displayName: 'MySQL',
            description: 'Popular open-source relational database',
            icon: '🐬',
            category: ServiceCategory::Database,
            ports: [$port],
            internalPorts: [3306],
            defaultConfig: [
                'version' => $this->config['version'],
                'port' => $this->config['port'],
                'database' => $this->config['database'],
                'user' => $this->config['user'],
                'password' => $this->config['password'],
                'root_password' => $this->config['root_password'],
                'environment' => [
                    'MYSQL_DATABASE' => $this->config['database'],
                    'MYSQL_USER' => $this->config['user'],
                    'MYSQL_PASSWORD' => $this->config['password'],
                    'MYSQL_ROOT_PASSWORD' => $this->config['root_password'],
                ],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD', 'mysqladmin', 'ping', '-h', 'localhost'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
            databaseOperations: new DatabaseOperations(
                dumpCommand: static fn($config) => [
                    'mysqldump',
                    '-u',
                    $config->environmentVariables['MYSQL_USER'] ?? 'root',
                    '-p' . ($config->environmentVariables['MYSQL_PASSWORD'] ?? ''),
                    $config->environmentVariables['MYSQL_DATABASE'] ?? 'mysql',
                ],
                restoreCommand: static fn($config) => [
                    'mysql',
                    '-u',
                    $config->environmentVariables['MYSQL_USER'] ?? 'root',
                    '-p' . ($config->environmentVariables['MYSQL_PASSWORD'] ?? ''),
                    $config->environmentVariables['MYSQL_DATABASE'] ?? 'mysql',
                ],
                shellCommand: static fn($config) => [
                    'mysql',
                    '-u',
                    $config->environmentVariables['MYSQL_USER'] ?? 'root',
                    '-p' . ($config->environmentVariables['MYSQL_PASSWORD'] ?? ''),
                    $config->environmentVariables['MYSQL_DATABASE'] ?? 'mysql',
                ],
            ),
        );
    }
}
