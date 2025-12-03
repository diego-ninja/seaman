# Seaman Enhancement: Dual Mode Architecture, Traefik Integration & Docker-Compose Import

**Date**: 2025-12-03
**Status**: Design Approved
**Author**: Claude & Diego

## Executive Summary

Transform seaman from a "generate and manage" tool to a flexible "work with any docker-compose" tool. Key enhancements:

1. **Dual Operating Mode**: Work with existing docker-compose.yaml files without initialization (unmanaged mode) or get full features with seaman.yml (managed mode)
2. **Docker-Compose Import**: Best-effort import of existing docker-compose.yaml files with fuzzy service detection
3. **Traefik Integration**: Mandatory reverse proxy with HTTPS support, automatic service routing
4. **DNS Management**: Smart DNS configuration with dnsmasq integration or manual instructions
5. **Bug Fixes**: Port conflict detection, better validation, improved error messages

## Current State Analysis

### What is Seaman?

Seaman is "sail for Symfony" - a Docker development environment manager for Symfony 7+ projects. It provides:
- Intelligent project detection and initialization
- Service orchestration with Docker Compose
- Developer-friendly CLI with interactive wizards
- Type-safe PHP 8.4 implementation with PHPStan level 10
- 95%+ test coverage

### Current Architecture

```
CLI Layer (Commands)
    ↓
Service Management Layer
    ↓
Docker Integration Layer
    ↓
Configuration/Value Objects
    ↓
Enums & Data Structures
```

**Core Components**:
- **Application.php**: Main Symfony Console Application
- **ConfigManager**: YAML parsing for `.seaman/seaman.yaml`
- **DockerManager**: Executes docker-compose commands
- **ProjectInitializer**: Orchestrates Docker environment setup
- **ServiceRegistry + ServiceDiscovery**: Auto-discovers service implementations
- **TemplateRenderer**: Twig-based file generation

### Current Limitations

1. **All or Nothing**: Must run `seaman init` before any commands work
2. **Can't Import**: No support for existing docker-compose.yaml files
3. **No Reverse Proxy**: Services accessed by port numbers (localhost:8000, localhost:8025)
4. **Manual Port Management**: Users must handle port conflicts manually
5. **Generic Errors**: "Configuration not found" instead of helpful guidance

### Issues to Fix

1. **Port Conflicts**: No detection before starting services
2. **Validation**: Commands assume configuration exists, fail with generic errors
3. **InitCommand Complexity**: 400+ lines mixing concerns (detection, wizard, generation)
4. **Service Dependencies**: Services start in random order

## 1. Dual Mode Architecture

### Operating Modes

```php
enum OperatingMode
{
    case Managed;       // .seaman/seaman.yaml exists
    case Unmanaged;     // Only docker-compose.yaml exists
    case Uninitialized; // Neither exists
}
```

**Mode Detection** (`ModeDetector` service):
```php
public function detect(): OperatingMode
{
    if (file_exists('.seaman/seaman.yaml')) {
        return OperatingMode::Managed;
    }

    if (file_exists('docker-compose.yml') || file_exists('docker-compose.yaml')) {
        return OperatingMode::Unmanaged;
    }

    return OperatingMode::Uninitialized;
}
```

### Philosophy Change

**Current**: Seaman owns docker-compose.yml completely. Initialize or nothing.

**New**: Seaman is useful even without initialization, reducing adoption friction.

### Command Availability Matrix

| Command | Unmanaged Mode | Managed Mode | Notes |
|---------|---------------|--------------|-------|
| `start` | ✅ Passthrough | ✅ Full control | Direct docker-compose in unmanaged |
| `stop` | ✅ Passthrough | ✅ Full control | |
| `restart` | ✅ Passthrough | ✅ Full control | |
| `status` | ✅ Passthrough | ✅ Enhanced | Managed shows seaman-specific info |
| `logs` | ✅ Passthrough | ✅ Full control | |
| `shell` | ✅ If app service exists | ✅ Full control | Auto-detect app service in unmanaged |
| `destroy` | ✅ Passthrough | ✅ Full control | |
| `rebuild` | ✅ Passthrough | ✅ Full control | |
| `execute:*` | ✅ If service exists | ✅ Full control | |
| `db:*` | ✅ If DB detected | ✅ Full control | Fuzzy detect postgres/mysql |
| `xdebug:*` | ❌ Requires init | ✅ Works | Needs seaman config |
| `service:add` | ❌ Requires init | ✅ Works | |
| `service:remove` | ❌ Requires init | ✅ Works | |
| `init` | ✅ Creates managed mode | ✅ Reinit | Smart import if compose exists |
| `devcontainer:*` | ❌ Requires init | ✅ Works | |

### Mode-Aware Command Base Class

