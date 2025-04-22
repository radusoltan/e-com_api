<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
class CacheService
{
    public function __construct(
        private CacheInterface $cache,

    ){}

    /**
     * Retrieve or store cached content.
     *
     * @param string $key
     * @param callable $callback
     * @param int $ttl Time to live in seconds
     * @param bool $forceRefresh If true, bypass the cache and regenerate
     * @return mixed
     */
    public function get(string $key, callable $callback, int $ttl = 600, bool $forceRefresh = false): mixed
    {
        if ($forceRefresh) {
            $this->cache->delete($key);
        }

        return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl) {
            $item->expiresAfter($ttl);
            return $callback();
        });
    }

    /**
     * Remove an item from cache.
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }

    /**
     * Check if a key exists in cache.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->cache->hasItem($key);
    }

}