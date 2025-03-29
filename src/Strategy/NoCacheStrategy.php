<?php

namespace Tourze\DoctrineCacheBundle\Strategy;

class NoCacheStrategy implements CacheStrategy
{
    public function shouldCache(string $query, array $params): bool
    {
        return true;
    }
}
