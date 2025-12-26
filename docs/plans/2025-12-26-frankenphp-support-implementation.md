# FrankenPHP Support Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add FrankenPHP as an alternative to Symfony Server with three server options in the initialization wizard.

**Architecture:** New `ServerType` enum added to data model. Wizard prompts for server after PHP version. Dockerfile converted to Twig template with conditionals for each server type. Caddyfile generated only for worker mode.

**Tech Stack:** PHP 8.4, Pest, Twig, Docker

---

### Task 1: Create ServerType Enum

**Files:**
- Create: `src/Enum/ServerType.php`
- Create: `tests/Unit/Enum/ServerTypeTest.php`

**Step 1: Write the failing test**

```php
<?php

// ABOUTME: Tests for ServerType enum.
// ABOUTME: Validates server type cases and helper methods.

declare(strict_types=1);

namespace Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use Seaman\Enum\ServerType;

final class ServerTypeTest extends TestCase
{
    public function test_has_symfony_server_case(): void
    {
        $this->assertSame('symfony', ServerType::SymfonyServer->value);
    }

    public function test_has_frankenphp_classic_case(): void
    {
        $this->assertSame('frankenphp', ServerType::FrankenPhpClassic->value);
    }

    public function test_has_frankenphp_worker_case(): void
    {
        $this->assertSame('frankenphp-worker', ServerType::FrankenPhpWorker->value);
    }

    public function test_get_label_returns_human_readable_name(): void
    {
        $this->assertSame('Symfony Server', ServerType::SymfonyServer->getLabel());
        $this->assertSame('FrankenPHP', ServerType::FrankenPhpClassic->getLabel());
        $this->assertSame('FrankenPHP Worker', ServerType::FrankenPhpWorker->getLabel());
    }

    public function test_get_description_returns_description(): void
    {
        $this->assertSame('Built-in development server', ServerType::SymfonyServer->getDescription());
        $this->assertSame('Modern PHP server with Caddy', ServerType::FrankenPhpClassic->getDescription());
        $this->assertSame('Persistent process (advanced)', ServerType::FrankenPhpWorker->getDescription());
    }

    public function test_is_frankenphp_returns_correct_value(): void
    {
        $this->assertFalse(ServerType::SymfonyServer->isFrankenPhp());
        $this->assertTrue(ServerType::FrankenPhpClassic->isFrankenPhp());
        $this->assertTrue(ServerType::FrankenPhpWorker->isFrankenPhp());
    }

    public function test_is_worker_mode_returns_correct_value(): void
    {
        $this->assertFalse(ServerType::SymfonyServer->isWorkerMode());
        $this->assertFalse(ServerType::FrankenPhpClassic->isWorkerMode());
        $this->assertTrue(ServerType::FrankenPhpWorker->isWorkerMode());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Enum/ServerTypeTest.php`
Expected: FAIL with "Class 'Seaman\Enum\ServerType' not found"

**Step 3: Write minimal implementation**

```php
<?php

// ABOUTME: Server type enum for application serving.
// ABOUTME: Supports Symfony Server and FrankenPHP modes.

declare(strict_types=1);

namespace Seaman\Enum;

enum ServerType: string
{
    case SymfonyServer = 'symfony';
    case FrankenPhpClassic = 'frankenphp';
    case FrankenPhpWorker = 'frankenphp-worker';

    public function getLabel(): string
    {
        return match ($this) {
            self::SymfonyServer => 'Symfony Server',
            self::FrankenPhpClassic => 'FrankenPHP',
            self::FrankenPhpWorker => 'FrankenPHP Worker',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SymfonyServer => 'Built-in development server',
            self::FrankenPhpClassic => 'Modern PHP server with Caddy',
            self::FrankenPhpWorker => 'Persistent process (advanced)',
        };
    }

    public function isFrankenPhp(): bool
    {
        return $this !== self::SymfonyServer;
    }

    public function isWorkerMode(): bool
    {
        return $this === self::FrankenPhpWorker;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Enum/ServerTypeTest.php`
Expected: PASS

**Step 5: Run PHPStan and fix any issues**

Run: `./vendor/bin/phpstan analyse src/Enum/ServerType.php tests/Unit/Enum/ServerTypeTest.php`
Expected: No errors

**Step 6: Commit**

```bash
git add src/Enum/ServerType.php tests/Unit/Enum/ServerTypeTest.php
git commit -m "feat(enum): add ServerType enum for application server selection"
```

