<?php

namespace App\Session;

use App\Service\RedisService;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;

class RedisSessionHandler extends AbstractSessionHandler
{
    private const PREFIX = 'session:';

    public function __construct(
        private RedisService $redis,
        private int $ttl = 86400
    ) {}

    protected function doRead(string $sessionId): string
    {
        $data = $this->redis->get(self::PREFIX . $sessionId);
        return $data !== null ? $data : '';
    }

    protected function doWrite(string $sessionId, string $data): bool
    {
        return $this->redis->set(self::PREFIX . $sessionId, $data, $this->ttl);
    }

    protected function doDestroy(string $sessionId): bool
    {
        return $this->redis->delete(self::PREFIX . $sessionId);
    }

    public function close(): bool
    {
        return true; // Redis doesn't require explicit close
    }

    public function gc(int $max_lifetime): int|false
    {
        // Redis handles expiration automatically
        return 0;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        // Update TTL on session access
        return $this->redis->expire(self::PREFIX . $id, $this->ttl);
    }
}