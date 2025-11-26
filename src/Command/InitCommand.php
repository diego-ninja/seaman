<?php

declare(strict_types=1);

// ABOUTME: Interactive initialization command.
// ABOUTME: Creates seaman.yaml and sets up Docker environment.

namespace Seaman\Command;

use Seaman\Service\ConfigManager;
use Seaman\Service\DockerComposeGenerator;
use Seaman\Service\DockerfileGenerator;
use Seaman\Service\TemplateRenderer;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ServerConfig;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\VolumeConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;

#[AsCommand(
    name: 'seaman:init',
    description: 'Initialize Seaman configuration interactively',
    aliases: ['init'],
)]
class InitCommand extends Command
{
    public function __construct(private readonly ServiceRegistry $registry)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Seaman Initialization');

        $projectRoot = (string) getcwd();

        // Check if already initialized
        if (file_exists($projectRoot . '/seaman.yaml')) {
            if (!$io->confirm('seaman.yaml already exists. Overwrite?', false)) {
                $io->info('Initialization cancelled.');
                return Command::SUCCESS;
            }
        }

        // Step 1: PHP Version
        /** @var string $phpVersion */
        $phpVersion = $io->choice(
            'Select PHP version',
            ['8.2', '8.3', '8.4'],
            '8.4',
        );

        // Step 2: Server Type
        /** @var string $serverType */
        $serverType = $io->choice(
            'Select server type',
            [
                'symfony' => 'Symfony Server (fastest, hot reload)',
                'nginx-fpm' => 'Nginx + PHP-FPM (production-like)',
                'frankenphp' => 'FrankenPHP + Caddy (modern, HTTP/3)',
            ],
            'symfony',
        );

        // Step 3: Database Selection
        $databaseQuestion = new ChoiceQuestion(
            'Select database (leave empty for none)',
            ['none', 'postgresql', 'mysql', 'mariadb'],
            'postgresql',
        );
        /** @var string $database */
        $database = $io->askQuestion($databaseQuestion);

        // Step 4: Additional Services
        /** @var list<string> $additionalServices */
        $additionalServices = $io->choice(
            'Select additional services (comma-separated)',
            ['redis', 'mailpit', 'minio', 'elasticsearch', 'rabbitmq'],
            'redis,mailpit',
            true,
        );

        // Build configuration
        $server = new ServerConfig($serverType, 8000);
        $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');

        $extensions = [];
        if ($database === 'postgresql') {
            $extensions[] = 'pdo_pgsql';
        } elseif ($database === 'mysql' || $database === 'mariadb') {
            $extensions[] = 'pdo_mysql';
        }

        if (in_array('redis', $additionalServices, true)) {
            $extensions[] = 'redis';
        }

        $extensions[] = 'intl';
        $extensions[] = 'opcache';

        $php = new PhpConfig($phpVersion, $extensions, $xdebug);

        /** @var array<string, \Seaman\ValueObject\ServiceConfig> $services */
        $services = [];
        /** @var list<string> $persistVolumes */
        $persistVolumes = [];

        if ($database !== 'none') {
            $serviceImpl = $this->registry->get($database);
            $defaultConfig = $serviceImpl->getDefaultConfig();
            $services[$database] = $defaultConfig;
            $persistVolumes[] = $database;
        }

        foreach ($additionalServices as $serviceName) {
            $serviceImpl = $this->registry->get($serviceName);
            $defaultConfig = $serviceImpl->getDefaultConfig();
            $services[$serviceName] = $defaultConfig;

            if (in_array($serviceName, ['redis', 'minio', 'elasticsearch', 'rabbitmq'], true)) {
                $persistVolumes[] = $serviceName;
            }
        }

        $config = new Configuration(
            version: '1.0',
            server: $server,
            php: $php,
            services: new ServiceCollection($services),
            volumes: new VolumeConfig($persistVolumes),
        );

        // Save configuration
        $configManager = new ConfigManager($projectRoot);
        $configManager->save($config);

        // Generate Docker files
        $this->generateDockerFiles($config, $projectRoot);

        $io->success('Seaman initialized successfully!');
        $io->info('Next steps:');
        $io->listing([
            'Run "seaman start" to start services',
            'Run "seaman status" to check service status',
            'Run "seaman --help" to see all commands',
        ]);

        return Command::SUCCESS;
    }

    private function generateDockerFiles(Configuration $config, string $projectRoot): void
    {
        $seamanDir = $projectRoot . '/.seaman';
        if (!is_dir($seamanDir)) {
            mkdir($seamanDir, 0755, true);
        }

        $templateDir = __DIR__ . '/../Template';
        $renderer = new TemplateRenderer($templateDir);

        // Generate docker-compose.yml (in project root)
        $composeGenerator = new DockerComposeGenerator($renderer);
        $composeYaml = $composeGenerator->generate($config);
        file_put_contents($projectRoot . '/docker-compose.yml', $composeYaml);

        // Generate sail.yaml (service definitions in .seaman/)
        $this->generateSailConfig($config, $seamanDir);

        // Generate Dockerfile
        $dockerfileGenerator = new DockerfileGenerator($renderer);
        $dockerfile = $dockerfileGenerator->generate($config->server, $config->php);
        file_put_contents($seamanDir . '/Dockerfile', $dockerfile);

        // Generate xdebug-toggle script
        $xdebugScript = $renderer->render('scripts/xdebug-toggle.sh.twig', [
            'xdebug' => $config->php->xdebug,
        ]);
        $scriptPath = $seamanDir . '/scripts/xdebug-toggle.sh';
        $scriptsDir = dirname($scriptPath);
        if (!is_dir($scriptsDir)) {
            mkdir($scriptsDir, 0755, true);
        }
        file_put_contents($scriptPath, $xdebugScript);
        chmod($scriptPath, 0755);
    }

    private function generateSailConfig(Configuration $config, string $seamanDir): void
    {
        $sailConfig = [
            'version' => $config->version,
            'services' => [],
        ];

        foreach ($config->services->all() as $name => $service) {
            $sailConfig['services'][$name] = [
                'enabled' => $service->enabled,
                'type' => $service->type,
                'version' => $service->version,
                'port' => $service->port,
            ];

            if (!empty($service->additionalPorts)) {
                $sailConfig['services'][$name]['additional_ports'] = $service->additionalPorts;
            }

            if (!empty($service->environmentVariables)) {
                $sailConfig['services'][$name]['environment'] = $service->environmentVariables;
            }
        }

        $sailYaml = \Symfony\Component\Yaml\Yaml::dump($sailConfig, 4, 2);
        file_put_contents($seamanDir . '/sail.yaml', $sailYaml);
    }
}
