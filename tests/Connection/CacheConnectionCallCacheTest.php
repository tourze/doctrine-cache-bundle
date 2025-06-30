<?php

declare(strict_types=1);

namespace Tourze\DoctrineCacheBundle\Tests\Connection;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Tourze\CacheStrategy\CacheStrategy;
use Tourze\DoctrineCacheBundle\Connection\CacheConnection;

class CacheConnectionCallCacheTest extends TestCase
{
    private CacheConnection $cacheConnection;
    private Connection|MockObject $innerConnection;
    private TagAwareCacheInterface|MockObject $cache;
    private LoggerInterface|MockObject $logger;
    private CacheStrategy|MockObject $cacheStrategy;

    protected function setUp(): void
    {
        $this->innerConnection = $this->createMock(Connection::class);
        $this->cache = $this->createMock(TagAwareCacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cacheStrategy = $this->createMock(CacheStrategy::class);

        $this->innerConnection->method('getParams')->willReturn(['url' => 'mysql://user:pass@localhost/dbname']);

        $this->cacheConnection = new CacheConnection(
            $this->innerConnection,
            $this->cache,
            $this->logger,
            $this->cacheStrategy
        );
    }

    /**
     * 测试 callCache 方法在标准场景下通过反射调用
     */
    public function testCallCache_withShouldCacheTrue_getsFromCache(): void
    {
        // 创建反射方法以访问私有方法
        $reflection = new \ReflectionClass(CacheConnection::class);
        $method = $reflection->getMethod('callCache');
        $method->setAccessible(true);

        $func = 'fetchAssociative';
        $query = 'SELECT * FROM users WHERE id = ?';
        $params = [[1], []]; // 包装在数组中的参数
        $expected = ['id' => 1, 'name' => 'Test User'];

        $callback = function () use ($expected) {
            return $expected;
        };

        // 设置缓存策略应该缓存
        $this->cacheStrategy->expects($this->once())
            ->method('shouldCache')
            ->with($query)
            ->willReturn(true);

        // 缓存应该被调用并返回结果
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callable) use ($expected) {
                return $expected;
            });

        $result = $method->invoke($this->cacheConnection, $func, $query, $params, $callback);
        $this->assertSame($expected, $result);
    }

    /**
     * 测试 callCache 方法在缓存机制不应使用时直接调用回调
     */
    public function testCallCache_withShouldCacheFalse_callsCallback(): void
    {
        // 创建反射方法以访问私有方法
        $reflection = new \ReflectionClass(CacheConnection::class);
        $method = $reflection->getMethod('callCache');
        $method->setAccessible(true);

        $func = 'fetchAssociative';
        $query = 'SELECT * FROM users WHERE id = ?';
        $params = [[1], []]; // 包装在数组中的参数
        $expected = ['id' => 1, 'name' => 'Test User'];

        $callbackCalled = false;
        $callback = function () use ($expected, &$callbackCalled) {
            $callbackCalled = true;
            return $expected;
        };

        // 设置缓存策略不应缓存
        $this->cacheStrategy->expects($this->once())
            ->method('shouldCache')
            ->with($query)
            ->willReturn(false);

        // 缓存不应该被调用
        $this->cache->expects($this->never())
            ->method('get');

        $result = $method->invoke($this->cacheConnection, $func, $query, $params, $callback);

        $this->assertTrue($callbackCalled);
        $this->assertSame($expected, $result);
    }

    /**
     * 测试 callCache 方法在缓存开关关闭时直接调用回调
     */
    public function testCallCache_withOpenCacheFalse_callsCallback(): void
    {
        // 创建反射方法以访问私有方法
        $reflection = new \ReflectionClass(CacheConnection::class);
        $method = $reflection->getMethod('callCache');
        $method->setAccessible(true);

        // 关闭缓存
        $this->cacheConnection->setOpenCache(false);

        $func = 'fetchAssociative';
        $query = 'SELECT * FROM users WHERE id = ?';
        $params = [[1], []]; // 包装在数组中的参数
        $expected = ['id' => 1, 'name' => 'Test User'];

        $callbackCalled = false;
        $callback = function () use ($expected, &$callbackCalled) {
            $callbackCalled = true;
            return $expected;
        };

        // 根据实际实现，即使缓存关闭，仍可能调用缓存策略
        // 所以不要限制它不被调用

        // 缓存不应该被调用
        $this->cache->expects($this->never())
            ->method('get');

        $result = $method->invoke($this->cacheConnection, $func, $query, $params, $callback);

        $this->assertTrue($callbackCalled);
        $this->assertSame($expected, $result);
    }

    /**
     * 测试 callCache 方法在事务进行时直接调用回调
     */
    public function testCallCache_withActiveTransaction_callsCallback(): void
    {
        // 创建反射方法以访问私有方法
        $reflection = new \ReflectionClass(CacheConnection::class);
        $method = $reflection->getMethod('callCache');
        $method->setAccessible(true);

        // 设置内部连接报告事务激活
        $this->innerConnection->method('isTransactionActive')
            ->willReturn(true);

        $func = 'fetchAssociative';
        $query = 'SELECT * FROM users WHERE id = ?';
        $params = [[1], []]; // 包装在数组中的参数
        $expected = ['id' => 1, 'name' => 'Test User'];

        $callbackCalled = false;
        $callback = function () use ($expected, &$callbackCalled) {
            $callbackCalled = true;
            return $expected;
        };

        // 根据实际实现，即使在事务中，仍可能调用缓存策略
        // 所以不要限制它不被调用

        // 缓存不应该被调用
        $this->cache->expects($this->never())
            ->method('get');

        $result = $method->invoke($this->cacheConnection, $func, $query, $params, $callback);

        $this->assertTrue($callbackCalled);
        $this->assertSame($expected, $result);
    }

    /**
     * 测试 callCache 方法在写操作时直接调用回调
     */
    public function testCallCache_withWriteOperation_callsCallback(): void
    {
        // 创建反射方法以访问私有方法
        $reflection = new \ReflectionClass(CacheConnection::class);
        $method = $reflection->getMethod('callCache');
        $method->setAccessible(true);

        // 使用写操作
        $func = 'insert';
        $query = 'INSERT INTO users (name) VALUES (?)';
        $params = [[1], []]; // 包装在数组中的参数
        $expected = 1;

        $callbackCalled = false;
        $callback = function () use ($expected, &$callbackCalled) {
            $callbackCalled = true;
            return $expected;
        };

        // 根据实际实现，即使是写操作，仍可能调用缓存策略
        // 所以不要限制它不被调用

        // 缓存不应该被调用
        $this->cache->expects($this->never())
            ->method('get');

        $result = $method->invoke($this->cacheConnection, $func, $query, $params, $callback);

        $this->assertTrue($callbackCalled);
        $this->assertSame($expected, $result);
    }

}