---

### Task 2: Update PhpConfig Value Object

**Files:**
- Modify: `src/ValueObject/PhpConfig.php`
- Modify: `tests/Unit/ValueObject/PhpConfigTest.php` (create if not exists)

**Step 1: Write the failing test**

Create or update `tests/Unit/ValueObject/PhpConfigTest.php`:

```php
<?php

// ABOUTME: Tests for PhpConfig value object.
// ABOUTME: Validates PHP configuration including server type.

declare(strict_types=1);

use Seaman\Enum\PhpVersion;
use Seaman\Enum\ServerType;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;

test('PhpConfig includes server type', function () {
    $config = new PhpConfig(
        version: PhpVersion::Php84,
        server: ServerType::FrankenPhpClassic,
        xdebug: new XdebugConfig(enabled: false),
    );

    expect($config->server)->toBe(ServerType::FrankenPhpClassic);
});

test('PhpConfig defaults server to SymfonyServer', function () {
    $config = new PhpConfig(
        version: PhpVersion::Php84,
        xdebug: new XdebugConfig(enabled: false),
    );

    expect($config->server)->toBe(ServerType::SymfonyServer);
});

test('PhpConfig validates PHP version', function () {
    new PhpConfig(
        version: PhpVersion::Php82,
        xdebug: new XdebugConfig(enabled: false),
    );
})->throws(InvalidArgumentException::class);
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/ValueObject/PhpConfigTest.php`
Expected: FAIL with argument count error or missing property

**Step 3: Update PhpConfig implementation**

Update `src/ValueObject/PhpConfig.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: PHP configuration value object.
// ABOUTME: Validates PHP version and manages server and Xdebug configuration.

namespace Seaman\ValueObject;

use InvalidArgumentException;
use Seaman\Enum\PhpVersion;
use Seaman\Enum\ServerType;

final readonly class PhpConfig
{
    public function __construct(
        public PhpVersion $version,
        public XdebugConfig $xdebug,
        public ServerType $server = ServerType::SymfonyServer,
    ) {
        if (!PhpVersion::isSupported($this->version)) {
            throw new InvalidArgumentException(
                "Unsupported PHP version: {$version->value}. Must be one of: " . implode(', ', array_map(static fn(PhpVersion $version): string => $version->value, PhpVersion::supported())),
            );
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/ValueObject/PhpConfigTest.php`
Expected: PASS

**Step 5: Run all tests to check for regressions**

Run: `./vendor/bin/pest`
Expected: Some tests may fail due to constructor change - fix them

**Step 6: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/ValueObject/PhpConfig.php`
Expected: No errors

**Step 7: Commit**

```bash
git add src/ValueObject/PhpConfig.php tests/Unit/ValueObject/PhpConfigTest.php
git commit -m "feat(config): add server property to PhpConfig"
```

---

### Task 3: Update PhpConfigParser

**Files:**
- Modify: `src/Service/ConfigParser/PhpConfigParser.php`
- Modify: `tests/Unit/Service/ConfigParser/PhpConfigParserTest.php`

**Step 1: Write the failing test**

Add to `tests/Unit/Service/ConfigParser/PhpConfigParserTest.php`:

```php
test('parses server configuration', function () {
    $data = [
        'php' => [
            'version' => '8.4',
            'server' => 'frankenphp',
            'xdebug' => [
                'enabled' => false,
            ],
        ],
    ];

    $result = $this->parser->parse($data);

    expect($result->server)->toBe(\Seaman\Enum\ServerType::FrankenPhpClassic);
});

test('defaults server to symfony when not specified', function () {
    $data = [
        'php' => [
            'version' => '8.4',
            'xdebug' => [
                'enabled' => false,
            ],
        ],
    ];

    $result = $this->parser->parse($data);

    expect($result->server)->toBe(\Seaman\Enum\ServerType::SymfonyServer);
});

test('parses frankenphp-worker server type', function () {
    $data = [
        'php' => [
            'version' => '8.4',
            'server' => 'frankenphp-worker',
            'xdebug' => [
                'enabled' => false,
            ],
        ],
    ];

    $result = $this->parser->parse($data);

    expect($result->server)->toBe(\Seaman\Enum\ServerType::FrankenPhpWorker);
});

