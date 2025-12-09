# Dynamic Port Allocation Design

## Overview

System for automatic port allocation when default ports are occupied by other processes or Seaman projects.

## Principles

- `seaman.yaml` defines "desired" ports (never modified by this system)
- On each `start`, verify availability and assign alternatives if needed
- If desired port becomes free again, use it automatically
- Increment strategy: port+1, port+2... up to 10 attempts
- Each port (main and additional) treated independently
- Interactive: user confirms alternative port before using

## Flow

1. User runs `seaman start`
2. For each port of each enabled service:
   - If available → use it
   - If occupied → find next available, ask user for confirmation
   - If user rejects or no ports available → abort with clear error
3. Generate docker-compose.yaml with real ports
4. Generate .env with real ports
5. Start containers

## Components

### PortChecker

```php
// src/Service/PortChecker.php
final readonly class PortChecker
{
    public function isPortAvailable(int $port): bool
    {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if ($socket !== false) {
            fclose($socket);
            return false; // Port in use
        }
        return true; // Port available
    }

    public function findAvailablePort(int $desiredPort, int $maxAttempts = 10): ?int
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $port = $desiredPort + $i;
            if ($this->isPortAvailable($port)) {
                return $port;
            }
        }
        return null;
    }
}
```

### PortAllocation (Value Object)

```php
// src/ValueObject/PortAllocation.php
final readonly class PortAllocation
{
    /**
     * @param array<string, array<int, int>> $allocations
     *        Map: serviceName => [desiredPort => assignedPort]
     */
    public function __construct(
        private array $allocations = [],
    ) {}

    public function withPort(string $service, int $desired, int $assigned): self
    {
        $newAllocations = $this->allocations;
        $newAllocations[$service][$desired] = $assigned;
        return new self($newAllocations);
    }

    public function getPort(string $service, int $desiredPort): int
    {
        return $this->allocations[$service][$desiredPort] ?? $desiredPort;
    }

    public function hasAlternatives(): bool
    {
        foreach ($this->allocations as $ports) {
            foreach ($ports as $desired => $assigned) {
                if ($desired !== $assigned) {
                    return true;
                }
            }
        }
        return false;
    }
}
```

### PortAllocator

```php
// src/Service/PortAllocator.php
final readonly class PortAllocator
{
    public function __construct(
        private PortChecker $portChecker,
    ) {}

    /**
     * @throws PortAllocationException if user rejects or no ports available
     */
    public function allocate(Configuration $config): PortAllocation
    {
        $allocation = new PortAllocation();

        foreach ($config->services->enabled() as $name => $serviceConfig) {
            foreach ($serviceConfig->getAllPorts() as $desiredPort) {
                if ($desiredPort === 0) {
                    continue; // SQLite doesn't use ports
                }

                if ($this->portChecker->isPortAvailable($desiredPort)) {
                    $allocation = $allocation->withPort($name, $desiredPort, $desiredPort);
                    continue;
                }

                // Port occupied - find alternative
                $alternativePort = $this->portChecker->findAvailablePort($desiredPort + 1);

                if ($alternativePort === null) {
                    throw PortAllocationException::noPortsAvailable($name, $desiredPort);
                }

                // Ask user confirmation
                $confirmed = confirm(
                    label: "Port {$desiredPort} is in use. Use {$alternativePort} for {$name}?",
                    default: true,
                );

                if (!$confirmed) {
                    throw PortAllocationException::userRejected($name, $desiredPort);
                }

                $allocation = $allocation->withPort($name, $desiredPort, $alternativePort);
            }
        }

        return $allocation;
    }
}
```

### PortAllocationException

```php
// src/Exception/PortAllocationException.php
final class PortAllocationException extends RuntimeException
{
    public static function noPortsAvailable(string $service, int $port): self
    {
        return new self("No available ports found for {$service} (tried {$port} to " . ($port + 10) . ")");
    }

    public static function userRejected(string $service, int $port): self
    {
        return new self("Port allocation for {$service} rejected by user");
    }
}
```

## Integration

### StartCommand

```php
// Before generating docker-compose
$allocation = $this->portAllocator->allocate($config);

// Pass allocation to generator
$this->dockerComposeGenerator->generate($config, $allocation);

// Regenerate .env with real ports
$this->configManager->generateEnvWithAllocation($config, $allocation);
```

### DockerComposeGenerator

- Receives `PortAllocation` as parameter
- When generating service config, uses `$allocation->getPort($serviceName, $desiredPort)`

### ConfigManager

- New method `generateEnvWithAllocation(Configuration $config, PortAllocation $allocation)`
- Uses assigned ports instead of config ports
- Variables like `DB_PORT` reflect actual port in use

## Files to Create

- `src/Service/PortChecker.php`
- `src/Service/PortAllocator.php`
- `src/ValueObject/PortAllocation.php`
- `src/Exception/PortAllocationException.php`

## Files to Modify

- `src/Command/StartCommand.php`
- `src/Service/Generator/DockerComposeGenerator.php`
- `src/Service/ConfigManager.php`
- `config/container.php`
