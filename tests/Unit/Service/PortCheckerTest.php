<?php

// ABOUTME: Tests for PortChecker service.
// ABOUTME: Validates port availability checking and conflict detection.

declare(strict_types=1);

namespace Seaman\Tests\Unit\Service;

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
        // Create a socket to occupy a port, then verify it's detected as unavailable
        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            $this->markTestSkipped('Cannot create socket for testing');
        }

        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        if (!@socket_bind($socket, '127.0.0.1', 0)) {
            socket_close($socket);
            $this->markTestSkipped('Cannot bind socket for testing');
        }

        socket_listen($socket);

        $port = null;
        socket_getsockname($socket, $addr, $port);

        if (!is_int($port)) {
            socket_close($socket);
            $this->markTestSkipped('Cannot determine bound port');
        }

        try {
            // Port should NOT be available (we're listening on it)
            $result = $this->checker->isPortAvailable($port);
            $this->assertFalse($result, "Port {$port} should be detected as in use");
        } finally {
            socket_close($socket);
        }
    }

    public function test_gets_process_using_port(): void
    {
        // Use a very high port number that's unlikely to be in use
        $port = 65433;
        $result = $this->checker->getProcessUsingPort($port);

        // Should return null for an available port
        $this->assertNull($result);
    }

    public function test_checks_multiple_ports(): void
    {
        // Create sockets to occupy ports, keep them open, and verify detection
        $ports = [];
        $sockets = [];

        for ($i = 0; $i < 3; $i++) {
            $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket === false) {
                foreach ($sockets as $s) {
                    socket_close($s);
                }
                $this->markTestSkipped('Cannot create socket for testing');
            }

            socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
            if (!@socket_bind($socket, '127.0.0.1', 0)) {
                socket_close($socket);
                foreach ($sockets as $s) {
                    socket_close($s);
                }
                $this->markTestSkipped('Cannot bind socket for testing');
            }

            socket_listen($socket);

            $port = null;
            socket_getsockname($socket, $addr, $port);
            if (is_int($port)) {
                $ports[] = $port;
            }
            $sockets[] = $socket;
        }

        if (count($ports) < 3) {
            foreach ($sockets as $socket) {
                socket_close($socket);
            }
            $this->markTestSkipped('Cannot determine bound ports');
        }

        try {
            // All ports should be detected as in use
            $conflicts = $this->checker->checkPorts($ports);
            $this->assertCount(3, $conflicts, 'Should detect all 3 ports as in use');
        } finally {
            foreach ($sockets as $socket) {
                socket_close($socket);
            }
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
        $port = null;
        $success = socket_getsockname($socket, $address, $port);
        if (!$success || !is_int($port)) {
            socket_close($socket);
            $this->markTestSkipped('Cannot get socket name for testing');
        }
        /** @var int<1, max> $boundPort */
        $boundPort = max(1, $port);

        try {
            // Now the port should be in use
            $this->expectException(PortConflictException::class);
            $this->checker->ensurePortAvailable($boundPort, 'test-service');
        } finally {
            socket_close($socket);
        }
    }
}
