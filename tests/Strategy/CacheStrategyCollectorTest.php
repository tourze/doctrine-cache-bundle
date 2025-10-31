<?php

declare(strict_types=1);

namespace Tourze\DoctrineCacheBundle\Tests\Strategy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\CacheStrategy\CacheStrategy;
use Tourze\DoctrineCacheBundle\Strategy\CacheStrategyCollector;

/**
 * @internal
 */
#[CoversClass(CacheStrategyCollector::class)]
final class CacheStrategyCollectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testShouldCacheReturnsTrueWhenAllStrategiesReturnTrue(): void
    {
        // 创建模拟策略，总是返回 true
        $mockStrategy1 = $this->createMock(CacheStrategy::class);
        $mockStrategy1->expects($this->once())
            ->method('shouldCache')
            ->willReturn(true)
        ;

        $mockStrategy2 = $this->createMock(CacheStrategy::class);
        $mockStrategy2->expects($this->once())
            ->method('shouldCache')
            ->willReturn(true)
        ;

        $strategies = [$mockStrategy1, $mockStrategy2];

        $collector = new CacheStrategyCollector($strategies);

        $this->assertTrue($collector->shouldCache('SELECT * FROM users', []));
    }

    public function testShouldCacheReturnsFalseWhenAnyStrategyReturnsFalse(): void
    {
        // 第一个策略返回 true，第二个返回 false
        $mockStrategy1 = $this->createMock(CacheStrategy::class);
        $mockStrategy1->expects($this->once())
            ->method('shouldCache')
            ->willReturn(true)
        ;

        $mockStrategy2 = $this->createMock(CacheStrategy::class);
        $mockStrategy2->expects($this->once())
            ->method('shouldCache')
            ->willReturn(false)
        ;

        $strategies = [$mockStrategy1, $mockStrategy2];

        $collector = new CacheStrategyCollector($strategies);

        $this->assertFalse($collector->shouldCache('SELECT * FROM users', []));
    }

    public function testShouldCacheWithEmptyStrategiesReturnsTrue(): void
    {
        $collector = new CacheStrategyCollector([]);

        $this->assertTrue($collector->shouldCache('SELECT * FROM users', []));
    }
}
