<?php

namespace App\Service;

class JwtBlacklistService
{
    private const PREFIX = 'jwt_blacklist:';

    public function __construct(
        private RedisService $redis
    ) {}

    public function blacklist(string $token, int $ttl): bool
    {
        $key = self::PREFIX . md5($token);
        return $this->redis->set($key, '1', $ttl);
    }

    public function isBlacklisted(string $token): bool
    {
        $key = self::PREFIX . md5($token);
        return (bool)$this->redis->exists($key);
    }
}