```php
abstract class ModeAwareCommand extends Command
{
    protected OperatingMode $mode;

    protected function initialize(Input $input, Output $output): void
    {
        $this->mode = $this->modeDetector->detect();

        if (!$this->supportsMode($this->mode)) {
            $this->showUpgradeMessage($output);
            exit(1);
        }
    }

    abstract protected function supportsMode(OperatingMode $mode): bool;

    protected function showUpgradeMessage(Output $output): void
    {
        $output->writeln("<comment>This command requires seaman initialization.</comment>");
        $output->writeln("Run <info>seaman init</info> to unlock:");
        $output->writeln("  • Service management (add/remove services)");
        $output->writeln("  • Xdebug control");
        $output->writeln("  • DevContainer generation");
        $output->writeln("  • Database tools");
    }
}
```

**Benefits**:
- Basic commands work immediately with any docker-compose file
- Clear value proposition for upgrading to managed mode
- No breaking changes for existing users

## 2. Docker-Compose Import Mechanism

### Import Flow

When user runs `seaman init` and docker-compose.yaml exists:

```
1. Detect existing docker-compose.yaml
2. Interactive Prompt:
   "Existing docker-compose.yaml found. [I]mport it or [C]reate new configuration?"
3. If Import selected:
   a. Parse docker-compose.yaml
   b. Use fuzzy matching to identify known services
   c. Show detected services for user confirmation
   d. Create seaman.yml with recognized + custom services
   e. Backup original as docker-compose.yaml.backup
4. If Create selected:
   a. Backup existing compose as docker-compose.yaml.backup
   b. Run normal initialization wizard
5. Generate new docker-compose.yml from seaman.yml
```

### Service Detection (Fuzzy Matching)

**ComposeImporter Service** with multiple detection strategies:

```php
class ServiceDetector
{
    public function detectService(string $serviceName, array $composeService): ?DetectedService
    {
        // Strategy 1: Match by image name (highest confidence)
        if (str_contains($composeService['image'] ?? '', 'postgres')) {
            return new DetectedService(
                type: Service::PostgreSQL,
                version: $this->extractVersion($composeService['image']),
                confidence: 'high'
            );
        }

        // Strategy 2: Match by service name patterns
        $name = strtolower($serviceName);
        if (in_array($name, ['postgres', 'postgresql', 'db', 'database'])) {
            return $this->detectByEnvironment($composeService, Service::PostgreSQL);
        }

        // Strategy 3: Match by exposed ports (medium confidence)
        if ($this->exposesPort($composeService, 5432)) {
            return new DetectedService(
                type: Service::PostgreSQL,
                confidence: 'medium'
            );
        }

        return null; // Unknown service
    }

    private function extractVersion(string $image): string
    {
        // Extract version from "postgres:16" → "16"
        if (preg_match('/:(.+)$/', $image, $matches)) {
            return $matches[1];
        }
        return 'latest';
    }
}
```

**Detection Rules by Service**:

| Service | Image Pattern | Name Patterns | Port |
|---------|--------------|---------------|------|
| PostgreSQL | `postgres:*` | postgres, postgresql, db, database | 5432 |
| MySQL | `mysql:*` | mysql, db, database | 3306 |
| MariaDB | `mariadb:*` | mariadb, maria, db | 3306 |
| Redis | `redis:*` | redis, cache | 6379 |
| Memcached | `memcached:*` | memcached, cache | 11211 |
| RabbitMQ | `rabbitmq:*` | rabbitmq, rabbit, queue | 5672, 15672 |
| Mailpit | `mailpit:*` | mailpit, mail, mailhog | 8025, 1025 |
| MongoDB | `mongo:*` | mongo, mongodb | 27017 |
| Elasticsearch | `elasticsearch:*` | elasticsearch, elastic, search | 9200 |
| Kafka | `kafka:*` | kafka | 9092 |

### User Confirmation Interface

Interactive table showing detected services:

```
┌─────────────────┬──────────────┬────────────┬────────────┐
│ Service Name    │ Detected As  │ Version    │ Confidence │
├─────────────────┼──────────────┼────────────┼────────────┤
│ postgres        │ PostgreSQL   │ 16         │ High       │
│ cache           │ Redis        │ 7-alpine   │ High       │
│ mail            │ Mailpit      │ latest     │ Medium     │
│ my-custom-app   │ Unknown      │ -          │ -          │
│ weird-service   │ Unknown      │ -          │ -          │
└─────────────────┴──────────────┴────────────┴────────────┘

Import recognized services (PostgreSQL, Redis, Mailpit)? [Y/n]
Unknown services will be preserved as custom_services in seaman.yml.
```

### Custom Services Storage

**Best-Effort Strategy**: Recognized services → seaman services, unknown → custom_services

**seaman.yml with custom services**:
```yaml
version: "1.0"
project_type: "existing"

php:
  version: "8.4"

proxy:
  enabled: true
  domain_prefix: "myproject"
  cert_resolver: "selfsigned"
  dashboard: true

services:
  traefik:
    enabled: true
    type: "traefik"
    version: "v3.1"
    port: 443

  postgresql:
    enabled: true
    type: "postgresql"
    version: "16"
    port: 5432

  redis:
    enabled: true
    type: "redis"
    version: "7-alpine"
    port: 6379

# Raw YAML pass-through for unknown services
custom_services:
  my-custom-app:
    image: "mycompany/app:latest"
    ports:
      - "8080:80"
    environment:
      API_KEY: "secret"
    volumes:
      - "./data:/data"
    networks:
      - seaman

  weird-service:
    image: "weirdcompany/service:v2"
    volumes:
      - "./data:/data"
    depends_on:
      - postgres
```

