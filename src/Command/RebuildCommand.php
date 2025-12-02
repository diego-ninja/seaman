<?php

declare(strict_types=1);

// ABOUTME: Rebuilds Docker images.
// ABOUTME: Builds image from .seaman/Dockerfile and restarts services.

namespace Seaman\Command;

use Seaman\Contracts\Decorable;
use Seaman\Service\ConfigManager;
use Seaman\Service\Container\DozzleService;
use Seaman\Service\Container\ElasticsearchService;
use Seaman\Service\Container\MailpitService;
use Seaman\Service\Container\MariadbService;
use Seaman\Service\Container\MemcachedService;
use Seaman\Service\Container\MinioService;
use Seaman\Service\Container\MongodbService;
use Seaman\Service\Container\MysqlService;
use Seaman\Service\Container\PostgresqlService;
use Seaman\Service\Container\RabbitmqService;
use Seaman\Service\Container\RedisService;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\DockerImageBuilder;
use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaman:rebuild',
    description: 'Rebuild docker images',
    aliases: ['rebuild'],
)]
class RebuildCommand extends AbstractSeamanCommand implements Decorable
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = (string) getcwd();

        // Create service registry
        $registry = new ServiceRegistry();
        $registry->register(new PostgresqlService());
        $registry->register(new MysqlService());
        $registry->register(new MariadbService());
        $registry->register(new MongodbService());
        $registry->register(new RedisService());
        $registry->register(new MemcachedService());
        $registry->register(new MailpitService());
        $registry->register(new MinioService());
        $registry->register(new ElasticsearchService());
        $registry->register(new RabbitmqService());
        $registry->register(new DozzleService());

        // Load configuration to get PHP version
        $configManager = new ConfigManager($projectRoot, $registry);
        $config = $configManager->load();

        // Build Docker image
        $builder = new DockerImageBuilder($projectRoot, $config->php->version);
        $buildResult = $builder->build();

        if (!$buildResult->isSuccessful()) {
            return Command::FAILURE;
        }

        $manager = new DockerManager($projectRoot);
        $restartResult = $manager->restart();

        if ($restartResult->isSuccessful()) {
            return Command::SUCCESS;
        }

        Terminal::output()->writeln($restartResult->errorOutput);
        return Command::FAILURE;
    }
}
