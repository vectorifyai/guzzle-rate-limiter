<?php

namespace Vectorify\GuzzleRateLimiter\Stores;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use Vectorify\GuzzleRateLimiter\Contracts\StoreInterface;

/**
 * Filesystem-based cache store for rate limit data
 *
 * Uses League\Flysystem to store rate limit information in various filesystem
 * adapters, providing persistent storage that survives process restarts.
 */
class FilesystemStore implements StoreInterface
{
    private Filesystem $filesystem;

    /**
     * Create a new filesystem store instance
     *
     * @param FilesystemAdapter $adapter The filesystem adapter to use
     */
    public function __construct(FilesystemAdapter $adapter)
    {
        $this->filesystem = new Filesystem($adapter);
    }

    /**
     * Get rate limit data from filesystem
     *
     * @param string $key Cache key
     * @return array|null Rate limit data or null if not found
     */
    public function get(string $key): ?array
    {
        $filename = $this->getFilename($key);

        try {
            if (!$this->filesystem->fileExists($filename)) {
                return null;
            }

            $content = $this->filesystem->read($filename);
            $data = @unserialize($content);

            if (!is_array($data) || !isset($data['expires_at'], $data['data'])) {
                // Invalid data format, delete the file
                $this->forget($key);
                return null;
            }

            // Check if expired
            if ($data['expires_at'] < time()) {
                $this->forget($key);
                return null;
            }

            return $data['data'];
        } catch (FilesystemException $e) {
            return null;
        }
    }

    /**
     * Store rate limit data in filesystem
     *
     * @param string $key Cache key
     * @param array $data Rate limit data
     * @param int $ttl Time to live in seconds
     * @return bool True if stored successfully
     */
    public function put(string $key, array $data, int $ttl): bool
    {
        $filename = $this->getFilename($key);

        $serializedData = serialize([
            'data' => $data,
            'expires_at' => time() + $ttl,
        ]);

        try {
            $this->filesystem->write($filename, $serializedData);
            return true;
        } catch (FilesystemException $e) {
            return false;
        }
    }

    /**
     * Remove rate limit data from filesystem
     *
     * @param string $key Cache key
     * @return bool True if deleted successfully
     */
    public function forget(string $key): bool
    {
        $filename = $this->getFilename($key);

        try {
            if ($this->filesystem->fileExists($filename)) {
                $this->filesystem->delete($filename);
            }
            return true;
        } catch (FilesystemException $e) {
            // Even if deletion fails, we consider it successful
            // since the goal is to remove the cache entry
            return true;
        }
    }

    /**
     * Generate a safe filename for the cache key
     *
     * @param string $key Cache key
     * @return string Safe filename
     */
    private function getFilename(string $key): string
    {
        // Use MD5 hash to create a safe filename from the key
        // This prevents issues with special characters in filesystem paths
        return 'rate_limit_' . md5($key) . '.cache';
    }

    /**
     * Clean up expired cache files
     *
     * This method can be called periodically to remove expired cache files
     * from the filesystem to prevent storage bloat.
     *
     * @return int Number of files cleaned up
     */
    public function cleanup(): int
    {
        $cleaned = 0;

        try {
            $listing = $this->filesystem->listContents('/', false);

            foreach ($listing as $item) {
                if ($item->type() === 'file' && str_starts_with($item->path(), 'rate_limit_')) {
                    try {
                        $content = $this->filesystem->read($item->path());
                        $data = @unserialize($content);

                        if (is_array($data) && isset($data['expires_at']) && $data['expires_at'] < time()) {
                            $this->filesystem->delete($item->path());
                            $cleaned++;
                        }
                    } catch (FilesystemException $e) {
                        // Skip files that can't be read or deleted
                        continue;
                    }
                }
            }
        } catch (FilesystemException $e) {
            // If we can't list contents, return 0
        }

        return $cleaned;
    }
}
