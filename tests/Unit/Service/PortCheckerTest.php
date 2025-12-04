<?php

// ABOUTME: Tests for PortChecker service.
// ABOUTME: Validates port availability checking and conflict detection.

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Seaman\Exception\PortConflictException;
use Seaman\Service\PortChecker;

final class PortCheckerTest extends TestCase
{
    private PortChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new PortChecker();
    }

    public function test_can_instantiate_port_checker(): void
    {
        $this->assertInstanceOf(PortChecker::class, $this->checker);
    }

    public function test_checks_if_port_is_available(): void
    {
        // Use a very high port number that's unlikely to be in use
        $port = 65432;
        $result = $this->checker->isPortAvailable($port);

        // Should return a boolean
        $this->assertIsBool($result);
    }

    public function test_gets_process_using_port(): void
    {
        // Use a very high port number that's unlikely to be in use
        $port = 65433;
        $result = $this->checker->getProcessUsingPort($port);

        // Should return null for an available port, or a string for a used port
        $this->assertTrue($result === null || is_string($result));
    }

    public function test_checks_multiple_ports(): void
    {
        // Use high port numbers unlikely to be in use
        $ports = [65430, 65431, 65432];
        $conflicts = $this->checker->checkPorts($ports);

        // Should return an array
        $this->assertIsArray($conflicts);
        // Each value should be a string (process name)
        foreach ($conflicts as $port => $process) {
            $this->assertIsInt($port);
            $this->assertIsString($process);
        }
    }

    public function test_ensure_port_available_does_not_throw_for_free_port(): void
    {
        // Use a very high port number that's unlikely to be in use
        $port = 65434;

        // Skip this test if the port is actually in use
        if (!$this->checker->isPortAvailable($port)) {
            $this->markTestSkipped("Port {$port} is in use, cannot test");
        }

        // Should not throw an exception
        $this->expectNotToPerformAssertions();
        $this->checker->ensurePortAvailable($port, 'test-service');
    }

    public function test_ensure_port_available_throws_for_used_port(): void
    {
        // Create a temporary socket to occupy a port
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            $this->markTestSkipped('Cannot create socket for testing');
        }

        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        $bound = @socket_bind($socket, '127.0.0.1', 0);

        if (!$bound) {
            socket_close($socket);
            $this->markTestSkipped('Cannot bind socket for testing');
        }

        socket_listen($socket);

        // Get the actual port number that was assigned
        $success = socket_getsockname($socket, $address, $port);
        if (!$success) {
            socket_close($socket);
            $this->markTestSkipped('Cannot get socket name for testing');
        }

        try {
            // Now the port should be in use
            $this->expectException(PortConflictException::class);
            $this->checker->ensurePortAvailable($port, 'test-service');
        } finally {
            socket_close($socket);
        }
    }
}
