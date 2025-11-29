<?php

declare(strict_types=1);

// ABOUTME: Interactive initialization command.
// ABOUTME: Creates seaman.yaml and sets up Docker environment.

namespace Seaman\Command;

use Seaman\Service\ConfigManager;
use Seaman\Service\DockerComposeGenerator;
use Seaman\Service\DockerImageBuilder;
use Seaman\Service\TemplateRenderer;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\SymfonyDetector;
use Seaman\Service\ProjectBootstrapper;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ProjectType;
use Seaman\ValueObject\XdebugConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\VolumeConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\multiselect;

#[AsCommand(
    name: 'seaman:init',
    description: 'Initialize Seaman configuration interactively',
    aliases: ['init'],
)]
class InitCommand extends Command
{
    public function __construct(
        private readonly ServiceRegistry $registry,
        private readonly SymfonyDetector $detector,
        private readonly ProjectBootstrapper $bootstrapper,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = (string) getcwd();

        info('Initializing seaman application...');

        // Check if seaman.yaml already exists
        if (file_exists($projectRoot . '/seaman.yaml')) {
            if (!confirm(
                label: 'seaman.yaml already exists. Overwrite?',
                default: false,
            )) {
                info('Initialization cancelled.');
                return Command::SUCCESS;
            }
        }

        // Detect Symfony application
        $detection = $this->detector->detect($projectRoot);

        if (!$detection->isSymfonyProject) {
            if ($detection->matchedIndicators === 1) {
                info('Found some Symfony files but project seems incomplete.');
            }

            $shouldBootstrap = confirm(
                label: 'No Symfony application detected. Create new project?',
                default: true,
            );

            if (!$shouldBootstrap) {
                info('Please create a Symfony project first, then run init again.');
                return Command::SUCCESS;
            }

            // Bootstrap new Symfony project
            $projectType = $this->selectProjectType();
            $projectName = $this->getProjectName($projectRoot);

            info('Creating Symfony project...');

            if (!$this->bootstrapper->bootstrap($projectType, $projectName, dirname($projectRoot))) {
                info('Failed to create Symfony project.');
                return Command::FAILURE;
            }

            // Change to new project directory
            $projectRoot = dirname($projectRoot) . '/' . $projectName;
            chdir($projectRoot);

            info('Symfony project created successfully!');
        }

        // Continue with Docker configuration...
        $database = $this->selectDatabase();
        $services = $this->selectServices($projectType ?? null);
        $xdebugEnabled = confirm(label: 'Do you want to enable Xdebug?', default: false);

        // Build configuration
        $xdebug = new XdebugConfig($xdebugEnabled, 'seaman', 'host.docker.internal');

        $extensions = [];
        if ($database === 'postgresql') {
            $extensions[] = 'pdo_pgsql';
        } elseif (in_array($database, ['mysql', 'mariadb'], true)) {
            $extensions[] = 'pdo_mysql';
        }

        if (in_array('redis', $services, true)) {
            $extensions[] = 'redis';
        }

        $php = new PhpConfig('8.4', $extensions, $xdebug);

        /** @var array<string, \Seaman\ValueObject\ServiceConfig> $serviceConfigs */
        $serviceConfigs = [];
        /** @var list<string> $persistVolumes */
        $persistVolumes = [];

        if ($database !== 'none') {
            $serviceImpl = $this->registry->get($database);
            $defaultConfig = $serviceImpl->getDefaultConfig();
            $serviceConfigs[$database] = $defaultConfig;
            $persistVolumes[] = $database;
        }

        foreach ($services as $serviceName) {
            $serviceImpl = $this->registry->get($serviceName);
            $defaultConfig = $serviceImpl->getDefaultConfig();
            $serviceConfigs[$serviceName] = $defaultConfig;

            if (in_array($serviceName, ['redis', 'minio', 'elasticsearch', 'rabbitmq'], true)) {
                $persistVolumes[] = $serviceName;
            }
        }

        $config = new Configuration(
            version: '1.0',
            php: $php,
            services: new ServiceCollection($serviceConfigs),
            volumes: new VolumeConfig($persistVolumes),
        );

        // Show configuration summary
        $this->showSummary($config, $database, $services, $xdebugEnabled, $projectType ?? null);

        if (!confirm(label: 'Continue with this configuration?', default: true)) {
            info('Initialization cancelled.');
            return Command::SUCCESS;
        }

        // Generate Docker files
        $this->generateDockerFiles($config, $projectRoot);

        info('✓ Seaman initialized successfully!');
        info('');
        info('Next steps:');
        info('  1. Run \'seaman start\' to start your containers');
        info('  2. Run \'seaman status\' to check service status');
        info('  3. Your application will be available at http://localhost:8000');
        info('');
        info('Useful commands:');
        info('  • seaman shell - Access container shell');
        info('  • seaman logs - View container logs');
        info('  • seaman composer - Run Composer commands');
        info('  • seaman console - Run Symfony console commands');
        info('  • seaman --help - See all available commands');

        return Command::SUCCESS;
    }

    private function selectProjectType(): ProjectType
    {
        $choice = select(
            label: 'Select project type',
            options: [
                'web' => ProjectType::WebApplication->getLabel() . ' - ' . ProjectType::WebApplication->getDescription(),
                'api' => ProjectType::ApiPlatform->getLabel() . ' - ' . ProjectType::ApiPlatform->getDescription(),
                'microservice' => ProjectType::Microservice->getLabel() . ' - ' . ProjectType::Microservice->getDescription(),
                'skeleton' => ProjectType::Skeleton->getLabel() . ' - ' . ProjectType::Skeleton->getDescription(),
            ],
            default: 'web',
        );

        return ProjectType::from($choice);
    }

