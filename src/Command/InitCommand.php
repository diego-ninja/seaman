<?php

declare(strict_types=1);

// ABOUTME: Interactive initialization command.
// ABOUTME: Creates seaman.yaml and sets up Docker environment.

namespace Seaman\Command;

use Seaman\Contracts\Decorable;
use Seaman\Enum\PhpVersion;
use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\DockerComposeGenerator;
use Seaman\Service\DockerImageBuilder;
use Seaman\Service\ProjectBootstrapper;
use Seaman\Service\SymfonyDetector;
use Seaman\Service\TemplateRenderer;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

#[AsCommand(
    name: 'seaman:init',
    description: 'Initialize Seaman configuration interactively',
    aliases: ['init'],
)]
class InitCommand extends AbstractSeamanCommand implements Decorable
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

        // Check if seaman.yaml already exists
        if (file_exists($projectRoot . '/.seaman/seaman.yaml')) {
            if (!confirm(
                label: 'seaman.yaml already exists. Overwrite?',
                default: false,
            )) {
                info('Initialization cancelled.');
                return Command::SUCCESS;
            }
        }

        $projectType = $this->bootstrapSymfonyProject($projectRoot);
        $phpVersion = $this->selectPhpVersion();
        $database = $this->selectDatabase();
        $services = $this->selectServices($projectType);
        $xdebug = $this->enableXdebug();
        $php = new PhpConfig($phpVersion, $xdebug);

        /** @var array<string, ServiceConfig> $serviceConfigs */
        $serviceConfigs = [];
        /** @var list<Service> $persistVolumes */
        $persistVolumes = [];

        if ($database !== Service::None) {
            $serviceImpl = $this->registry->get($database);
            $defaultConfig = $serviceImpl->getDefaultConfig();
            $serviceConfigs[$database->value] = new ServiceConfig(
                name: $defaultConfig->name,
                enabled: true,
                type: $defaultConfig->type,
                version: $defaultConfig->version,
                port: $defaultConfig->port,
                additionalPorts: $defaultConfig->additionalPorts,
                environmentVariables: $defaultConfig->environmentVariables,
            );
            $persistVolumes[] = $database;
        }

        foreach ($services as $serviceName) {
            $serviceImpl = $this->registry->get($serviceName);
            $defaultConfig = $serviceImpl->getDefaultConfig();
            $serviceConfigs[$serviceName->value] = new ServiceConfig(
                name: $defaultConfig->name,
                enabled: true,
                type: $defaultConfig->type,
                version: $defaultConfig->version,
                port: $defaultConfig->port,
                additionalPorts: $defaultConfig->additionalPorts,
                environmentVariables: $defaultConfig->environmentVariables,
            );

            if (in_array($serviceName, [Service::Redis, Service::Memcached, Service::MinIO, Service::Elasticsearch, Service::RabbitMq, Service::MongoDB], true)) {
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
        $this->showSummary($config, $database, $services, $xdebug->enabled, $projectType);

        if (!confirm(label: 'Continue with this configuration?')) {
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

    private function selectDatabase(): Service
    {
        $choice = select(
            label: 'Select database (default: postgresql)',
            options: service::databases(),
            default: Service::PostgreSQL->value,
        );

        return Service::from($choice);
    }

    private function selectPhpVersion(): PhpVersion
    {
        $detectedVersion = $this->detectPhpVersionFromComposer();
        $defaultVersion = $detectedVersion ?? PhpVersion::Php84;

        $choice = select(
            label: sprintf('Select PHP version (default: %s)', $defaultVersion->value),
            options: array_map(fn(PhpVersion $version): string => $version->value, PhpVersion::supported()),
            default: $defaultVersion->value,
        );

        return PhpVersion::from($choice);
    }

    private function detectPhpVersionFromComposer(): ?PhpVersion
    {
        $composerPath = (string) getcwd() . '/composer.json';
        if (!file_exists($composerPath)) {
            return null;
        }

        $composerContent = file_get_contents($composerPath);
        if ($composerContent === false) {
            return null;
        }

        /** @var mixed $composer */
        $composer = json_decode($composerContent, true);
        if (!is_array($composer)) {
            return null;
        }

        /** @var array<string, mixed> $composer */
        $require = $composer['require'] ?? null;
        if (!is_array($require)) {
            return null;
        }

        $phpRequirement = $require['php'] ?? null;
        if (!is_string($phpRequirement)) {
            return null;
        }

        // Parse PHP version from requirement like "^8.4", ">=8.3", "~8.4.0", etc.
        if (preg_match('/(\d+\.\d+)/', $phpRequirement, $matches)) {
            $versionString = $matches[1];
            $phpVersion = PhpVersion::tryFrom($versionString);

            // If detected version is supported, return it
            if ($phpVersion !== null && PhpVersion::isSupported($phpVersion)) {
                return $phpVersion;
            }
        }

        return null;
    }

    private function enableXdebug(): XdebugConfig
    {
        $xdebugEnabled = confirm(label: 'Do you want to enable Xdebug?', default: false);
        return new XdebugConfig($xdebugEnabled, 'seaman', 'host.docker.internal');

    }

    /**
     * @param ProjectType $projectType
     * @return list<Service>
     */
    private function selectServices(ProjectType $projectType): array
    {
        $defaults = $this->getDefaultServices($projectType);
        /** @var array<int, string> $selected */
        $selected = multiselect(
            label: 'Select additional services',
            options: Service::services(),
            default: array_map(fn(Service $service) => $service->value, $defaults),
        );

        // Convert to list<Service>
        return array_values(
            array_map(fn(string $service): Service => Service::from($service), $selected),
        );
    }

    /**
     * @param ProjectType $projectType
     * @return list<Service>
     */
    private function getDefaultServices(ProjectType $projectType): array
    {
        return match ($projectType) {
            ProjectType::WebApplication => [Service::Redis, Service::Mailpit],
            ProjectType::ApiPlatform, ProjectType::Microservice => [Service::Redis],
            ProjectType::Skeleton, ProjectType::Existing => [],
        };
    }

    /**
     * @param list<Service> $services
     */
    private function showSummary(
        Configuration $config,
        Service $database,
        array $services,
        bool $xdebugEnabled,
        ?ProjectType $projectType,
    ): void {
        if ($projectType !== null) {
            info('Project Type: ' . $projectType->getLabel());
        }

        info('Database: ' . ($database === Service::None ? 'None' : ucfirst($database->value)));
        info('Services: ' . (empty($services) ? 'None' : implode(', ', array_map(function (Service $service) {
            return ucfirst($service->value);
        }, $services))));
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

        // Copy Dockerfile template to .seaman/
        $templateDockerfile = __DIR__ . '/../../docker/Dockerfile.template';
        if (!file_exists($templateDockerfile)) {
            info('Seaman Dockerfile template not found.');
            throw new \RuntimeException('Template Dockerfile missing');
        }
        copy($templateDockerfile, $seamanDir . '/Dockerfile');

        // Build Docker image
        info('Building Docker image...');
        $builder = new DockerImageBuilder($projectRoot, $config->php->version);
        $result = $builder->build();

        if (!$result->isSuccessful()) {
            info('Failed to build Docker image');
            info($result->errorOutput);
            throw new \RuntimeException('Docker build failed');
        }

        info('✓ Docker image built successfully!');
    }

    private function bootstrapSymfonyProject(string $projectRoot): ProjectType
    {
        $detection = $this->detector->detect($projectRoot);
        if (!$detection->isSymfonyProject) {
            $shouldBootstrap = confirm(
                label: 'No Symfony application detected. Create new project?',
            );

            if (!$shouldBootstrap) {
                info('Please create a Symfony project first, then run init again.');
                exit(Command::FAILURE);
            }

            // Bootstrap new Symfony project
            $projectType = $this->selectProjectType();
            $projectName = $this->getProjectName($projectRoot);

            info('Creating Symfony project...');

            if (!$this->bootstrapper->bootstrap($projectType, $projectName, dirname($projectRoot))) {
                info('Failed to create Symfony project.');
                exit(Command::FAILURE);
            }

            // Change to new project directory
            $projectRoot = dirname($projectRoot) . '/' . $projectName;
            chdir($projectRoot);

            info('Symfony project created successfully!');
            return $projectType;
        }

        return ProjectType::Existing;
    }
}
