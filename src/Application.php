<?php

declare(strict_types=1);

// ABOUTME: Main Symfony Console Application for Seaman.
// ABOUTME: Registers and manages all CLI commands.

namespace Seaman;

use Seaman\Command\InitCommand;
use Seaman\Command\RestartCommand;
use Seaman\Command\ServiceAddCommand;
use Seaman\Command\ServiceListCommand;
use Seaman\Command\ServiceRemoveCommand;
use Seaman\Command\StartCommand;
use Seaman\Command\StopCommand;
use Seaman\Service\ConfigManager;
use Seaman\Service\Container\ElasticsearchService;
use Seaman\Service\Container\MailpitService;
use Seaman\Service\Container\MariadbService;
use Seaman\Service\Container\MinioService;
use Seaman\Service\Container\MysqlService;
use Seaman\Service\Container\PostgresqlService;
use Seaman\Service\Container\RabbitmqService;
use Seaman\Service\Container\RedisService;
use Seaman\Service\Container\ServiceRegistry;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('Seaman', '1.0.0');

        $projectRoot = getcwd();
        if ($projectRoot === false) {
            throw new \RuntimeException('Unable to determine current working directory');
        }

        $configManager = new ConfigManager($projectRoot);
        $registry = $this->createServiceRegistry();

        $this->addCommands([
            new ServiceListCommand($configManager, $registry),
            new ServiceAddCommand($configManager, $registry),
            new ServiceRemoveCommand($configManager, $registry),
            new InitCommand($registry),
            new StartCommand(),
            new StopCommand(),
            new RestartCommand(),
        ]);
    }

    private function createServiceRegistry(): ServiceRegistry
    {
        $registry = new ServiceRegistry();

        $registry->register(new PostgresqlService());
        $registry->register(new MysqlService());
        $registry->register(new MariadbService());
        $registry->register(new RedisService());
        $registry->register(new MailpitService());
        $registry->register(new MinioService());
        $registry->register(new ElasticsearchService());
        $registry->register(new RabbitmqService());

        return $registry;
    }
}
