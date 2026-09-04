<?php

declare(strict_types=1);

// ABOUTME: Resolves supported Docker Compose filenames within a project.
// ABOUTME: Keeps mode detection and Docker execution aligned.

namespace Seaman\Service;

final readonly class ComposeFileLocator
{
    /** @var list<string> */
    private const array FILENAMES = [
        'docker-compose.yml',
        'docker-compose.yaml',
        'compose.yml',
        'compose.yaml',
    ];

    public function __construct(
        private string $projectPath,
    ) {}

    public function find(): ?string
    {
        foreach (self::FILENAMES as $filename) {
            $path = $this->projectPath . '/' . $filename;
            if (file_exists($path) || is_link($path)) {
                return $path;
            }
        }

        return null;
    }

    public function require(): string
    {
        return $this->find() ?? throw new \RuntimeException(
            "Docker Compose file not found in: {$this->projectPath}",
        );
    }

    /**
     * @return list<string>
     */
    public static function supportedFilenames(): array
    {
        return self::FILENAMES;
    }
}
