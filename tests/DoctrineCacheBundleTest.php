<?php

namespace Tourze\DoctrineCacheBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\DoctrineCacheBundle\DoctrineCacheBundle;

class DoctrineCacheBundleTest extends TestCase
{
    public function testInstanceOfBundle(): void
    {
        $bundle = new DoctrineCacheBundle();

        $this->assertInstanceOf(Bundle::class, $bundle);
    }
}
