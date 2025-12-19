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
    public function testShouldCacheWithEmptyStrategiesReturnsTrue(): void
    {
        $collector = new CacheStrategyCollector([]);

        $result = $collector->shouldCache('SELECT * FROM users', []);

        $this->assertTrue($result);
    }

    public function testShouldCacheWithSingleStrategyReturnTrue(): void
    {
        $strategy = new class implements CacheStrategy {
            public function shouldCache(string $query, array $params): bool
            {
                return true;
            }
        };

        $collector = new CacheStrategyCollector([$strategy]);

        $result = $collector->shouldCache('SELECT * FROM users', []);

        $this->assertTrue($result);
    }

    public function testShouldCacheWithSingleStrategyReturnFalse(): void
    {
        $strategy = new class implements CacheStrategy {
            public function shouldCache(string $query, array $params): bool
            {
                return false;
            }
        };

        $collector = new CacheStrategyCollector([$strategy]);

        $result = $collector->shouldCache('SELECT * FROM users', []);

        $this->assertFalse($result);
    }

    public function testShouldCacheReturnsTrueWhenAllStrategiesReturnTrue(): void
    {
        $strategy1 = new class implements CacheStrategy {
            public function shouldCache(string $query, array $params): bool
            {
                return true;
            }
        };

        $strategy2 = new class implements CacheStrategy {
            public function shouldCache(string $query, array $params): bool
            {
                return true;
            }
        };

        $collector = new CacheStrategyCollector([$strategy1, $strategy2]);

        $result = $collector->shouldCache('SELECT * FROM users', []);

        $this->assertTrue($result);
    }

    public function testShouldCacheReturnsFalseWhenAnyStrategyReturnsFalse(): void
    {
        $strategy1 = new class implements CacheStrategy {
            public function shouldCache(string $query, array $params): bool
            {
                return true;
            }
        };

        $strategy2 = new class implements CacheStrategy {
            public function shouldCache(string $query, array $params): bool
            {
                return false;
            }
        };

        $collector = new CacheStrategyCollector([$strategy1, $strategy2]);

        $result = $collector->shouldCache('SELECT * FROM users', []);

        $this->assertFalse($result);
    }

    public function testShouldCacheReturnsFalseWhenFirstStrategyReturnsFalse(): void
    {
        $strategy1 = new class implements CacheStrategy {
            public function shouldCache(string $query, array $params): bool
            {
                return false;
            }
        };

        $strategy2 = new class implements CacheStrategy {
            public function shouldCache(string $query, array $params): bool
            {
                return true;
            }
        };

        $collector = new CacheStrategyCollector([$strategy1, $strategy2]);

        $result = $collector->shouldCache('SELECT * FROM users', []);

        $this->assertFalse($result);
    }

    public function testShouldCacheWithMultipleStrategiesAllReturnFalse(): void
    {
        $strategy1 = new class implements CacheStrategy {
            public function shouldCache(string $query, array $params): bool
            {
                return false;
            }
        };

        $strategy2 = new class implements CacheStrategy {
            public function shouldCache(string $query, array $params): bool
            {
                return false;
            }
        };

        $collector = new CacheStrategyCollector([$strategy1, $strategy2]);

        $result = $collector->shouldCache('SELECT * FROM users', []);

        $this->assertFalse($result);
    }

    public function testShouldCachePassesQueryAndParamsToStrategies(): void
    {
        $capturedQuery = null;
        $capturedParams = null;

        $strategy = new class($capturedQuery, $capturedParams) implements CacheStrategy {
            public function __construct(
                private mixed &$capturedQuery,
                private mixed &$capturedParams,
            ) {
            }

            public function shouldCache(string $query, array $params): bool
            {
                $this->capturedQuery = $query;
                $this->capturedParams = $params;

                return true;
            }
        };

        $collector = new CacheStrategyCollector([$strategy]);

        $testQuery = 'SELECT * FROM users WHERE id = ?';
        $testParams = ['id' => 123];

        $collector->shouldCache($testQuery, $testParams);

        $this->assertSame($testQuery, $capturedQuery);
        $this->assertSame($testParams, $capturedParams);
    }
}
