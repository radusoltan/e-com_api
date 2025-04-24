<?php

namespace App\Service;

use Predis\Client;
use Psr\Log\LoggerInterface;

class RedisService
{

    private Client $redis;
    private LoggerInterface $logger;

    public function __construct(
        string $redisConnectionString,
        LoggerInterface $logger
    ) {
        $this->redis = new Client($redisConnectionString);
        $this->logger = $logger;
    }

    public function get(string $key)
    {
        try {
            return $this->redis->get($key);
        } catch (\Exception $e) {
            $this->logger->error('Redis get error: ' . $e->getMessage());
            return null;
        }
    }

    public function set(string $key, $value, int $expiry = null): bool
    {
        try {
            if ($expiry !== null) {
                return $this->redis->setex($key, $expiry, $value);
            }
            return $this->redis->set($key, $value);
        } catch (\Exception $e) {
            $this->logger->error('Redis set error: ' . $e->getMessage());
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            return (bool)$this->redis->del($key);
        } catch (\Exception $e) {
            $this->logger->error('Redis delete error: ' . $e->getMessage());
            return false;
        }
    }

    public function exists(string $key): bool
    {
        try {
            return (bool)$this->redis->exists($key);
        } catch (\Exception $e) {
            $this->logger->error('Redis exists error: ' . $e->getMessage());
            return false;
        }
    }

    public function increment(string $key, int $by = 1): int
    {
        try {
            return $this->redis->incrby($key, $by);
        } catch (\Exception $e) {
            $this->logger->error('Redis increment error: ' . $e->getMessage());
            return 0;
        }
    }

    public function expire(string $key, int $seconds): bool
    {
        try {
            return (bool)$this->redis->expire($key, $seconds);
        } catch (\Exception $e) {
            $this->logger->error('Redis expire error: ' . $e->getMessage());
            return false;
        }
    }

    public function getClient(): Client
    {
        return $this->redis;
    }

}