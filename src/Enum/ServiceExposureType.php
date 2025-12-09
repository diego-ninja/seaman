<?php

declare(strict_types=1);

// ABOUTME: Defines how services are exposed to users.
// ABOUTME: ProxyOnly services use Traefik HTTPS, DirectPort services need TCP ports.

namespace Seaman\Enum;

enum ServiceExposureType
{
    case ProxyOnly;    // Web UIs - only through Traefik (HTTPS URLs)
    case DirectPort;   // Data services - need direct port access (TCP connections)
}
