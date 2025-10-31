<?php

declare(strict_types=1);

namespace Tourze\DoctrineCacheBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DoctrineCacheBundle\DependencyInjection\DoctrineCacheExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(DoctrineCacheExtension::class)]
final class DoctrineCacheExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    protected function provideServiceDirectories(): iterable
    {
        yield from parent::provideServiceDirectories();
        yield 'Strategy';
    }
}