test('merges server configuration', function () {
    $base = new \Seaman\ValueObject\PhpConfig(
        version: \Seaman\Enum\PhpVersion::Php84,
        xdebug: new \Seaman\ValueObject\XdebugConfig(enabled: false),
        server: \Seaman\Enum\ServerType::SymfonyServer,
    );

    $overrides = [
        'php' => [
            'server' => 'frankenphp',
        ],
    ];

    $result = $this->parser->merge($overrides, $base);

    expect($result->server)->toBe(\Seaman\Enum\ServerType::FrankenPhpClassic);
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Service/ConfigParser/PhpConfigParserTest.php`
Expected: FAIL

**Step 3: Update PhpConfigParser implementation**

Update `src/Service/ConfigParser/PhpConfigParser.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Parses PHP configuration section from YAML data.
// ABOUTME: Handles PHP version, server type, and Xdebug settings parsing.

namespace Seaman\Service\ConfigParser;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\ServerType;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;

final readonly class PhpConfigParser
{
    use ConfigDataExtractor;

    /**
     * @param array<string, mixed> $data
     */
    public function parse(array $data): PhpConfig
    {
        $phpData = $this->requireArray($data, 'php', 'Invalid PHP configuration: expected array');
        $xdebug = $this->parseXdebug($phpData);

        $versionString = $phpData['version'] ?? null;
        $phpVersion = is_string($versionString) ? PhpVersion::tryFrom($versionString) : null;

        $serverString = $phpData['server'] ?? null;
        $server = is_string($serverString) ? ServerType::tryFrom($serverString) : null;

        return new PhpConfig(
            version: $phpVersion ?? PhpVersion::Php84,
            xdebug: $xdebug,
            server: $server ?? ServerType::SymfonyServer,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function merge(array $data, PhpConfig $base): PhpConfig
    {
        $phpData = $this->getArray($data, 'php');

        /** @var array<string, mixed> $phpData */
        $xdebug = $this->mergeXdebug($phpData, $base->xdebug);

        $versionString = $phpData['version'] ?? null;
        $phpVersion = is_string($versionString) ? PhpVersion::tryFrom($versionString) : null;

        $serverString = $phpData['server'] ?? null;
        $server = is_string($serverString) ? ServerType::tryFrom($serverString) : null;

        return new PhpConfig(
            version: $phpVersion ?? $base->version,
            xdebug: $xdebug,
            server: $server ?? $base->server,
        );
    }

    /**
     * @param array<string, mixed> $phpData
     */
    private function parseXdebug(array $phpData): XdebugConfig
    {
        $xdebugData = $this->requireArray($phpData, 'xdebug', 'Invalid xdebug configuration: expected array');

        return new XdebugConfig(
            enabled: $this->getBool($xdebugData, 'enabled', false),
            ideKey: $this->getString($xdebugData, 'ide_key', 'PHPSTORM'),
            clientHost: $this->getString($xdebugData, 'client_host', 'host.docker.internal'),
        );
    }

    /**
     * @param array<string, mixed> $phpData
     */
    private function mergeXdebug(array $phpData, XdebugConfig $base): XdebugConfig
    {
        $xdebugData = $this->getArray($phpData, 'xdebug');

        /** @var array<string, mixed> $xdebugData */
        return new XdebugConfig(
            enabled: $this->getBool($xdebugData, 'enabled', $base->enabled),
            ideKey: $this->getString($xdebugData, 'ide_key', $base->ideKey),
            clientHost: $this->getString($xdebugData, 'client_host', $base->clientHost),
        );
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Service/ConfigParser/PhpConfigParserTest.php`
Expected: PASS

**Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Service/ConfigParser/PhpConfigParser.php`
Expected: No errors

**Step 6: Commit**

```bash
git add src/Service/ConfigParser/PhpConfigParser.php tests/Unit/Service/ConfigParser/PhpConfigParserTest.php
git commit -m "feat(parser): add server type parsing to PhpConfigParser"
```

---

### Task 4: Update InitializationChoices

**Files:**
- Modify: `src/ValueObject/InitializationChoices.php`

**Step 1: Read current file and update**

Update `src/ValueObject/InitializationChoices.php` to add server property:

```php
<?php

declare(strict_types=1);

// ABOUTME: Value object containing user choices during initialization.
// ABOUTME: Used to pass configuration selections between services.

namespace Seaman\ValueObject;

use Seaman\Enum\DnsProvider;
use Seaman\Enum\PhpVersion;
use Seaman\Enum\ServerType;
use Seaman\Enum\Service;

final readonly class InitializationChoices
{
    /**
     * @param list<Service> $services
     */
    public function __construct(
        public string $projectName,
        public PhpVersion $phpVersion,
        public ServerType $server,
        public ?Service $database,
        public array $services,
        public XdebugConfig $xdebug,
        public bool $generateDevContainer,
        public bool $useProxy = true,
        public bool $configureDns = false,
        public ?DnsProvider $dnsProvider = null,
    ) {}
}
```

**Step 2: Run all tests to find usages that need updating**

Run: `./vendor/bin/pest`
Expected: Tests will fail where InitializationChoices is constructed

**Step 3: Fix all test failures by adding server parameter**

Search for usages and update them to include server parameter.

**Step 4: Run tests again**

Run: `./vendor/bin/pest`
Expected: PASS

**Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse`
Expected: Fix any type errors related to InitializationChoices

**Step 6: Commit**

```bash
git add src/ValueObject/InitializationChoices.php
git commit -m "feat(choices): add server property to InitializationChoices"
```

---

### Task 5: Add selectServer to InitializationWizard

**Files:**
- Modify: `src/Service/InitializationWizard.php`
- Modify: `tests/Unit/Service/InitializationWizardTest.php`

**Step 1: Write the failing test**

Add to `tests/Unit/Service/InitializationWizardTest.php`:

```php
test('selectServer method exists and returns ServerType', function () {
    $detector = new \Seaman\Service\Detector\PhpVersionDetector();
    $wizard = new \Seaman\Service\InitializationWizard($detector);

    $reflection = new \ReflectionClass($wizard);
    $method = $reflection->getMethod('selectServer');
    $returnType = $method->getReturnType();

    expect($method->isPublic())->toBeTrue();
    expect($returnType)->toBeInstanceOf(\ReflectionNamedType::class);

    if ($returnType instanceof \ReflectionNamedType) {
        expect($returnType->getName())->toBe(\Seaman\Enum\ServerType::class);
    }
});
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Service/InitializationWizardTest.php`
Expected: FAIL with "Method selectServer does not exist"

**Step 3: Add selectServer method to InitializationWizard**

Add to `src/Service/InitializationWizard.php`:

```php
use Seaman\Enum\ServerType;

// Add this method to the class:
public function selectServer(): ServerType
{
    $options = [];
    foreach (ServerType::cases() as $server) {
        $options[$server->value] = sprintf(
            '%s - %s',
            $server->getLabel(),
            $server->getDescription(),
        );
    }

    $choice = Prompts::select(
        label: 'Select application server',
        options: $options,
        default: ServerType::SymfonyServer->value,
    );

    return ServerType::from($choice);
}
```

**Step 4: Update run() method to call selectServer**

Update the `run()` method to include server selection after PHP version:

```php
public function run(InputInterface $input, ProjectType $projectType, string $projectRoot): InitializationChoices
{
    $projectName = basename($projectRoot);
    $phpVersion = $this->selectPhpVersion($projectRoot);
    $server = $this->selectServer();  // NEW
    $database = $this->selectDatabase();
    $services = $this->selectServices($projectType);
    $xdebug = $this->enableXdebug($server);  // Pass server for warning
    // ... rest unchanged

    return new InitializationChoices(
        projectName: $projectName,
        phpVersion: $phpVersion,
        server: $server,  // NEW
        database: $database,
        // ... rest unchanged
    );
}
```

**Step 5: Update enableXdebug to show warning for worker mode**

```php
public function enableXdebug(ServerType $server = ServerType::SymfonyServer): XdebugConfig
{
    $hint = $server->isWorkerMode()
        ? 'Note: Xdebug in worker mode requires container restart after toggle'
        : null;

    $xdebugEnabled = Prompts::confirm(
        label: 'Do you want to enable Xdebug?',
        default: false,
        hint: $hint,
    );

    return new XdebugConfig($xdebugEnabled, 'seaman', 'host.docker.internal');
}
```

**Step 6: Run tests**

Run: `./vendor/bin/pest tests/Unit/Service/InitializationWizardTest.php`
Expected: PASS

**Step 7: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Service/InitializationWizard.php`
Expected: No errors

**Step 8: Commit**

```bash
git add src/Service/InitializationWizard.php tests/Unit/Service/InitializationWizardTest.php
git commit -m "feat(wizard): add server selection to initialization wizard"
```

---

### Task 6: Create Dockerfile.twig Template

**Files:**
- Create: `src/Template/docker/Dockerfile.twig`
- Delete: `docker/Dockerfile.template`

**Step 1: Create the Dockerfile.twig template**

Create `src/Template/docker/Dockerfile.twig`:

```dockerfile
{% if server == 'symfony' %}
FROM ubuntu:24.04

LABEL maintainer="Diego Rin Martín"

ARG WWWGROUP
ARG NODE_VERSION=20
ARG PHP_VERSION={{ php_version }}

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y software-properties-common gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 libpng-dev dnsutils librsvg2-bin fswatch ffmpeg vim nano fish\
    && add-apt-repository -y ppa:ondrej/php \
    && apt-get install -y python-is-python3 php-common php${PHP_VERSION}-common \
        php${PHP_VERSION}-cli php${PHP_VERSION}-dev \
        php${PHP_VERSION}-pgsql php${PHP_VERSION}-sqlite3 php${PHP_VERSION}-gd \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-imap php${PHP_VERSION}-mysql php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml php${PHP_VERSION}-zip php${PHP_VERSION}-bcmath php${PHP_VERSION}-soap \
        php${PHP_VERSION}-intl php${PHP_VERSION}-readline \
        php${PHP_VERSION}-ldap \
        php${PHP_VERSION}-msgpack php${PHP_VERSION}-igbinary php${PHP_VERSION}-redis \
        php${PHP_VERSION}-memcached php${PHP_VERSION}-pcov php${PHP_VERSION}-imagick php${PHP_VERSION}-xdebug \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && npm install -g npm \
    && npm install -g pnpm \
    && npm install -g bun \
    && apt-get update \
    && apt-get install -y \
        php${PHP_VERSION}-yaml \
        php${PHP_VERSION}-gmp \
        php${PHP_VERSION}-maxminddb \
        php-pear \
        jq \
        libbrotli-dev \
        libpcre3-dev \
        libssl-dev \
        pkg-config \
        protobuf-compiler librdkafka-dev \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN groupadd --force -g $WWWGROUP seaman
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 seaman

# Install Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
&& mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

WORKDIR /var/www/html

# Make xdebug-toggle script executable
COPY .seaman/scripts/xdebug-toggle.sh /usr/local/bin/xdebug-toggle
RUN chmod +x /usr/local/bin/xdebug-toggle || true

EXPOSE 80
CMD ["symfony", "server:start", "--port=80", "--allow-all-ip"]

{% else %}
FROM dunglas/frankenphp:latest-php{{ php_version }}-bookworm

LABEL maintainer="Diego Rin Martín"

ARG WWWGROUP=1000
ARG NODE_VERSION=20

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV SERVER_NAME=:80

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Install PHP extensions
RUN install-php-extensions \
    pgsql pdo_pgsql \
    mysqli pdo_mysql \
    sqlite3 pdo_sqlite \
    gd curl imap mbstring xml zip bcmath soap intl readline ldap \
    msgpack igbinary redis memcached imagick xdebug pcov \
    yaml gmp

# Install system dependencies and Node.js
RUN apt-get update \
    && apt-get install -y \
        gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 \
        libpng-dev dnsutils librsvg2-bin fswatch ffmpeg vim nano fish \
        jq pkg-config \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && npm install -g npm pnpm bun \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install Composer
RUN curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

# Create seaman user
RUN groupadd --force -g $WWWGROUP seaman \
    && useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 seaman

# Make xdebug-toggle script executable
COPY .seaman/scripts/xdebug-toggle.sh /usr/local/bin/xdebug-toggle
RUN chmod +x /usr/local/bin/xdebug-toggle || true

{% if server == 'frankenphp-worker' %}
# Worker mode: Copy Caddyfile
COPY .seaman/Caddyfile /etc/caddy/Caddyfile

EXPOSE 80
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
{% else %}
# Classic mode: PHP server
EXPOSE 80
CMD ["frankenphp", "php-server", "--root", "/var/www/html/public"]
{% endif %}

{% endif %}
```

**Step 2: Verify template syntax**

Run a quick Twig syntax check (or rely on tests).

**Step 3: Commit**

```bash
git add src/Template/docker/Dockerfile.twig
git rm docker/Dockerfile.template
git commit -m "feat(docker): convert Dockerfile to Twig template with FrankenPHP support"
```

---

### Task 7: Create Caddyfile.twig Template

**Files:**
- Create: `src/Template/docker/Caddyfile.twig`

**Step 1: Create the Caddyfile.twig template**

Create `src/Template/docker/Caddyfile.twig`:

```caddyfile
{
    frankenphp
    order php_server before file_server
}

:80 {
    root * /var/www/html/public

    php_server {
        worker {
            file ./public/index.php
            num {$PHP_WORKERS:2}
        }
    }

    file_server
}
```

**Step 2: Commit**

```bash
git add src/Template/docker/Caddyfile.twig
git commit -m "feat(docker): add Caddyfile template for FrankenPHP worker mode"
```

---

### Task 8: Update ProjectInitializer to Render Dockerfile

**Files:**
- Modify: `src/Service/ProjectInitializer.php`
- Modify: `tests/Unit/Service/ProjectInitializerTest.php` (if exists)

**Step 1: Update ProjectInitializer**

Update `src/Service/ProjectInitializer.php` to render Dockerfile with Twig:

```php
// Replace the Dockerfile copy section with:

// Render Dockerfile from Twig template
$dockerfileContent = $renderer->render('docker/Dockerfile.twig', [
    'server' => $config->php->server->value,
    'php_version' => $config->php->version->value,
]);
file_put_contents($seamanDir . '/Dockerfile', $dockerfileContent);

// Generate Caddyfile for worker mode
if ($config->php->server->isWorkerMode()) {
    $caddyfileContent = $renderer->render('docker/Caddyfile.twig', []);
    file_put_contents($seamanDir . '/Caddyfile', $caddyfileContent);
}
```

Add the import at the top:
```php
use Seaman\Enum\ServerType;
```

**Step 2: Run all tests**

Run: `./vendor/bin/pest`
Expected: PASS

**Step 3: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Service/ProjectInitializer.php`
Expected: No errors

**Step 4: Commit**

```bash
git add src/Service/ProjectInitializer.php
git commit -m "feat(init): render Dockerfile with Twig and generate Caddyfile for worker mode"
```

---

### Task 9: Update ConfigManager to Serialize Server

**Files:**
- Modify: `src/Service/ConfigManager.php`
- Check: Tests for ConfigManager

**Step 1: Find where configuration is serialized**

Search for where `php` section is written to YAML.

**Step 2: Add server to serialization**

Ensure the `server` property is included when saving configuration.

**Step 3: Run tests**

Run: `./vendor/bin/pest`
Expected: PASS

**Step 4: Commit**

```bash
git add src/Service/ConfigManager.php
git commit -m "feat(config): serialize server type in configuration"
```

---

### Task 10: Fix Any Remaining Test Failures

**Step 1: Run full test suite**

Run: `./vendor/bin/pest`

**Step 2: Fix any failures**

Address any remaining test failures from the changes.

**Step 3: Run PHPStan**

Run: `./vendor/bin/phpstan analyse`

**Step 4: Run php-cs-fixer**

Run: `./vendor/bin/php-cs-fixer fix`

**Step 5: Final commit**

```bash
git add -A
git commit -m "fix: resolve remaining test failures and code style issues"
```

---

### Task 11: Integration Test

**Step 1: Manual verification**

Create a test project and run through the wizard to verify:
1. Server selection appears after PHP version
2. All three options work
3. Dockerfile is generated correctly for each option
4. Caddyfile is generated only for worker mode
5. Xdebug warning appears for worker mode

**Step 2: Final cleanup**

Remove any debug code, ensure all files have proper ABOUTME comments.

**Step 3: Final commit and push**

```bash
git add -A
git commit -m "chore: final cleanup for FrankenPHP support"
git push origin feature/frankenphp-support
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Create ServerType enum | `src/Enum/ServerType.php` |
| 2 | Update PhpConfig | `src/ValueObject/PhpConfig.php` |
| 3 | Update PhpConfigParser | `src/Service/ConfigParser/PhpConfigParser.php` |
| 4 | Update InitializationChoices | `src/ValueObject/InitializationChoices.php` |
| 5 | Add selectServer to wizard | `src/Service/InitializationWizard.php` |
| 6 | Create Dockerfile.twig | `src/Template/docker/Dockerfile.twig` |
| 7 | Create Caddyfile.twig | `src/Template/docker/Caddyfile.twig` |
| 8 | Update ProjectInitializer | `src/Service/ProjectInitializer.php` |
| 9 | Update ConfigManager | `src/Service/ConfigManager.php` |
| 10 | Fix test failures | Various |
| 11 | Integration test | Manual verification |
