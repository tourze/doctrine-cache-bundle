<?php

namespace Tourze\DoctrineCacheBundle\Strategy;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Tourze\CacheStrategy\CacheStrategy;

class CacheStrategyCollector implements CacheStrategy
{
    public function __construct(
        #[TaggedIterator(CacheStrategy::SERVICE_TAG)] private readonly iterable $strategies,
    )
    {
    }

    public function shouldCache(string $query, array $params): bool
    {
        foreach ($this->strategies as $strategy) {
            /** @var CacheStrategy $strategy */
            // 有一个不给缓存，那就不缓存了
            if (!$strategy->shouldCache($query, $params)) {
                return false;
            }
        }
        return true;
    }
}
