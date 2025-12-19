<?php

declare(strict_types=1);

namespace Tourze\DoctrineCacheBundle\Strategy;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Tourze\CacheStrategy\CacheStrategy;

final readonly class CacheStrategyCollector implements CacheStrategy
{
    /**
     * @param iterable<CacheStrategy> $strategies
     */
    public function __construct(
        #[AutowireIterator(tag: CacheStrategy::SERVICE_TAG)] private iterable $strategies,
    ) {
    }

    /**
     * @param array<mixed> $params
     */
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
