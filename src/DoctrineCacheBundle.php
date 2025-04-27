<?php

namespace Tourze\DoctrineCacheBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BacktraceHelper\Backtrace;
use Tourze\DoctrineCacheBundle\Connection\CacheConnection;

class DoctrineCacheBundle extends Bundle
{
    public function boot(): void
    {
        parent::boot();
        Backtrace::addProdIgnoreFiles((new \ReflectionClass(CacheConnection::class))->getFileName());
    }
}
