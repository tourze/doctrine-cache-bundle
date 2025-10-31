<?php

declare(strict_types=1);

namespace Tourze\DoctrineCacheBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DoctrineCacheBundle\DoctrineCacheBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(DoctrineCacheBundle::class)]
#[RunTestsInSeparateProcesses]
final class DoctrineCacheBundleTest extends AbstractBundleTestCase
{
}
