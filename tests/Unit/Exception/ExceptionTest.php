<?php

// ABOUTME: Tests for custom exception classes.
// ABOUTME: Validates exception messages and context.

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Seaman\Enum\OperatingMode;
use Seaman\Exception\InvalidConfigurationException;
use Seaman\Exception\PortConflictException;
use Seaman\Exception\UnsupportedModeException;

final class ExceptionTest extends TestCase
{
    public function test_unsupported_mode_exception_has_correct_message(): void
    {
        // Arrange
        $commandName = 'service:add';
        $mode = OperatingMode::Unmanaged;

        // Act
        $exception = UnsupportedModeException::forCommand($commandName, $mode);

        // Assert
        $this->assertInstanceOf(UnsupportedModeException::class, $exception);
        $this->assertStringContainsString($commandName, $exception->getMessage());
        $this->assertStringContainsString('Unmanaged', $exception->getMessage());
        $this->assertSame($commandName, $exception->getCommandName());
        $this->assertSame($mode, $exception->getMode());
    }

    public function test_port_conflict_exception_has_port_info(): void
    {
        // Arrange
        $port = 8080;
        $service = 'nginx';
        $conflictingProcess = 'apache2';

        // Act
        $exception = PortConflictException::forPort($port, $service, $conflictingProcess);

        // Assert
        $this->assertInstanceOf(PortConflictException::class, $exception);
        $this->assertStringContainsString((string) $port, $exception->getMessage());
        $this->assertStringContainsString($service, $exception->getMessage());
        $this->assertStringContainsString($conflictingProcess, $exception->getMessage());
        $this->assertSame($port, $exception->getPort());
        $this->assertSame($service, $exception->getService());
        $this->assertSame($conflictingProcess, $exception->getConflictingProcess());
    }

    public function test_invalid_configuration_exception_has_context(): void
    {
        // Arrange
        $message = 'Missing required field: database.host';
        $context = ['field' => 'database.host', 'section' => 'database'];

        // Act
        $exception = new InvalidConfigurationException($message, $context);

        // Assert
        $this->assertInstanceOf(InvalidConfigurationException::class, $exception);
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($context, $exception->getContext());
        $this->assertSame('database.host', $exception->getContext()['field']);
    }

    public function test_invalid_configuration_exception_without_context(): void
    {
        // Arrange
        $message = 'Invalid configuration';

        // Act
        $exception = new InvalidConfigurationException($message);

        // Assert
        $this->assertInstanceOf(InvalidConfigurationException::class, $exception);
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame([], $exception->getContext());
    }
}
