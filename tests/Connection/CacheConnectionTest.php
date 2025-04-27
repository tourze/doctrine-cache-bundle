<?php

declare(strict_types=1);

namespace Tourze\DoctrineCacheBundle\Tests\Connection;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Tourze\CacheStrategy\CacheStrategy;
use Tourze\DoctrineCacheBundle\Connection\CacheConnection;

class CacheConnectionTest extends TestCase
{
    private Connection|MockObject $innerConnection;
    private TagAwareCacheInterface|MockObject $cache;
    private LoggerInterface|MockObject $logger;
    private CacheStrategy|MockObject $cacheStrategy;
    private CacheConnection $cacheConnection;
    private Driver|MockObject $driver;
    private Configuration|MockObject $configuration;
    private AbstractPlatform|MockObject $platform;

    protected function setUp(): void
    {
        $this->innerConnection = $this->createMock(Connection::class);

        // 使用 TagAwareCacheInterface
        $this->cache = $this->createMock(TagAwareCacheInterface::class);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cacheStrategy = $this->createMock(CacheStrategy::class);

        $this->driver = $this->createMock(Driver::class);
        $this->configuration = $this->createMock(Configuration::class);
        $this->platform = $this->createMock(AbstractPlatform::class);

        $this->innerConnection->method('getParams')->willReturn(['url' => 'mysql://user:pass@localhost/dbname']);
        $this->innerConnection->method('getDriver')->willReturn($this->driver);
        $this->innerConnection->method('getConfiguration')->willReturn($this->configuration);
        $this->innerConnection->method('getDatabasePlatform')->willReturn($this->platform);

        $this->cacheConnection = new CacheConnection(
            $this->innerConnection,
            $this->cache,
            $this->logger,
            $this->cacheStrategy
        );
    }

    public function testIsOpenCache_defaultsToTrue(): void
    {
        $this->assertTrue($this->cacheConnection->isOpenCache());
    }

    public function testSetOpenCache_changesOpenCacheFlag(): void
    {
        $this->cacheConnection->setOpenCache(false);
        $this->assertFalse($this->cacheConnection->isOpenCache());

        $this->cacheConnection->setOpenCache(true);
        $this->assertTrue($this->cacheConnection->isOpenCache());
    }

    public function testGetParams_delegatesToInnerConnection(): void
    {
        $params = ['url' => 'mysql://user:pass@localhost/dbname'];
        $this->innerConnection->expects($this->once())
            ->method('getParams')
            ->willReturn($params);

        $this->assertSame($params, $this->cacheConnection->getParams());
    }

    public function testGetDatabase_delegatesToInnerConnection(): void
    {
        $database = 'test_db';
        $this->innerConnection->expects($this->once())
            ->method('getDatabase')
            ->willReturn($database);

        $this->assertSame($database, $this->cacheConnection->getDatabase());
    }

    public function testGetDriver_delegatesToInnerConnection(): void
    {
        $this->innerConnection->expects($this->once())
            ->method('getDriver')
            ->willReturn($this->driver);

        $this->assertSame($this->driver, $this->cacheConnection->getDriver());
    }

    public function testGetConfiguration_delegatesToInnerConnection(): void
    {
        $this->innerConnection->expects($this->once())
            ->method('getConfiguration')
            ->willReturn($this->configuration);

        $this->assertSame($this->configuration, $this->cacheConnection->getConfiguration());
    }

    public function testGetDatabasePlatform_delegatesToInnerConnection(): void
    {
        $this->innerConnection->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($this->platform);

        $this->assertSame($this->platform, $this->cacheConnection->getDatabasePlatform());
    }

    public function testCreateExpressionBuilder_delegatesToInnerConnection(): void
    {
        $expressionBuilder = $this->createStub(\Doctrine\DBAL\Query\Expression\ExpressionBuilder::class);
        $this->innerConnection->expects($this->once())
            ->method('createExpressionBuilder')
            ->willReturn($expressionBuilder);

        $this->assertSame($expressionBuilder, $this->cacheConnection->createExpressionBuilder());
    }

