<?php

declare(strict_types=1);

namespace Tourze\DoctrineCacheBundle\Tests\Cache;

use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * 测试用的缓存适配器
 * 因为 invalidateTags 方法属于 TagAwareCacheInterface 而不是基本的 CacheInterface
 */
class TagAwareCacheAdapter implements TagAwareCacheInterface
{
    private array $cache = [];

    public function get(string $key, callable $callback, ?float $beta = null, ?array $metadata = null): mixed
    {
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $callback();
        }

        return $this->cache[$key];
    }

    public function delete(string $key): bool
    {
        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
            return true;
        }

        return false;
    }

    public function invalidateTags(array $tags): bool
    {
        // 实际实现不重要，只需返回 true
        return true;
    }
}