### Generation with Custom Services

**DockerComposeGenerator** merges both:

```php
public function generate(Configuration $config): string
{
    // Generate seaman-managed services via Twig
    $managedServices = $this->templateRenderer->render('docker/compose.base.twig', [
        'services' => $config->services()->enabled(),
        'php_version' => $config->php()->version(),
        'proxy' => $config->proxy(),
        // ...
    ]);

    // Append custom services from config
    if ($config->hasCustomServices()) {
        $managedServices = $this->mergeCustomServices(
            $managedServices,
            $config->customServices()
        );
    }

    return $managedServices;
}

private function mergeCustomServices(string $baseYaml, CustomServiceCollection $custom): string
{
    $yaml = Yaml::parse($baseYaml);

    foreach ($custom->all() as $name => $serviceConfig) {
        $yaml['services'][$name] = $serviceConfig;
    }

    return Yaml::dump($yaml, 4, 2);
}
```

**Single Source of Truth**: seaman.yml contains everything (managed + custom). Can regenerate complete docker-compose.yml from it.

## 3. Traefik Integration

### Traefik as Required Service

Traefik becomes mandatory, always-enabled service:

```php
enum Service: string
{
    case Traefik = 'traefik';
    case PostgreSQL = 'postgresql';
    // ... existing services

    public function isRequired(): bool
    {
        return $this === self::Traefik;
    }
}
```

### TraefikService Implementation

```php
class TraefikService implements ServiceInterface
{
    public function getType(): Service
    {
        return Service::Traefik;
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: 'traefik',
            enabled: true,  // Always enabled
            type: Service::Traefik,
            version: 'v3.1',
            port: 443,
            additionalPorts: [80, 8080],  // HTTP + Dashboard
            environmentVariables: []
        );
    }

    public function generateComposeConfig(ServiceConfig $config): array
    {
        return [
            'image' => "traefik:{$config->version()}",
            'ports' => [
                '80:80',      // HTTP
                '443:443',    // HTTPS
                '8080:8080'   // Dashboard
            ],
            'volumes' => [
                '/var/run/docker.sock:/var/run/docker.sock:ro',
                './.seaman/traefik:/etc/traefik',
                './.seaman/certs:/certs:ro'
            ],
            'command' => [
                '--api.dashboard=true',
                '--api.insecure=true',
                '--providers.docker=true',
                '--providers.docker.exposedbydefault=false',
                '--providers.file.directory=/etc/traefik/dynamic',
                '--entrypoints.web.address=:80',
                '--entrypoints.websecure.address=:443',
                '--log.level=INFO',
                '--accesslog=true'
            ],
            'labels' => [
                'traefik.enable=true',
                'traefik.http.routers.traefik.rule=Host(`traefik.${PROJECT_NAME}.local`)',
                'traefik.http.routers.traefik.service=api@internal',
                'traefik.http.routers.traefik.entrypoints=websecure',
                'traefik.http.routers.traefik.tls=true'
            ]
        ];
    }
}
```

### Certificate Management

**CertificateManager Service** with smart defaults:

```php
class CertificateManager
{
    public function generateCertificates(string $projectName): CertificateResult
    {
        // Check for mkcert (trusted certs)
        if ($this->hasMkcert()) {
            return $this->generateWithMkcert($projectName);
        }

        // Fallback to self-signed
        return $this->generateSelfSigned($projectName);
    }

    private function hasMkcert(): bool
    {
        $process = new Process(['which', 'mkcert']);
        $process->run();
        return $process->isSuccessful();
    }

    private function generateWithMkcert(string $projectName): CertificateResult
    {
        $domains = [
            "*.{$projectName}.local",
            "{$projectName}.local"
        ];

        $process = new Process([
            'mkcert',
            '-cert-file', '.seaman/certs/cert.pem',
            '-key-file', '.seaman/certs/key.pem',
            ...$domains
        ]);

        $process->run();

        return new CertificateResult(
            type: 'mkcert',
            certPath: '.seaman/certs/cert.pem',
            keyPath: '.seaman/certs/key.pem',
            trusted: true
        );
    }

    private function generateSelfSigned(string $projectName): CertificateResult
    {
        $process = new Process([
            'openssl', 'req', '-x509', '-nodes', '-days', '365',
            '-newkey', 'rsa:2048',
            '-keyout', '.seaman/certs/key.pem',
            '-out', '.seaman/certs/cert.pem',
            '-subj', "/CN=*.{$projectName}.local"
        ]);

        $process->run();

        return new CertificateResult(
            type: 'self-signed',
            certPath: '.seaman/certs/cert.pem',
            keyPath: '.seaman/certs/key.pem',
            trusted: false
        );
    }
}
```

**Certificate Resolver Priority**:
1. **mkcert** (if installed): Locally-trusted certificates, no browser warnings
2. **self-signed** (fallback): Browser warnings, but works everywhere

### Traefik Configuration Files

Seaman generates static and dynamic configs during init:

