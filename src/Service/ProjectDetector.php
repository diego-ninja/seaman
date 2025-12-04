<?php

// ABOUTME: Detects Symfony project type for intelligent defaults.
// ABOUTME: Analyzes composer.json to determine Web App, API, Microservice, or Skeleton.

declare(strict_types=1);

namespace Seaman\Service;

use Seaman\Enum\ProjectType;

final readonly class ProjectDetector
{
    public function __construct(
        private SymfonyDetector $symfonyDetector,
    ) {}

    public function isSymfonyProject(string $directory): bool
    {
        $result = $this->symfonyDetector->detect($directory);
        return $result->isSymfonyProject;
    }

    public function detectProjectType(string $directory): ProjectType
    {
        // First check if it's a Symfony project
        if (!$this->isSymfonyProject($directory)) {
            return ProjectType::Skeleton;
        }

        $composerFile = $directory . '/composer.json';
        if (!file_exists($composerFile)) {
            return ProjectType::Skeleton;
        }

        $content = file_get_contents($composerFile);
        if ($content === false) {
            return ProjectType::Skeleton;
        }

        /** @var mixed $data */
        $data = json_decode($content, true);

        if (!is_array($data) || !isset($data['require']) || !is_array($data['require'])) {
            return ProjectType::Skeleton;
        }

        /** @var array<string, mixed> $require */
        $require = $data['require'];

        // Check for API Platform
        if (isset($require['api-platform/core'])) {
            return ProjectType::ApiPlatform;
        }

        // Check for Web Application indicators
        if ($this->hasWebAppIndicators($require)) {
            return ProjectType::WebApplication;
        }

        // Check for Microservice indicators (has more than just framework-bundle)
        if ($this->hasMicroserviceIndicators($require)) {
            return ProjectType::Microservice;
        }

        // Default to Skeleton
        return ProjectType::Skeleton;
    }

    /**
     * @param array<string, mixed> $require
     */
    private function hasWebAppIndicators(array $require): bool
    {
        $webIndicators = [
            'symfony/twig-bundle',
            'symfony/webpack-encore-bundle',
            'symfony/asset',
            'symfony/form',
            'symfony/security-bundle',
        ];

        foreach ($webIndicators as $indicator) {
            if (isset($require[$indicator])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $require
     */
    private function hasMicroserviceIndicators(array $require): bool
    {
        // Microservice typically has:
        // - framework-bundle (required)
        // - Additional Symfony packages (console, http-client, etc.)
        // - But NOT web UI stuff or API Platform

        $hasFramework = isset($require['symfony/framework-bundle']);
        $hasWebStuff = $this->hasWebAppIndicators($require);
        $hasApiPlatform = isset($require['api-platform/core']);

        // Count Symfony packages (excluding framework-bundle)
        $symfonyPackages = 0;
        foreach (array_keys($require) as $package) {
            if (str_starts_with($package, 'symfony/') && $package !== 'symfony/framework-bundle') {
                $symfonyPackages++;
            }
        }

        // Microservice: has framework + additional packages but not web/API
        // Skeleton: only has framework-bundle
        return $hasFramework && $symfonyPackages > 0 && !$hasWebStuff && !$hasApiPlatform;
    }
}