    public function testFetchAssociative_queryCached_returnsFromCache(): void
    {
        $sql = 'SELECT * FROM users WHERE id = ?';
        $params = [1];
        $types = [];
        $expected = ['id' => 1, 'name' => 'Test User'];

        // 配置缓存策略
        $this->cacheStrategy->expects($this->once())
            ->method('shouldCache')
            ->with($sql)
            ->willReturn(true);

        // 设置缓存行为
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) use ($expected) {
                return $expected;
            });

        $result = $this->cacheConnection->fetchAssociative($sql, $params, $types);
        $this->assertSame($expected, $result);
    }

    public function testFetchAssociative_queryNotCached_returnsFromConnection(): void
    {
        $sql = 'SELECT * FROM users WHERE id = ?';
        $params = [1];
        $types = [];
        $expected = ['id' => 1, 'name' => 'Test User'];

        // 配置缓存策略为不缓存
        $this->cacheStrategy->expects($this->once())
            ->method('shouldCache')
            ->with($sql)
            ->willReturn(false);

        // 内部连接应该被调用
        $this->innerConnection->expects($this->once())
            ->method('fetchAssociative')
            ->with($sql, $params, $types)
            ->willReturn($expected);

        // 缓存不应该被调用
        $this->cache->expects($this->never())->method('get');

        $result = $this->cacheConnection->fetchAssociative($sql, $params, $types);
        $this->assertSame($expected, $result);
    }

    public function testInsert_invalidatesCacheTag(): void
    {
        $table = 'users';
        $data = ['name' => 'New User'];
        $types = [];

        $this->innerConnection->expects($this->once())
            ->method('insert')
            ->with($table, $data, $types)
            ->willReturn(1);

        $this->cache->expects($this->once())
            ->method('invalidateTags')
            ->with(['users']);

        $result = $this->cacheConnection->insert($table, $data, $types);
        $this->assertSame(1, $result);
    }

    public function testUpdate_invalidatesCacheTagsWithId(): void
    {
        $table = 'users';
        $data = ['name' => 'Updated User'];
        $criteria = ['id' => 1];
        $types = [];

        $this->innerConnection->expects($this->once())
            ->method('update')
            ->with($table, $data, $criteria, $types)
            ->willReturn(1);

        $this->cache->expects($this->once())
            ->method('invalidateTags')
            ->with(['users', 'users_1']);

        $result = $this->cacheConnection->update($table, $data, $criteria, $types);
        $this->assertSame(1, $result);
    }

    public function testDelete_invalidatesCacheTagsWithId(): void
    {
        $table = 'users';
        $criteria = ['id' => 1];
        $types = [];

        $this->innerConnection->expects($this->once())
            ->method('delete')
            ->with($table, $criteria, $types)
            ->willReturn(1);

        $this->cache->expects($this->once())
            ->method('invalidateTags')
            ->with(['users', 'users_1']);

        $result = $this->cacheConnection->delete($table, $criteria, $types);
        $this->assertSame(1, $result);
    }

    public function testIsConnected_delegatesToInnerConnection(): void
    {
        $this->innerConnection->expects($this->once())
            ->method('isConnected')
            ->willReturn(true);

        $this->assertTrue($this->cacheConnection->isConnected());
    }

    public function testIsTransactionActive_delegatesToInnerConnection(): void
    {
        $this->innerConnection->expects($this->once())
            ->method('isTransactionActive')
            ->willReturn(true);

        $this->assertTrue($this->cacheConnection->isTransactionActive());
    }

    public function testClose_delegatesToInnerConnection(): void
    {
        $this->innerConnection->expects($this->once())
            ->method('close');

        $this->cacheConnection->close();
    }

    public function testQuote_delegatesToInnerConnection(): void
    {
        $value = 'test';
        $quoted = "'test'";

        $this->innerConnection->expects($this->once())
            ->method('quote')
            ->with($value)
            ->willReturn($quoted);

        $this->assertSame($quoted, $this->cacheConnection->quote($value));
    }
}