**`.seaman/traefik/traefik.yml`** (static):
```yaml
api:
  dashboard: true
  insecure: true

providers:
  docker:
    exposedByDefault: false
  file:
    directory: /etc/traefik/dynamic

entryPoints:
  web:
    address: :80
  websecure:
    address: :443

log:
  level: INFO

accessLog: {}
```

**`.seaman/traefik/dynamic/certs.yml`** (dynamic):
```yaml
tls:
  certificates:
    - certFile: /certs/cert.pem
      keyFile: /certs/key.pem
  stores:
    default:
      defaultCertificate:
        certFile: /certs/cert.pem
        keyFile: /certs/key.pem
```

## 4. Service Routing & Domain Configuration

### ProxyConfig Value Object

New configuration section in seaman.yml:

```yaml
proxy:
  enabled: true
  domain_prefix: "myproject"  # Defaults to project directory name
  cert_resolver: "mkcert"  # or "selfsigned"
  dashboard: true
```

```php
readonly class ProxyConfig
{
    public function __construct(
        public bool $enabled,
        public string $domainPrefix,
        public string $certResolver,
        public bool $dashboard,
    ) {}

    public static function default(string $projectName): self
    {
        return new self(
            enabled: true,
            domainPrefix: $projectName,
            certResolver: 'selfsigned',
            dashboard: true
        );
    }

    public function getDomain(string $subdomain = 'app'): string
    {
        return "{$subdomain}.{$this->domainPrefix}.local";
    }
}
```

### Service Exposure Strategy

**Selective Exposure** - Services categorized by how they're accessed:

```php
enum ServiceExposureType
{
    case ProxyOnly;    // Web UIs - only through Traefik
    case DirectPort;   // Data services - need direct port access
}
```

**Service Classification**:

| Exposure Type | Services | Reason |
|--------------|----------|---------|
| **ProxyOnly** | App, Mailpit UI, RabbitMQ Management, Dozzle, MinIO Console, Traefik Dashboard | Web UIs benefit from clean HTTPS URLs |
| **DirectPort** | PostgreSQL, MySQL, MariaDB, MongoDB, Redis, Memcached, RabbitMQ AMQP, Kafka, Mailpit SMTP | Database tools need direct TCP connections |

### Automatic Traefik Labels

**TraefikLabelGenerator Service**:

```php
class TraefikLabelGenerator
{
    public function generateLabels(
        ServiceConfig $service,
        ProxyConfig $proxy
    ): array {
        $exposureType = $this->getExposureType($service->type());

        if ($exposureType === ServiceExposureType::DirectPort) {
            return ['traefik.enable=false'];
        }

        $serviceName = $service->name();
        $domain = $proxy->getDomain($serviceName);
        $routerName = str_replace('.', '-', $serviceName);

        return [
            'traefik.enable=true',
            "traefik.http.routers.{$routerName}.rule=Host(`{$domain}`)",
            "traefik.http.routers.{$routerName}.entrypoints=websecure",
            "traefik.http.routers.{$routerName}.tls=true",
            $this->getServiceLabel($service),
        ];
    }

    private function getServiceLabel(ServiceConfig $service): string
    {
        // Map service to internal port
        return match($service->type()) {
            Service::Mailpit => "traefik.http.services.mailpit.loadbalancer.server.port=8025",
            Service::RabbitMQ => "traefik.http.services.rabbitmq.loadbalancer.server.port=15672",
            Service::Dozzle => "traefik.http.services.dozzle.loadbalancer.server.port=8080",
            Service::MinIO => "traefik.http.services.minio.loadbalancer.server.port=9001",
            default => "traefik.http.services.{$service->name()}.loadbalancer.server.port=80"
        };
    }
}
```

### App Service Routing

Main PHP app accessible at: `https://app.myproject.local`

```yaml
services:
  app:
    build:
      context: .
      dockerfile: .seaman/Dockerfile
    volumes:
      - .:/var/www/html
    networks:
      - seaman
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.app.rule=Host(`app.myproject.local`)"
      - "traefik.http.routers.app.entrypoints=websecure"
      - "traefik.http.routers.app.tls=true"
      - "traefik.http.services.app.loadbalancer.server.port=80"

      # Middleware
      - "traefik.http.middlewares.app-compress.compress=true"
      - "traefik.http.middlewares.app-headers.headers.sslredirect=true"
      - "traefik.http.routers.app.middlewares=app-compress,app-headers"
```

### Example Access Patterns

After init with PostgreSQL, Redis, Mailpit:

| Service | Direct Port | Traefik Domain | Access Method |
|---------|------------|----------------|---------------|
| App | ❌ | `https://app.myproject.local` | Proxy only |
| PostgreSQL | `localhost:5432` | ❌ | Direct (TablePlus, DBeaver) |
| Redis | `localhost:6379` | ❌ | Direct (RedisInsight) |
| Mailpit UI | ❌ | `https://mailpit.myproject.local` | Proxy only |
| Mailpit SMTP | `localhost:1025` | ❌ | Direct (SMTP protocol) |
| Traefik Dashboard | ❌ | `https://traefik.myproject.local` | Proxy only |

