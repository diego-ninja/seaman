<?php

declare(strict_types=1);

// ABOUTME: Manages SSL/TLS certificate generation for local development.
// ABOUTME: Supports mkcert (trusted) and self-signed (fallback) certificates.

namespace Seaman\Service\Process;

use Seaman\Contract\CommandExecutor;
use Seaman\ValueObject\CertificateResult;

final readonly class CertificateManager
{
    public function __construct(
        private CommandExecutor $executor,
    ) {}

    /**
     * Generate certificates for a project.
     * Uses mkcert if available, otherwise falls back to self-signed.
     */
    public function generateCertificates(string $projectName): CertificateResult
    {
        if ($this->hasMkcert()) {
            return $this->generateWithMkcert($projectName);
        }

        return $this->generateSelfSigned($projectName);
    }

    /**
     * Check if mkcert is available on the system.
     */
    public function hasMkcert(): bool
    {
        $result = $this->executor->execute(['which', 'mkcert']);
        return $result->isSuccessful();
    }

    /**
     * Generate certificates using mkcert (locally-trusted).
     */
    private function generateWithMkcert(string $projectName): CertificateResult
    {
        $this->executor->execute([
            'mkcert',
            '-cert-file', '.seaman/certs/cert.pem',
            '-key-file', '.seaman/certs/key.pem',
            "*.{$projectName}.local",
            "{$projectName}.local",
        ]);

        return new CertificateResult(
            type: 'mkcert',
            certPath: '.seaman/certs/cert.pem',
            keyPath: '.seaman/certs/key.pem',
            trusted: true,
        );
    }

    /**
     * Generate self-signed certificates using openssl (browser warnings).
     */
    private function generateSelfSigned(string $projectName): CertificateResult
    {
        $this->executor->execute([
            'openssl', 'req', '-x509', '-nodes', '-days', '365',
            '-newkey', 'rsa:2048',
            '-keyout', '.seaman/certs/key.pem',
            '-out', '.seaman/certs/cert.pem',
            '-subj', "/CN=*.{$projectName}.local",
        ]);

        return new CertificateResult(
            type: 'self-signed',
            certPath: '.seaman/certs/cert.pem',
            keyPath: '.seaman/certs/key.pem',
            trusted: false,
        );
    }
}