    private function getProjectName(string $currentDir): string
    {
        // Check if directory is empty
        $files = array_diff(scandir($currentDir) ?: [], ['.', '..']);

        if (count($files) > 0) {
            info('Current directory is not empty.');
            // For now, just use a default - we'll enhance this later
            return 'symfony-app';
        }

        return basename($currentDir);
    }

    private function selectDatabase(): string
    {
        $choice = select(
            label: 'Select database (default: postgresql)',
            options: ['postgresql', 'mysql', 'mariadb', 'sqlite', 'none'],
            default: 'postgresql',
        );

        return (string) $choice;
    }

    /**
     * @param ProjectType|null $projectType
     * @return list<string>
     */
    private function selectServices(?ProjectType $projectType): array
    {
        $defaults = $this->getDefaultServices($projectType);

        $selected = multiselect(
            label: 'Select additional services',
            options: ['redis', 'mailpit', 'minio', 'elasticsearch', 'rabbitmq'],
            default: $defaults,
        );

        // Convert to list<string>
        return array_values(array_map('strval', $selected));
    }

    /**
     * @param ProjectType|null $projectType
     * @return list<string>
     */
    private function getDefaultServices(?ProjectType $projectType): array
    {
        if ($projectType === null) {
            return ['redis'];
        }

        return match ($projectType) {
            ProjectType::WebApplication => ['redis', 'mailpit'],
            ProjectType::ApiPlatform, ProjectType::Microservice => ['redis'],
            ProjectType::Skeleton => [],
        };
    }

    /**
     * @param list<string> $services
     */
    private function showSummary(
        Configuration $config,
        string $database,
        array $services,
        bool $xdebugEnabled,
        ?ProjectType $projectType,
    ): void {
        info('');
        info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        info('Configuration Summary');
        info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($projectType !== null) {
            info('Project Type: ' . $projectType->getLabel());
        }

        info('Database: ' . ($database === 'none' ? 'None' : ucfirst($database)));
        info('Services: ' . (empty($services) ? 'None' : implode(', ', array_map('ucfirst', $services))));
        info('Xdebug: ' . ($xdebugEnabled ? 'Enabled' : 'Disabled'));
        info('PHP Version: 8.4');
        info('');
        info('This will create:');
        info('  • seaman.yaml');
        info('  • docker-compose.yml');
        info('  • .seaman/ directory');
        info('  • Dockerfile (if not present)');
        info('  • Docker image: seaman/seaman:latest');
        info('');
    }

    private function generateDockerFiles(Configuration $config, string $projectRoot): void
    {
        $seamanDir = $projectRoot . '/.seaman';
        if (!is_dir($seamanDir)) {
            mkdir($seamanDir, 0755, true);
        }

        // Handle Dockerfile
        $rootDockerfile = $projectRoot . '/Dockerfile';
        if (!file_exists($rootDockerfile)) {
            $shouldUseTemplate = confirm(
                label: 'No Dockerfile found. Use Seaman\'s template Dockerfile?',
                default: true,
            );

            if (!$shouldUseTemplate) {
                info('Please add a Dockerfile to your project root and run init again.');
                throw new \RuntimeException('Dockerfile required');
            }

            // Copy Seaman's template Dockerfile
            $templateDockerfile = __DIR__ . '/../../Dockerfile';
            if (!file_exists($templateDockerfile)) {
                info('Seaman template Dockerfile not found.');
                throw new \RuntimeException('Template Dockerfile missing');
            }

            copy($templateDockerfile, $rootDockerfile);
            info('✓ Copied Seaman template Dockerfile to project root');
        }

        $templateDir = __DIR__ . '/../Template';
        $renderer = new TemplateRenderer($templateDir);

        // Generate docker-compose.yml (in project root)
        $composeGenerator = new DockerComposeGenerator($renderer);
        $composeYaml = $composeGenerator->generate($config);
        file_put_contents($projectRoot . '/docker-compose.yml', $composeYaml);

        // Save configuration
        $configManager = new ConfigManager($projectRoot);
        $configManager->save($config);

        // Generate xdebug-toggle script (needed by Dockerfile build and runtime)
        $xdebugScript = $renderer->render('scripts/xdebug-toggle.sh.twig', [
            'xdebug' => $config->php->xdebug,
        ]);

        // Create in project root for Docker build
        $rootScriptDir = $projectRoot . '/scripts';
        if (!is_dir($rootScriptDir)) {
            mkdir($rootScriptDir, 0755, true);
        }
        file_put_contents($rootScriptDir . '/xdebug-toggle.sh', $xdebugScript);
        chmod($rootScriptDir . '/xdebug-toggle.sh', 0755);

        // Also create in .seaman for volume mount reference
        $seamanScriptDir = $seamanDir . '/scripts';
        if (!is_dir($seamanScriptDir)) {
            mkdir($seamanScriptDir, 0755, true);
        }
        file_put_contents($seamanScriptDir . '/xdebug-toggle.sh', $xdebugScript);
        chmod($seamanScriptDir . '/xdebug-toggle.sh', 0755);

        // Copy root Dockerfile to .seaman/ (after xdebug script is created)
        copy($rootDockerfile, $seamanDir . '/Dockerfile');

        // Build Docker image
        info('Building Docker image...');
        $builder = new DockerImageBuilder($projectRoot);
        $result = $builder->build();

        if (!$result->isSuccessful()) {
            info('Failed to build Docker image');
            info($result->errorOutput);
            throw new \RuntimeException('Docker build failed');
        }

        info('✓ Docker image built successfully!');
    }
}
