<?php

declare(strict_types=1);

namespace Tourze\DoctrineCacheBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class DoctrineCacheExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