## 5. DNS Management

### DnsConfigurationHelper Service

Smart DNS setup with automatic detection:

```php
class DnsConfigurationHelper
{
    public function configure(string $projectName): DnsConfigurationResult
    {
        // Check for dnsmasq
        if ($this->hasDnsmasq()) {
            return $this->offerDnsmasqSetup($projectName);
        }

        // Check for systemd-resolved (Linux)
        if ($this->hasSystemdResolved()) {
            return $this->offerSystemdResolvedSetup($projectName);
        }

        // Fallback to manual instructions
        return $this->showManualInstructions($projectName);
    }

    private function hasDnsmasq(): bool
    {
        $process = new Process(['which', 'dnsmasq']);
        $process->run();
        return $process->isSuccessful();
    }

    private function offerDnsmasqSetup(string $projectName): DnsConfigurationResult
    {
        $configPath = $this->getDnsmasqConfigPath();
        $configContent = "address=/.{$projectName}.local/127.0.0.1\n";

        return new DnsConfigurationResult(
            type: 'dnsmasq',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: $configContent
        );
    }

    private function getDnsmasqConfigPath(): string
    {
        return match(PHP_OS_FAMILY) {
            'Linux' => '/etc/dnsmasq.d/seaman-' . $this->projectName . '.conf',
            'Darwin' => '/usr/local/etc/dnsmasq.d/seaman-' . $this->projectName . '.conf',
            default => throw new RuntimeException('Unsupported platform')
        };
    }
}
```

### Post-Initialization DNS Flow

After successful `seaman init`:

```
┌─────────────────────────────────────────────────────────┐
│ ✓ Seaman initialized successfully!                     │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ DNS Configuration Required                             │
│                                                         │
│ For *.myproject.local domains to work, DNS must be     │
│ configured.                                            │
│                                                         │
│ [A] Automatic (dnsmasq detected)                       │
│ [M] Manual (show instructions)                         │
│ [S] Skip (configure later)                             │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

**Automatic (dnsmasq)**:
```
Configuring dnsmasq...

This requires sudo access to write to /etc/dnsmasq.d/

The following file will be created:
  /etc/dnsmasq.d/seaman-myproject.conf

With content:
  address=/.myproject.local/127.0.0.1

Continue? [Y/n] y

[sudo] password for diego:

✓ Created /etc/dnsmasq.d/seaman-myproject.conf
✓ Restarting dnsmasq...

DNS configured! All *.myproject.local domains now resolve to 127.0.0.1
```

**Manual**:
```
Add these entries to /etc/hosts:

  127.0.0.1 app.myproject.local
  127.0.0.1 mailpit.myproject.local
  127.0.0.1 traefik.myproject.local

On Linux/macOS:
  sudo nano /etc/hosts

On Windows:
  notepad C:\Windows\System32\drivers\etc\hosts
```

### New Command: proxy:configure-dns

Configure DNS after initialization:

```php
class ProxyConfigureDnsCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('proxy:configure-dns')
             ->setDescription('Configure DNS for Traefik domains');
    }

    protected function execute(Input $input, Output $output): int
    {
        $config = $this->configManager->load();
        $result = $this->dnsHelper->configure($config->proxy()->domainPrefix());

        // Show configuration UI
        return Command::SUCCESS;
    }
}
```

### Cleanup on Destroy

When running `seaman destroy`, offer DNS cleanup:

```
Destroying seaman environment...

✓ Stopped containers
✓ Removed volumes

DNS configuration still exists:
  /etc/dnsmasq.d/seaman-myproject.conf

Remove DNS configuration? [y/N]
```

## 6. Bug Fixes & Improvements

### 1. Port Conflict Detection

**Problem**: Services fail to start if ports already in use.

**Solution**: `PortChecker` service validates before starting:

```php
class PortChecker
{
    public function checkAvailability(array $ports): PortCheckResult
    {
        $conflicts = [];

        foreach ($ports as $port) {
            if ($this->isPortInUse($port)) {
                $conflicts[$port] = $this->findProcessUsingPort($port);
            }
        }

        return new PortCheckResult($conflicts);
    }

    private function isPortInUse(int $port): bool
    {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);

        if ($socket) {
            fclose($socket);
            return true;
        }

