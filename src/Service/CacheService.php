<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CacheService
{
    private TagAwareAdapterInterface $cache;

    public function __construct(
        LoggerInterface $logger,
        ?TagAwareAdapterInterface $cache = null,
        private bool $cacheEnabled = true,
        private string $cachePrefix = 'app_',
        private array $tagPrefixes = []
    ) {
        // If no cache adapter is injected, create a default one
        if ($cache === null) {
            $this->cache = new TagAwareAdapter(new FilesystemAdapter());
        } else {
            $this->cache = $cache;
        }

        $this->logger = $logger;
    }

    /**
     * Retrieve or store cached content with tags
     *
     * @param string $key The cache key
     * @param callable $callback The callback to generate the value
     * @param int $ttl Time to live in seconds
     * @param bool $forceRefresh If true, bypass the cache and regenerate
     * @param array $tags Array of tag names for invalidation
     * @return mixed The cached value
     */
    public function get(string $key, callable $callback, int $ttl = 3600, bool $forceRefresh = false, array $tags = []): mixed
    {
        // Skip cache if disabled or forced refresh
        if (!$this->cacheEnabled || $forceRefresh) {
            try {
                $value = $callback();

                // If cache is enabled but we're doing a force refresh, update the cache with the new value
                if ($this->cacheEnabled && $forceRefresh) {
                    $this->set($key, $value, $ttl, $tags);
                }

                return $value;
            } catch (\Throwable $e) {
                $this->logger->error('Error generating cached value', [
                    'key' => $key,
                    'exception' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        // Prefix the key to avoid collisions
        $prefixedKey = $this->getPrefixedKey($key);

        try {
            return $this->cache->get($prefixedKey, function (ItemInterface $item) use ($callback, $ttl, $tags) {
                $item->expiresAfter($ttl);

                // Add tags with prefixes
                if (!empty($tags)) {
                    $prefixedTags = $this->getPrefixedTags($tags);
                    $item->tag($prefixedTags);
                }

                return $callback();
            });
        } catch (\Throwable $e) {
            $this->logger->error('Cache error', [
                'key' => $key,
                'exception' => $e->getMessage(),
            ]);

            // On cache error, fall back to direct execution
            return $callback();
        }
    }

    /**
     * Explicitly set an item in the cache
     *
     * @param string $key The cache key
     * @param mixed $value The value to cache
     * @param int $ttl Time to live in seconds
     * @param array $tags Array of tag names for invalidation
     * @return bool Success indicator
     */
    public function set(string $key, mixed $value, int $ttl = 3600, array $tags = []): bool
    {
        if (!$this->cacheEnabled) {
            return false;
        }

        try {
            $prefixedKey = $this->getPrefixedKey($key);
            $item = $this->cache->getItem($prefixedKey);
            $item->set($value);
            $item->expiresAfter($ttl);

            // Add tags with prefixes
            if (!empty($tags)) {
                $prefixedTags = $this->getPrefixedTags($tags);
                $item->tag($prefixedTags);
            }

            return $this->cache->save($item);
        } catch (\Throwable $e) {
            $this->logger->error('Error setting cached value', [
                'key' => $key,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Remove an item from cache
     *
     * @param string $key The cache key
     * @return bool Success indicator
     */
    public function delete(string $key): bool
    {
        if (!$this->cacheEnabled) {
            return false;
        }

        try {
            $prefixedKey = $this->getPrefixedKey($key);
            return $this->cache->deleteItem($prefixedKey);
        } catch (\Throwable $e) {
            $this->logger->error('Error deleting cached item', [
                'key' => $key,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete all items with a specific tag
     *
     * @param string $tag The tag name
     * @return bool Success indicator
     */
    public function invalidateTag(string $tag): bool
    {
        if (!$this->cacheEnabled) {
            return false;
        }

        try {
            $prefixedTag = $this->getPrefixedTag($tag);
            return $this->cache->invalidateTags([$prefixedTag]);
        } catch (\Throwable $e) {
            $this->logger->error('Error invalidating tag', [
                'tag' => $tag,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete all items with specific tags
     *
     * @param array $tags Array of tag names
     * @return bool Success indicator
     */
    public function invalidateTags(array $tags): bool
    {
        if (!$this->cacheEnabled || empty($tags)) {
            return false;
        }

        try {
            $prefixedTags = $this->getPrefixedTags($tags);
            return $this->cache->invalidateTags($prefixedTags);
        } catch (\Throwable $e) {
            $this->logger->error('Error invalidating tags', [
                'tags' => $tags,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if a key exists in cache
     *
     * @param string $key The cache key
     * @return bool Whether the item exists
     */
    public function has(string $key): bool
    {
        if (!$this->cacheEnabled) {
            return false;
        }

        try {
            $prefixedKey = $this->getPrefixedKey($key);
            return $this->cache->hasItem($prefixedKey);
        } catch (\Throwable $e) {
            $this->logger->error('Error checking if item exists in cache', [
                'key' => $key,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Clear the entire cache
     *
     * @return bool Success indicator
     */
    public function clear(): bool
    {
        if (!$this->cacheEnabled) {
            return false;
        }

        try {
            return $this->cache->clear();
        } catch (\Throwable $e) {
            $this->logger->error('Error clearing cache', [
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Enable or disable caching temporarily
     *
     * @param bool $enabled Whether caching should be enabled
     * @return void
     */
    public function setEnabled(bool $enabled): void
    {
        $this->cacheEnabled = $enabled;
    }

    /**
     * Get cache enabled status
     *
     * @return bool Whether caching is enabled
     */
    public function isEnabled(): bool
    {
        return $this->cacheEnabled;
    }

    /**
     * Prefix a cache key
     *
     * @param string $key The original key
     * @return string The prefixed key
     */
    private function getPrefixedKey(string $key): string
    {
        return $this->cachePrefix . $key;
    }

    /**
     * Apply prefix to a tag
     *
     * @param string $tag The original tag
     * @return string The prefixed tag
     */
    private function getPrefixedTag(string $tag): string
    {
        // Check if there's a specific prefix for this tag
        foreach ($this->tagPrefixes as $tagName => $prefix) {
            if (str_starts_with($tag, $tagName)) {
                return $prefix . $tag;
            }
        }

        // Use default prefix
        return $this->cachePrefix . 'tag_' . $tag;
    }

    /**
     * Apply prefixes to an array of tags
     *
     * @param array $tags The original tags
     * @return array The prefixed tags
     */
    private function getPrefixedTags(array $tags): array
    {
        return array_map([$this, 'getPrefixedTag'], $tags);
    }

    /**
     * Shortcut method for get(), semantic alias for readability.
     *
     * @param string $key The cache key
     * @param int $ttl Time to live in seconds
     * @param callable $callback Value generator if not cached
     * @param bool $forceRefresh Bypass cache if true
     * @param array $tags Tags for tagging the cache
     * @return mixed
     */
    public function remember(string $key, int $ttl, callable $callback, bool $forceRefresh = false, array $tags = []): mixed
    {
        return $this->get($key, $callback, $ttl, $forceRefresh, $tags);
    }

}