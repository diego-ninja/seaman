<?php

// ABOUTME: Enum for Symfony project types.
// ABOUTME: Defines available project templates for bootstrapping.

declare(strict_types=1);

namespace Seaman\ValueObject;

enum ProjectType: string
{
    case WebApplication = 'web';
    case ApiPlatform = 'api';
    case Microservice = 'microservice';
    case Skeleton = 'skeleton';

    public function getLabel(): string
    {
        return match($this) {
            self::WebApplication => 'Full Web Application',
            self::ApiPlatform => 'API Platform',
            self::Microservice => 'Microservice',
            self::Skeleton => 'Skeleton',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::WebApplication => 'Complete web app with Twig, Doctrine, Security, Forms',
            self::ApiPlatform => 'API-first application with API Platform bundle',
            self::Microservice => 'Minimal Symfony with framework-bundle only',
            self::Skeleton => 'Bare minimum framework-bundle',
        };
    }
}