        return false;
    }

    private function findProcessUsingPort(int $port): string
    {
        // Use lsof on Unix, netstat on Windows
        $process = new Process(['lsof', '-ti', ":{$port}"]);
        $process->run();

        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }

        return 'unknown';
    }
}
```

**Integration in StartCommand**:
```php
protected function execute(Input $input, Output $output): int
{
    $config = $this->configManager->load();
    $requiredPorts = $this->getRequiredPorts($config);

    $portCheck = $this->portChecker->checkAvailability($requiredPorts);

    if ($portCheck->hasConflicts()) {
        $output->writeln("<error>Port conflicts detected:</error>");
        foreach ($portCheck->conflicts() as $port => $process) {
            $output->writeln("  Port {$port}: used by {$process}");
        }
        $output->writeln("\nFree these ports or change service ports in .seaman/seaman.yaml");
        return Command::FAILURE;
    }

    return $this->dockerManager->start();
}
```

### 2. Configuration Validation

**Problem**: Invalid seaman.yaml causes runtime errors.

**Solution**: `ConfigurationValidator` validates on load:

```php
class ConfigurationValidator
{
    public function validate(Configuration $config): ValidationResult
    {
        $errors = [];

        // Validate PHP version
        if (!PhpVersion::tryFrom($config->php()->version()->value)) {
            $errors[] = "Unsupported PHP version: {$config->php()->version()->value}";
        }

        // Validate unique ports
        $ports = [];
        foreach ($config->services()->enabled() as $service) {
            if (in_array($service->port(), $ports)) {
                $errors[] = "Port conflict: {$service->port()} used by multiple services";
            }
            $ports[] = $service->port();
        }

        // Validate custom services
        foreach ($config->customServices()->all() as $name => $serviceConfig) {
            if (!isset($serviceConfig['image'])) {
                $errors[] = "Custom service '{$name}' missing 'image' field";
            }
        }

        return new ValidationResult($errors);
    }
}
```

### 3. Better Error Messages

**Problem**: Generic exceptions don't guide users.

**Solution**: Typed exceptions with helpful messages:

```php
class SeamanNotInitializedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            "Seaman is not initialized in this project.\n\n" .
            "Run 'seaman init' to set up your development environment.\n" .
            "This will create .seaman/seaman.yaml and generate docker-compose.yml"
        );
    }
}

class InvalidComposeFileException extends RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            "docker-compose.yml is invalid: {$reason}\n\n" .
            "Run 'seaman init' to regenerate it."
        );
    }
}
```

### 4. InitCommand Refactoring

**Problem**: 400+ lines mixing concerns.

**Solution**: Extract `ProjectDetector` service:

```php
class ProjectDetector
{
    public function detect(): ProjectDetectionResult
    {
        $hasComposer = file_exists('composer.json');
        $hasDockerCompose = file_exists('docker-compose.yml') ||
                           file_exists('docker-compose.yaml');
        $hasSeamanConfig = file_exists('.seaman/seaman.yaml');

        $symfonyVersion = null;
        $phpVersion = null;

        if ($hasComposer) {
            $composer = json_decode(file_get_contents('composer.json'), true);

            // Detect Symfony
            if (isset($composer['require']['symfony/framework-bundle'])) {
                $symfonyVersion = $this->extractSymfonyVersion($composer);
            }

            // Detect PHP version
            if (isset($composer['require']['php'])) {
                $phpVersion = $this->extractPhpVersion($composer['require']['php']);
            }
        }

        return new ProjectDetectionResult(
            hasComposer: $hasComposer,
            hasDockerCompose: $hasDockerCompose,
            hasSeamanConfig: $hasSeamanConfig,
            symfonyVersion: $symfonyVersion,
            phpVersion: $phpVersion
        );
    }
}
```

Simplified InitCommand:
```php
protected function execute(Input $input, Output $output): int
{
    // 1. Detect project state
    $detection = $this->projectDetector->detect();

    // 2. Handle existing config
    if ($detection->hasSeamanConfig && !$input->getOption('force')) {
        if (!$this->confirmOverwrite($output)) {
            return Command::SUCCESS;
        }
    }

    // 3. Handle existing docker-compose
    $importMode = false;
    if ($detection->hasDockerCompose && !$detection->hasSeamanConfig) {
        $importMode = $this->askImportOrCreate($output);
    }

    // 4. Run wizard or import
    if ($importMode) {
        $config = $this->composeImporter->import($output);
    } else {
        $config = $this->wizard->run($output, $detection);
    }

    // 5. Initialize environment
    $this->projectInitializer->initialize($config);

    // 6. Configure DNS
    $this->dnsHelper->configure($config->proxy()->domainPrefix());

    return Command::SUCCESS;
}
```

## 7. Configuration Structure

### Extended seaman.yml Schema

```yaml
version: "1.0"
project_type: "web"  # web|api|microservice|skeleton|existing

php:
  version: "8.4"
  xdebug:
    enabled: true
    ide_key: "PHPSTORM"
    client_host: "host.docker.internal"

# NEW: Proxy configuration
proxy:
  enabled: true
  domain_prefix: "myproject"
  cert_resolver: "mkcert"
  dashboard: true

services:
  # NEW: Traefik always present
  traefik:
    enabled: true
    type: "traefik"
    version: "v3.1"
    port: 443
    additional_ports: [80, 8080]

  postgresql:
    enabled: true
    type: "postgresql"
    version: "16"
    port: 5432
    environment:
      POSTGRES_DB: "app"
      POSTGRES_USER: "app"
      POSTGRES_PASSWORD: "secret"

  redis:
    enabled: true
    type: "redis"
    version: "7-alpine"
    port: 6379

# NEW: Custom services from import
custom_services:
  my-custom-app:
    image: "mycompany/app:latest"
    ports:
      - "8080:80"
    environment:
      API_KEY: "secret"

volumes:
  persist:
    - "postgresql"
    - "redis"
