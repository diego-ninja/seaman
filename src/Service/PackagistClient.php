<?php

// ABOUTME: HTTP client for querying Packagist API.
// ABOUTME: Searches for packages by type (seaman-plugin) with optional caching.

declare(strict_types=1);

namespace Seaman\Service;

use Seaman\Exception\PackagistException;

final class PackagistClient
{
    private const BASE_URL = 'https://packagist.org';
    private const SEARCH_ENDPOINT = '/search.json';
    private const PACKAGE_TYPE = 'seaman-plugin';
    private const CACHE_TTL = 3600; // 1 hour
    private const REQUEST_TIMEOUT = 10;

    public function __construct(
        private readonly ?string $cacheDir = null,
    ) {}

    /**
     * Search for seaman-plugin packages on Packagist.
     *
     * @return list<array{name: string, description: string, url: string, downloads: int, favers: int}>
     *
     * @throws PackagistException
     */
    public function searchPlugins(?string $query = null): array
    {
        $cacheKey = 'packagist_plugins_' . md5($query ?? '');
        $cached = $this->getFromCache($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $results = $this->fetchAllPages($query);
        $this->saveToCache($cacheKey, $results);

        return $results;
    }

    /**
     * @return list<array{name: string, description: string, url: string, downloads: int, favers: int}>
     *
     * @throws PackagistException
     */
    private function fetchAllPages(?string $query): array
    {
        $results = [];
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $url = $this->buildSearchUrl($query, $page);
            $response = $this->request($url);

            /** @var list<array{name?: string, description?: string, url?: string, downloads?: int, favers?: int}> $packages */
            $packages = $response['results'] ?? [];

            foreach ($packages as $package) {
                $name = $package['name'] ?? null;
                if (!is_string($name) || $name === '') {
                    continue;
                }

                $results[] = [
                    'name' => $name,
                    'description' => is_string($package['description'] ?? null) ? $package['description'] : '',
                    'url' => is_string($package['url'] ?? null) ? $package['url'] : '',
                    'downloads' => is_int($package['downloads'] ?? null) ? $package['downloads'] : 0,
                    'favers' => is_int($package['favers'] ?? null) ? $package['favers'] : 0,
                ];
            }

            $hasMore = isset($response['next']) && is_string($response['next']) && $response['next'] !== '';
            $page++;

            // Safety limit
            if ($page > 10) {
                break;
            }
        }

        return $results;
    }

    private function buildSearchUrl(?string $query, int $page): string
    {
        $params = [
            'type' => self::PACKAGE_TYPE,
            'page' => $page,
        ];

        if ($query !== null && $query !== '') {
            $params['q'] = $query;
        }

        return self::BASE_URL . self::SEARCH_ENDPOINT . '?' . http_build_query($params);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PackagistException
     */
    private function request(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::REQUEST_TIMEOUT,
                'header' => [
                    'User-Agent: Seaman/1.0',
                    'Accept: application/json',
                ],
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new PackagistException('Failed to connect to Packagist API');
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($response, true);

        if ($data === null) {
            throw new PackagistException('Invalid JSON response from Packagist API');
        }

        if (isset($data['status']) && $data['status'] === 'error') {
            $message = isset($data['message']) && is_string($data['message'])
                ? $data['message']
                : 'Packagist API error';
            throw new PackagistException($message);
        }

        return $data;
    }

    /**
     * @return list<array{name: string, description: string, url: string, downloads: int, favers: int}>|null
     */
    private function getFromCache(string $key): ?array
    {
        if ($this->cacheDir === null) {
            return null;
        }

        $cacheFile = $this->getCacheFile($key);

        if (!file_exists($cacheFile)) {
            return null;
        }

        $mtime = filemtime($cacheFile);
        if ($mtime === false || (time() - $mtime) > self::CACHE_TTL) {
            @unlink($cacheFile);
            return null;
        }

        $content = file_get_contents($cacheFile);
        if ($content === false) {
            return null;
        }

        /** @var list<array{name: string, description: string, url: string, downloads: int, favers: int}>|null $data */
        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param list<array{name: string, description: string, url: string, downloads: int, favers: int}> $data
     */
    private function saveToCache(string $key, array $data): void
    {
        if ($this->cacheDir === null) {
            return;
        }

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }

        $cacheFile = $this->getCacheFile($key);
        @file_put_contents($cacheFile, json_encode($data));
    }

    private function getCacheFile(string $key): string
    {
        return $this->cacheDir . '/' . $key . '.json';
    }
}
