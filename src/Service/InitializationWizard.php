<?php

declare(strict_types=1);

// ABOUTME: Handles interactive initialization prompts and user input.
// ABOUTME: Collects user choices for project configuration.

namespace Seaman\Service;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;
use Seaman\ValueObject\InitializationChoices;
use Seaman\ValueObject\XdebugConfig;
use Symfony\Component\Console\Input\InputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

final readonly class InitializationWizard
{
    public function __construct(
        private PhpVersionDetector $detector,
    ) {}

    /**
     * Run the full initialization wizard and collect all choices.
     */
    public function run(InputInterface $input, ProjectType $projectType, string $projectRoot): InitializationChoices
    {
        $projectName = basename($projectRoot);
        $phpVersion = $this->selectPhpVersion($projectRoot);
        $database = $this->selectDatabase();
        $services = $this->selectServices($projectType);
        $xdebug = $this->enableXdebug();
        $useProxy = $this->shouldUseProxy();
        $devContainer = $this->enableDevContainer($input);

        return new InitializationChoices(
            projectName: $projectName,
            phpVersion: $phpVersion,
            database: $database,
            services: $services,
            xdebug: $xdebug,
            generateDevContainer: $devContainer,
            useProxy: $useProxy,
        );
    }

    public function selectProjectType(): ProjectType
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

    public function selectPhpVersion(string $projectRoot): PhpVersion
    {
        $detectedVersion = $this->detectPhpVersion($projectRoot);
        $defaultVersion = $detectedVersion ?? PhpVersion::Php84;

        $choice = select(
            label: sprintf('Select PHP version (default: %s)', $defaultVersion->value),
            options: array_map(fn(PhpVersion $version): string => $version->value, PhpVersion::supported()),
            default: $defaultVersion->value,
        );

        return PhpVersion::from($choice);
    }

    public function selectDatabase(): Service
    {
        $choice = select(
            label: 'Select database (default: postgresql)',
            options: Service::databases(),
            default: Service::PostgreSQL->value,
        );

        return Service::from($choice);
    }

    /**
     * @return list<Service>
     */
    public function selectServices(ProjectType $projectType): array
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

    public function enableXdebug(): XdebugConfig
    {
        $xdebugEnabled = confirm(label: 'Do you want to enable Xdebug?', default: false);
        return new XdebugConfig($xdebugEnabled, 'seaman', 'host.docker.internal');
    }

    public function enableDevContainer(InputInterface $input): bool
    {
        return $input->getOption('with-devcontainer')
            || confirm(label: 'Do you want to enable DevContainer support?', default: false);
    }

    /**
     * Ask if user wants to use Traefik proxy.
     */
    public function shouldUseProxy(): bool
    {
        return confirm(
            label: 'Use Traefik as reverse proxy?',
            default: true,
            hint: 'Enables HTTPS and local domains (app.project.local). Disable for direct port access.',
        );
    }

    /**
     * Get default services for a project type.
     *
     * @return list<Service>
     */
    public function getDefaultServices(ProjectType $projectType): array
    {
        return match ($projectType) {
            ProjectType::WebApplication => [Service::Redis, Service::Mailpit],
            ProjectType::ApiPlatform, ProjectType::Microservice => [Service::Redis],
            ProjectType::Skeleton, ProjectType::Existing => [],
        };
    }

    /**
     * Detect PHP version from project root.
     */
    public function detectPhpVersion(string $projectRoot): ?PhpVersion
    {
        return $this->detector->detect($projectRoot);
    }

    /**
     * Get project name from directory path.
     */
    public function getProjectName(string $currentDir): string
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
}