```

### New Value Objects

**ProxyConfig**:
```php
readonly class ProxyConfig
{
    public function __construct(
        public bool $enabled,
        public string $domainPrefix,
        public string $certResolver,
        public bool $dashboard,
    ) {}

    public function getDomain(string $subdomain = 'app'): string
    {
        return "{$subdomain}.{$this->domainPrefix}.local";
    }
}
```

**CustomServiceCollection**:
```php
readonly class CustomServiceCollection
{
    /** @param array<string, array> $services */
    public function __construct(
        private array $services = []
    ) {}

    public function all(): array
    {
        return $this->services;
    }

    public function count(): int
    {
        return count($this->services);
    }

    public function add(string $name, array $config): self
    {
        return new self([...$this->services, $name => $config]);
    }
}
```

**Updated Configuration**:
```php
readonly class Configuration
{
    public function __construct(
        private string $version,
        private ProjectType $projectType,
        private PhpConfig $php,
        private ServiceCollection $services,
        private VolumeConfig $volumes,
        private ProxyConfig $proxy,  // NEW
        private CustomServiceCollection $customServices = new CustomServiceCollection(),  // NEW
    ) {}

    public function proxy(): ProxyConfig
    {
        return $this->proxy;
    }

    public function customServices(): CustomServiceCollection
    {
        return $this->customServices;
    }

    public function hasCustomServices(): bool
    {
        return $this->customServices->count() > 0;
    }
}
```

## 8. Implementation Phases

### Phase 1: Foundation (Core Architecture)

**Goal**: Establish dual-mode architecture and refactor existing code.

**Tasks**:
1. Create `OperatingMode` enum
2. Create `ModeDetector` service (TDD)
3. Create `ModeAwareCommand` base class
4. Create `SeamanCommand` base with validation
5. Implement typed exceptions
6. Create `ConfigurationValidator` service (TDD)
7. Create `PortChecker` service (TDD)
8. Extract `ProjectDetector` from InitCommand (TDD)
9. Refactor all commands to extend `ModeAwareCommand`

**Tests**:
- ✅ Unit tests for ModeDetector
- ✅ Unit tests for ConfigurationValidator
- ✅ Unit tests for PortChecker
- ✅ Unit tests for ProjectDetector
- ❌ Command integration tests (skipped)

**Deliverable**: Foundation ready for new features with better errors and validation.

### Phase 2: Traefik Integration

**Goal**: Add Traefik as required service with HTTPS support.

**Tasks**:
1. Add `Traefik` to `Service` enum with `isRequired()`
2. Create `TraefikService` implementation (TDD)
3. Create `ProxyConfig` value object
4. Update `Configuration` to include `ProxyConfig`
5. Create `CertificateManager` service (TDD)
6. Create Traefik config templates (traefik.yml, certs.yml)
7. Create `TraefikLabelGenerator` service (TDD)
8. Define `ServiceExposureType` enum
9. Update `DockerComposeGenerator` to include Traefik
10. Update service templates with Traefik labels
11. Update `InitCommand` to initialize Traefik and generate certs

**Tests**:
- ✅ Unit tests for TraefikService
- ✅ Unit tests for CertificateManager (mock process execution)
- ✅ Unit tests for TraefikLabelGenerator
- ❌ Command integration tests (skipped)

**Deliverable**: Fresh `seaman init` creates environment with Traefik, HTTPS, automatic routing.

### Phase 3: DNS Management

**Goal**: Help users configure DNS for *.local domains.

**Tasks**:
1. Create `DnsConfigurationHelper` service (TDD)
2. Create `DnsConfigurationResult` value object
3. Add post-init DNS configuration flow in `InitCommand`
4. Create `ProxyConfigureDnsCommand`
5. Update `DestroyCommand` to offer DNS cleanup
6. Platform-specific dnsmasq/systemd-resolved integration

**Tests**:
- ✅ Unit tests for DnsConfigurationHelper (mock platform detection)
- ❌ Command integration tests (skipped)

**Deliverable**: Users can configure DNS automatically or get clear manual instructions.

### Phase 4: Import Mechanism

**Goal**: Import existing docker-compose.yaml files.

**Tasks**:
1. Create `ComposeImporter` service (TDD)
2. Create `ServiceDetector` with fuzzy matching (TDD)
3. Create `DetectedService` value object
4. Create `CustomServiceCollection` value object
5. Update `Configuration` to include `customServices`
6. Update `ConfigManager` to serialize/deserialize custom services
7. Update `DockerComposeGenerator` to merge custom services
8. Add interactive confirmation UI in `InitCommand`
9. Update `InitCommand` to detect existing compose and offer import

**Tests**:
- ✅ Unit tests for ServiceDetector (various compose scenarios)
- ✅ Unit tests for ComposeImporter
- ✅ Unit tests for CustomServiceCollection
- ❌ Command integration tests (skipped)

**Deliverable**: Users can import docker-compose.yaml files with recognized and custom services.

### Phase 5: Unmanaged Mode Support

**Goal**: Make commands work without initialization.

**Tasks**:
1. Update all commands to check `OperatingMode`
2. Implement passthrough logic in `DockerManager` for unmanaged mode
3. Add "upgrade to managed" messages for restricted commands
4. Test all commands in unmanaged mode

**Tests**:
- ✅ Unit tests for mode-aware command logic
- ❌ Command integration tests (skipped)

**Deliverable**: Basic commands work with any docker-compose.yaml file.

### Phase 6: Testing & Documentation

**Goal**: Ensure 95%+ unit test coverage and document features.

**Tasks**:
1. Verify unit test coverage ≥ 95%
2. Write additional tests for edge cases
3. Update CLI help text for all commands
4. Update README with Traefik features
5. Create migration guide for existing users
6. Create TESTING.md with manual test scenarios
7. Add examples for common workflows

**Tests**:
- ✅ Verify coverage with `vendor/bin/pest --coverage --min=95`
- ❌ Integration tests (documented in TESTING.md instead)

**Deliverable**: Production-ready features with comprehensive documentation.

### Phase Dependencies

```
Phase 1 (Foundation)
    ↓
