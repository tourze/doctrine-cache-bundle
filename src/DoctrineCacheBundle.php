<?php

declare(strict_types=1);

namespace Tourze\DoctrineCacheBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BacktraceHelper\Backtrace;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\DoctrineCacheBundle\Connection\CacheConnection;

class DoctrineCacheBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
        ];
    }

    public function boot(): void
    {
        parent::boot();
        $fileName = (new \ReflectionClass(CacheConnection::class))->getFileName();
        if (false !== $fileName) {
            Backtrace::addProdIgnoreFiles($fileName);
        }
    }
}