Phase 2 (Traefik) ←→ Phase 3 (DNS)  (can run in parallel)
    ↓
Phase 4 (Import)
    ↓
Phase 5 (Unmanaged Mode)
    ↓
Phase 6 (Testing & Docs)
```

## 9. Testing Strategy

### Unit Tests (95% Coverage - TDD)

**Test ALL business logic**:
- All new services (ModeDetector, PortChecker, ServiceDetector, etc.)
- All value objects (ProxyConfig, CustomServiceCollection, etc.)
- Service implementations (TraefikService, etc.)
- Generators (TraefikLabelGenerator)

**Example Test**:
```php
class PortCheckerTest extends TestCase
{
    public function test_detects_port_in_use(): void
    {
        $checker = new PortChecker();

        // Start local server on port 9999
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($socket, '127.0.0.1', 9999);
        socket_listen($socket);

        $result = $checker->checkAvailability([9999]);

        $this->assertTrue($result->hasConflicts());
        $this->assertArrayHasKey(9999, $result->conflicts());

        socket_close($socket);
    }
}
```

### Integration Tests (NOT Required)

**Commands** - Skip due to terminal complexity:
- ❌ InitCommand (interactive wizard)
- ❌ StartCommand (docker interaction)
- ❌ ProxyConfigureDnsCommand (system interaction)
- ❌ All other command integration tests

**Manual Testing Instead**:
- Document test scenarios in `TESTING.md`
- Test manually during development
- Create fixture projects for testing

### Coverage Monitoring

After each feature:
```bash
vendor/bin/pest --coverage --min=95
```

## 10. Migration Path for Existing Users

### Backward Compatibility

**Existing seaman.yml files work without changes**:
- Traefik service auto-added on next `seaman start`
- Proxy config created with defaults if missing
- Services get Traefik labels automatically

### Upgrade Flow

1. User runs `seaman start` (or any command)
2. Seaman detects missing proxy config
3. Shows message:
   ```
   Seaman has been updated with Traefik reverse proxy support.

   Run 'seaman init --upgrade' to add Traefik to your environment.

   This will:
   - Add Traefik service to seaman.yml
   - Generate HTTPS certificates
   - Configure service routing
   - Keep all existing services

   Your current setup will continue working without upgrade.
   ```
4. User runs `seaman init --upgrade`
5. Seaman adds Traefik without changing other services

### Breaking Changes

**None** - Fully backward compatible.

## 11. Future Enhancements (Out of Scope)

Not included in this design, but documented for future consideration:

1. **Multiple Environment Support**: production, staging configs
2. **Service Health Monitoring**: Dashboard showing service health
3. **Automatic Service Updates**: Check for new service versions
4. **Cloud Deployment**: Export to Kubernetes/Docker Swarm
5. **Service Templates**: Pre-configured stacks (Laravel, WordPress, etc.)
6. **Performance Monitoring**: APM integration
7. **Backup/Restore**: Automated database backups

## 12. Success Criteria

### Feature Completion

- ✅ Dual mode architecture (managed/unmanaged)
- ✅ Import existing docker-compose.yaml files
- ✅ Traefik integration with HTTPS
- ✅ DNS management (automatic or manual)
- ✅ Port conflict detection
- ✅ Configuration validation
- ✅ Better error messages

### Quality Metrics

- ✅ 95%+ unit test coverage
- ✅ PHPStan level 10 compliance
- ✅ PER code style
- ✅ All ABOUTME comments present
- ✅ Documentation complete

### User Experience

- ✅ Basic commands work without init
- ✅ Clear upgrade path to managed mode
- ✅ Helpful error messages guide users
- ✅ HTTPS "just works" with smart defaults
- ✅ DNS setup is straightforward

## Conclusion

This design transforms seaman from an opinionated generator to a flexible development tool that works with any docker-compose setup while providing premium features for full adoption. The phased approach ensures stability while delivering features incrementally.

Key principles maintained:
- **YAGNI**: Only building requested features
- **Backward Compatibility**: Existing users unaffected
- **Type Safety**: PHPStan level 10 throughout
- **Test Coverage**: 95%+ unit tests
- **User Experience**: Clear, helpful, non-breaking
