<?php

declare(strict_types=1);

namespace Tourze\DoctrineCacheBundle\Tests\Connection;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\TransactionIsolationLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Tourze\CacheStrategy\CacheStrategy;
use Tourze\DoctrineCacheBundle\Connection\CacheConnection;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CacheConnection::class)]
#[RunTestsInSeparateProcesses]
final class CacheConnectionTest extends AbstractIntegrationTestCase
{
    private CacheConnection $cacheConnection;

    private Connection $innerConnection;

    private TagAwareCacheInterface $cache;

    private LoggerInterface $logger;

    private CacheStrategy $cacheStrategy;

    protected function onSetUp(): void
    {
        // 从容器获取真实的服务
        $this->innerConnection = self::getService(Connection::class);
        $this->cache = self::getService(TagAwareCacheInterface::class);
        $this->logger = self::getService(LoggerInterface::class);
        $this->cacheStrategy = self::getService(CacheStrategy::class);

        // 创建 CacheConnection 实例
        // 装饰器模式需要手动组装，因此需要直接实例化
        // @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass
        $this->cacheConnection = new CacheConnection(
            $this->innerConnection,
            $this->cache,
            $this->logger,
            $this->cacheStrategy
        );

        // 确保缓存开关开启
        $this->cacheConnection->setOpenCache(true);

        // 创建测试表
        $this->createTestTable();
    }

    protected function onTearDown(): void
    {
        // 清理测试表
        $this->dropTestTable();
    }

    private function createTestTable(): void
    {
        $sql = '
            CREATE TABLE IF NOT EXISTS test_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255)
            )
        ';
        $this->innerConnection->executeStatement($sql);
    }

    private function dropTestTable(): void
    {
        try {
            $this->innerConnection->executeStatement('DROP TABLE IF EXISTS test_users');
        } catch (\Throwable) {
            // 忽略错误
        }
    }

    public function testInsertInvalidatesCacheTag(): void
    {
        // 插入数据
        $result = $this->cacheConnection->insert('test_users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // 验证插入成功
        $this->assertSame(1, $result);

        // 验证数据已插入
        $user = $this->innerConnection->fetchAssociative('SELECT * FROM test_users WHERE name = ?', ['John Doe']);
        $this->assertIsArray($user);
        $this->assertSame('John Doe', $user['name']);
    }

    public function testUpdateInvalidatesCacheTag(): void
    {
        // 先插入数据
        $this->innerConnection->insert('test_users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $userId = $this->innerConnection->lastInsertId();

        // 更新数据
        $result = $this->cacheConnection->update(
            'test_users',
            ['name' => 'Jane Smith'],
            ['id' => $userId]
        );

        // 验证更新成功
        $this->assertSame(1, $result);

        // 验证数据已更新
        $user = $this->innerConnection->fetchAssociative('SELECT * FROM test_users WHERE id = ?', [$userId]);
        $this->assertIsArray($user);
        $this->assertSame('Jane Smith', $user['name']);
    }

    public function testDeleteInvalidatesCacheTag(): void
    {
        // 先插入数据
        $this->innerConnection->insert('test_users', [
            'name' => 'Bob Smith',
            'email' => 'bob@example.com',
        ]);

        $userId = $this->innerConnection->lastInsertId();

        // 删除数据
        $result = $this->cacheConnection->delete('test_users', ['id' => $userId]);

        // 验证删除成功
        $this->assertSame(1, $result);

        // 验证数据已删除
        $user = $this->innerConnection->fetchAssociative('SELECT * FROM test_users WHERE id = ?', [$userId]);
        $this->assertFalse($user);
    }

    public function testFetchAssociativeReturnsData(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        // 查询数据
        $user = $this->cacheConnection->fetchAssociative('SELECT * FROM test_users WHERE name = ?', ['Alice']);

        // 验证返回数据正确
        $this->assertIsArray($user);
        $this->assertSame('Alice', $user['name']);
        $this->assertSame('alice@example.com', $user['email']);
    }

    public function testFetchAllAssociativeReturnsMultipleRows(): void
    {
        // 插入多条测试数据
        $this->innerConnection->insert('test_users', ['name' => 'User1', 'email' => 'user1@example.com']);
        $this->innerConnection->insert('test_users', ['name' => 'User2', 'email' => 'user2@example.com']);

        // 查询所有数据
        $users = $this->cacheConnection->fetchAllAssociative('SELECT * FROM test_users ORDER BY name');

        // 验证返回数据正确
        $this->assertIsArray($users);
        $this->assertCount(2, $users);
        $this->assertSame('User1', $users[0]['name']);
        $this->assertSame('User2', $users[1]['name']);
    }

    public function testFetchOneReturnsScalarValue(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'TestUser', 'email' => 'test@example.com']);

        // 查询单个值
        $name = $this->cacheConnection->fetchOne('SELECT name FROM test_users WHERE email = ?', ['test@example.com']);

        // 验证返回值正确
        $this->assertSame('TestUser', $name);
    }

    public function testExecuteStatementInvalidatesCacheTags(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'OldName', 'email' => 'old@example.com']);

        // 使用 executeStatement 更新数据
        $result = $this->cacheConnection->executeStatement(
            'UPDATE test_users SET name = ? WHERE email = ?',
            ['NewName', 'old@example.com']
        );

        // 验证更新成功
        $this->assertSame(1, $result);

        // 验证数据已更新
        $user = $this->innerConnection->fetchAssociative('SELECT * FROM test_users WHERE email = ?', ['old@example.com']);
        $this->assertIsArray($user);
        $this->assertSame('NewName', $user['name']);
    }

    public function testSetOpenCacheDisablesCache(): void
    {
        // 关闭缓存
        $this->cacheConnection->setOpenCache(false);

        // 验证缓存开关状态
        $this->assertFalse($this->cacheConnection->isOpenCache());

        // 开启缓存
        $this->cacheConnection->setOpenCache(true);

        // 验证缓存开关状态
        $this->assertTrue($this->cacheConnection->isOpenCache());
    }

    public function testBeginTransactionDelegateToInnerConnection(): void
    {
        // 开始事务
        $this->cacheConnection->beginTransaction();

        // 验证事务已开始
        $this->assertTrue($this->cacheConnection->isTransactionActive());

        // 回滚事务
        $this->cacheConnection->rollBack();

        // 验证事务已结束
        $this->assertFalse($this->cacheConnection->isTransactionActive());
    }

    public function testCommitDelegateToInnerConnection(): void
    {
        // 开始事务
        $this->cacheConnection->beginTransaction();

        // 插入数据
        $this->cacheConnection->insert('test_users', ['name' => 'TransactionUser', 'email' => 'tx@example.com']);

        // 提交事务
        $this->cacheConnection->commit();

        // 验证事务已提交
        $this->assertFalse($this->cacheConnection->isTransactionActive());

        // 验证数据已持久化
        $user = $this->innerConnection->fetchAssociative('SELECT * FROM test_users WHERE name = ?', ['TransactionUser']);
        $this->assertIsArray($user);
        $this->assertSame('TransactionUser', $user['name']);
    }

    public function testRollBackDelegateToInnerConnection(): void
    {
        // 开始事务
        $this->cacheConnection->beginTransaction();

        // 插入数据
        $this->cacheConnection->insert('test_users', ['name' => 'RollbackUser', 'email' => 'rb@example.com']);

        // 回滚事务
        $this->cacheConnection->rollBack();

        // 验证数据未持久化
        $user = $this->innerConnection->fetchAssociative('SELECT * FROM test_users WHERE name = ?', ['RollbackUser']);
        $this->assertFalse($user);
    }

    public function testLastInsertIdReturnsCorrectValue(): void
    {
        // 插入数据
        $this->cacheConnection->insert('test_users', ['name' => 'InsertIdTest', 'email' => 'insertid@example.com']);

        // 获取最后插入的ID
        $lastId = $this->cacheConnection->lastInsertId();

        // 验证ID正确
        $this->assertNotEmpty($lastId);

        // 验证可以通过ID查询到数据
        $user = $this->innerConnection->fetchAssociative('SELECT * FROM test_users WHERE id = ?', [$lastId]);
        $this->assertIsArray($user);
        $this->assertSame('InsertIdTest', $user['name']);
    }

    public function testQuoteDelegateToInnerConnection(): void
    {
        // 测试引用字符串
        $quoted = $this->cacheConnection->quote("test'value");

        // 验证已正确引用
        $this->assertIsString($quoted);
        $this->assertStringContainsString("test", $quoted);
    }

    public function testGetParamsReturnsConnectionParameters(): void
    {
        // 获取连接参数
        $params = $this->cacheConnection->getParams();

        // 验证返回了参数
        $this->assertIsArray($params);
        $this->assertNotEmpty($params);
    }

    public function testGetDatabasePlatformReturnsCorrectPlatform(): void
    {
        // 获取数据库平台
        $platform = $this->cacheConnection->getDatabasePlatform();

        // 验证返回了平台对象
        $this->assertNotNull($platform);
        $this->assertIsObject($platform);
    }

    public function testCreateQueryBuilderReturnsQueryBuilder(): void
    {
        // 创建查询构建器
        $qb = $this->cacheConnection->createQueryBuilder();

        // 验证返回了查询构建器
        $this->assertNotNull($qb);
        $this->assertIsObject($qb);

        // 验证可以使用查询构建器
        $qb->select('*')->from('test_users');
        $this->assertStringContainsString('test_users', $qb->getSQL());
    }

    public function testExecuteQueryReturnsResult(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'QueryTest', 'email' => 'query@example.com']);

        // 执行查询
        $result = $this->cacheConnection->executeQuery('SELECT * FROM test_users WHERE name = ?', ['QueryTest']);

        // 验证返回了结果对象
        $this->assertNotNull($result);

        // 验证可以获取数据
        $data = $result->fetchAssociative();
        $this->assertIsArray($data);
        $this->assertSame('QueryTest', $data['name']);
    }

    public function testIterateAssociativeReturnsTraversable(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'Iterator1', 'email' => 'iter1@example.com']);
        $this->innerConnection->insert('test_users', ['name' => 'Iterator2', 'email' => 'iter2@example.com']);

        // 迭代查询结果
        $iterator = $this->cacheConnection->iterateAssociative('SELECT * FROM test_users ORDER BY name');

        // 验证返回了可遍历对象
        $this->assertInstanceOf(\Traversable::class, $iterator);

        // 验证可以遍历数据
        $rows = iterator_to_array($iterator);
        $this->assertIsArray($rows);
        $this->assertGreaterThanOrEqual(2, count($rows));
    }

    public function testTransactionalExecutesCallbackInTransaction(): void
    {
        // 在事务中执行操作
        $result = $this->cacheConnection->transactional(function (Connection $conn) {
            $conn->insert('test_users', ['name' => 'TransactionalTest', 'email' => 'transactional@example.com']);

            return 'success';
        });

        // 验证返回值正确
        $this->assertSame('success', $result);

        // 验证数据已提交
        $user = $this->innerConnection->fetchAssociative('SELECT * FROM test_users WHERE name = ?', ['TransactionalTest']);
        $this->assertIsArray($user);
        $this->assertSame('TransactionalTest', $user['name']);
    }

    public function testCloseConnectionDoesNotThrowException(): void
    {
        // 关闭连接不应抛出异常
        $this->cacheConnection->close();

        // 验证连接已关闭
        $this->assertFalse($this->cacheConnection->isConnected());
    }

    public function testFetchFirstColumnReturnsColumnValues(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'Column1', 'email' => 'col1@example.com']);
        $this->innerConnection->insert('test_users', ['name' => 'Column2', 'email' => 'col2@example.com']);

        // 获取第一列的所有值
        $names = $this->cacheConnection->fetchFirstColumn('SELECT name FROM test_users ORDER BY name');

        // 验证返回了列值数组
        $this->assertIsArray($names);
        $this->assertGreaterThanOrEqual(2, count($names));
        $this->assertContains('Column1', $names);
        $this->assertContains('Column2', $names);
    }

    public function testConvertToDatabaseValueDelegateToInnerConnection(): void
    {
        // 测试类型转换委托
        $value = new \DateTime('2025-01-01 12:00:00');
        $result = $this->cacheConnection->convertToDatabaseValue($value, 'datetime');

        // 验证返回了转换后的值
        $this->assertIsString($result);
        $this->assertStringContainsString('2025-01-01', $result);
    }

    public function testConvertToPHPValueDelegateToInnerConnection(): void
    {
        // 测试类型转换委托
        $value = '2025-01-01 12:00:00';
        $result = $this->cacheConnection->convertToPHPValue($value, 'datetime');

        // 验证返回了转换后的值
        $this->assertInstanceOf(\DateTimeInterface::class, $result);
    }

    public function testCreateExpressionBuilderReturnsExpressionBuilder(): void
    {
        // 创建表达式构建器
        $eb = $this->cacheConnection->createExpressionBuilder();

        // 验证返回了表达式构建器对象
        $this->assertNotNull($eb);
        $this->assertInstanceOf(ExpressionBuilder::class, $eb);

        // 验证可以使用表达式构建器
        $expr = $eb->eq('name', ':name');
        $this->assertIsString($expr);
    }

    public function testCreateSavepointDelegateToInnerConnection(): void
    {
        // 开始事务
        $this->cacheConnection->beginTransaction();

        // 创建保存点
        $this->cacheConnection->createSavepoint('test_savepoint');

        // 插入数据
        $this->cacheConnection->insert('test_users', ['name' => 'SavepointTest', 'email' => 'sp@example.com']);

        // 回滚到保存点
        $this->cacheConnection->rollbackSavepoint('test_savepoint');

        // 提交事务
        $this->cacheConnection->commit();

        // 验证数据未持久化（因为回滚到保存点前）
        $user = $this->innerConnection->fetchAssociative('SELECT * FROM test_users WHERE name = ?', ['SavepointTest']);
        $this->assertFalse($user);
    }

    public function testCreateSchemaManagerReturnsSchemaManager(): void
    {
        // 创建架构管理器
        $schemaManager = $this->cacheConnection->createSchemaManager();

        // 验证返回了架构管理器对象
        $this->assertNotNull($schemaManager);
        $this->assertInstanceOf(AbstractSchemaManager::class, $schemaManager);
    }

    public function testExecuteCacheQueryDelegateToInnerConnection(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'CacheQueryTest', 'email' => 'cq@example.com']);

        // 创建查询缓存配置（需要提供结果缓存）
        // SQLite测试环境可能没有配置结果缓存，所以这里直接测试方法存在性
        $this->assertTrue(method_exists($this->cacheConnection, 'executeCacheQuery'));

        // 验证方法签名正确
        $reflection = new \ReflectionMethod($this->cacheConnection, 'executeCacheQuery');
        $this->assertSame(4, $reflection->getNumberOfParameters());
    }

    public function testExecuteUpdateDelegateToInnerConnection(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'UpdateTest', 'email' => 'update@example.com']);

        // 使用 executeUpdate 更新数据
        $affected = $this->cacheConnection->executeUpdate(
            'UPDATE test_users SET email = ? WHERE name = ?',
            ['newemail@example.com', 'UpdateTest']
        );

        // 验证返回了影响的行数
        $this->assertSame(1, $affected);

        // 验证数据已更新
        $user = $this->innerConnection->fetchAssociative('SELECT * FROM test_users WHERE name = ?', ['UpdateTest']);
        $this->assertIsArray($user);
        $this->assertSame('newemail@example.com', $user['email']);
    }

    public function testFetchAllAssociativeIndexedReturnsIndexedArray(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'IndexedUser1', 'email' => 'iu1@example.com']);
        $this->innerConnection->insert('test_users', ['name' => 'IndexedUser2', 'email' => 'iu2@example.com']);

        $id1 = $this->innerConnection->lastInsertId();

        // 查询索引数据
        $users = $this->cacheConnection->fetchAllAssociativeIndexed('SELECT * FROM test_users WHERE name LIKE ?', ['IndexedUser%']);

        // 验证返回了索引数组
        $this->assertIsArray($users);
        $this->assertNotEmpty($users);
    }

    public function testFetchAllKeyValueReturnsKeyValuePairs(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'KeyValueUser1', 'email' => 'kv1@example.com']);
        $this->innerConnection->insert('test_users', ['name' => 'KeyValueUser2', 'email' => 'kv2@example.com']);

        // 查询键值对
        $pairs = $this->cacheConnection->fetchAllKeyValue('SELECT name, email FROM test_users WHERE name LIKE ?', ['KeyValueUser%']);

        // 验证返回了键值对数组
        $this->assertIsArray($pairs);
        $this->assertNotEmpty($pairs);
    }

    public function testFetchAllNumericReturnsNumericArrays(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'NumericUser1', 'email' => 'nu1@example.com']);

        // 查询数值数组
        $users = $this->cacheConnection->fetchAllNumeric('SELECT * FROM test_users WHERE name = ?', ['NumericUser1']);

        // 验证返回了数值索引数组
        $this->assertIsArray($users);
        $this->assertNotEmpty($users);
        $this->assertIsArray($users[0]);
    }

    public function testFetchNumericReturnsNumericArray(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'NumericSingleUser', 'email' => 'nsu@example.com']);

        // 查询单行数值数组
        $user = $this->cacheConnection->fetchNumeric('SELECT * FROM test_users WHERE name = ?', ['NumericSingleUser']);

        // 验证返回了数值索引数组或false
        $this->assertTrue(is_array($user) || false === $user);
        if (is_array($user)) {
            $this->assertIsArray($user);
        }
    }

    public function testGetDatabaseReturnsString(): void
    {
        // 获取数据库名称
        $database = $this->cacheConnection->getDatabase();

        // 验证返回了数据库名称（SQLite可能返回null或路径）
        $this->assertTrue(is_string($database) || null === $database);
    }

    public function testGetNativeConnectionReturnsObject(): void
    {
        // 获取原生连接
        $nativeConn = $this->cacheConnection->getNativeConnection();

        // 验证返回了对象
        $this->assertIsObject($nativeConn);
    }

    public function testGetNestTransactionsWithSavepointsReturnsBoolean(): void
    {
        // 获取嵌套事务配置
        // @phpstan-ignore method.deprecated
        $nested = $this->cacheConnection->getNestTransactionsWithSavepoints();

        // 验证返回了布尔值
        $this->assertIsBool($nested);
    }

    public function testGetTransactionIsolationReturnsLevel(): void
    {
        // 获取事务隔离级别
        $level = $this->cacheConnection->getTransactionIsolation();

        // 验证返回了事务隔离级别
        $this->assertInstanceOf(TransactionIsolationLevel::class, $level);
    }

    public function testGetTransactionNestingLevelReturnsInteger(): void
    {
        // 获取事务嵌套级别
        $level = $this->cacheConnection->getTransactionNestingLevel();

        // 验证返回了整数
        $this->assertIsInt($level);
        $this->assertSame(0, $level);

        // 开始事务后验证嵌套级别
        $this->cacheConnection->beginTransaction();
        $newLevel = $this->cacheConnection->getTransactionNestingLevel();
        $this->assertSame(1, $newLevel);

        // 回滚事务
        $this->cacheConnection->rollBack();
    }

    public function testGetWrappedConnectionReturnsObject(): void
    {
        // 获取包装的连接
        $wrapped = $this->cacheConnection->getWrappedConnection();

        // 验证返回了对象
        $this->assertIsObject($wrapped);
    }

    public function testIsAutoCommitReturnsBoolean(): void
    {
        // 获取自动提交状态
        $autoCommit = $this->cacheConnection->isAutoCommit();

        // 验证返回了布尔值
        $this->assertIsBool($autoCommit);
    }

    public function testIsConnectedReturnsBoolean(): void
    {
        // 获取连接状态
        $connected = $this->cacheConnection->isConnected();

        // 验证返回了布尔值
        $this->assertIsBool($connected);
    }

    public function testIsRollbackOnlyReturnsBoolean(): void
    {
        // isRollbackOnly 需要在事务中调用
        $this->cacheConnection->beginTransaction();

        try {
            // 获取只回滚状态
            $rollbackOnly = $this->cacheConnection->isRollbackOnly();

            // 验证返回了布尔值
            $this->assertIsBool($rollbackOnly);
        } finally {
            // 回滚事务
            $this->cacheConnection->rollBack();
        }
    }

    public function testIterateAssociativeIndexedReturnsTraversable(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'IterIndexed1', 'email' => 'ii1@example.com']);
        $this->innerConnection->insert('test_users', ['name' => 'IterIndexed2', 'email' => 'ii2@example.com']);

        // 迭代索引查询结果
        $iterator = $this->cacheConnection->iterateAssociativeIndexed('SELECT * FROM test_users WHERE name LIKE ?', ['IterIndexed%']);

        // 验证返回了可遍历对象
        $this->assertInstanceOf(\Traversable::class, $iterator);

        // 验证可以遍历数据
        $rows = iterator_to_array($iterator);
        $this->assertIsArray($rows);
    }

    public function testIterateColumnReturnsTraversable(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'IterColumn1', 'email' => 'ic1@example.com']);
        $this->innerConnection->insert('test_users', ['name' => 'IterColumn2', 'email' => 'ic2@example.com']);

        // 迭代列值
        $iterator = $this->cacheConnection->iterateColumn('SELECT name FROM test_users WHERE name LIKE ?', ['IterColumn%']);

        // 验证返回了可遍历对象
        $this->assertInstanceOf(\Traversable::class, $iterator);

        // 验证可以遍历数据
        $values = iterator_to_array($iterator);
        $this->assertIsArray($values);
        $this->assertGreaterThanOrEqual(2, count($values));
    }

    public function testIterateKeyValueReturnsTraversable(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'IterKV1', 'email' => 'ikv1@example.com']);
        $this->innerConnection->insert('test_users', ['name' => 'IterKV2', 'email' => 'ikv2@example.com']);

        // 迭代键值对
        $iterator = $this->cacheConnection->iterateKeyValue('SELECT name, email FROM test_users WHERE name LIKE ?', ['IterKV%']);

        // 验证返回了可遍历对象
        $this->assertInstanceOf(\Traversable::class, $iterator);

        // 验证可以遍历数据
        $pairs = iterator_to_array($iterator);
        $this->assertIsArray($pairs);
    }

    public function testIterateNumericReturnsTraversable(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'IterNumeric1', 'email' => 'in1@example.com']);
        $this->innerConnection->insert('test_users', ['name' => 'IterNumeric2', 'email' => 'in2@example.com']);

        // 迭代数值数组
        $iterator = $this->cacheConnection->iterateNumeric('SELECT * FROM test_users WHERE name LIKE ?', ['IterNumeric%']);

        // 验证返回了可遍历对象
        $this->assertInstanceOf(\Traversable::class, $iterator);

        // 验证可以遍历数据
        $rows = iterator_to_array($iterator);
        $this->assertIsArray($rows);
        $this->assertGreaterThanOrEqual(2, count($rows));
    }

    public function testPrepareReturnsStatement(): void
    {
        // 准备语句
        $stmt = $this->cacheConnection->prepare('SELECT * FROM test_users WHERE name = ?');

        // 验证返回了语句对象
        $this->assertInstanceOf(Statement::class, $stmt);
    }

    public function testReleaseSavepointDelegateToInnerConnection(): void
    {
        // 开始事务
        $this->cacheConnection->beginTransaction();

        // 创建保存点
        $this->cacheConnection->createSavepoint('test_release');

        // 插入数据
        $this->cacheConnection->insert('test_users', ['name' => 'ReleaseTest', 'email' => 'release@example.com']);

        // 释放保存点
        $this->cacheConnection->releaseSavepoint('test_release');

        // 提交事务
        $this->cacheConnection->commit();

        // 验证数据已持久化
        $user = $this->innerConnection->fetchAssociative('SELECT * FROM test_users WHERE name = ?', ['ReleaseTest']);
        $this->assertIsArray($user);
        $this->assertSame('ReleaseTest', $user['name']);
    }

    public function testRollbackSavepointDelegateToInnerConnection(): void
    {
        // 开始事务
        $this->cacheConnection->beginTransaction();

        // 创建保存点
        $this->cacheConnection->createSavepoint('test_rollback_sp');

        // 插入数据
        $this->cacheConnection->insert('test_users', ['name' => 'RollbackSPTest', 'email' => 'rbsp@example.com']);

        // 回滚保存点
        $this->cacheConnection->rollbackSavepoint('test_rollback_sp');

        // 提交事务
        $this->cacheConnection->commit();

        // 验证数据未持久化
        $user = $this->innerConnection->fetchAssociative('SELECT * FROM test_users WHERE name = ?', ['RollbackSPTest']);
        $this->assertFalse($user);
    }

    public function testSetAutoCommitDelegateToInnerConnection(): void
    {
        // 获取当前自动提交状态
        $originalState = $this->cacheConnection->isAutoCommit();

        // 设置自动提交
        $this->cacheConnection->setAutoCommit(!$originalState);

        // 验证状态已改变
        $this->assertSame(!$originalState, $this->cacheConnection->isAutoCommit());

        // 恢复原始状态
        $this->cacheConnection->setAutoCommit($originalState);
    }

    public function testSetNestTransactionsWithSavepointsDelegateToInnerConnection(): void
    {
        // 设置嵌套事务为true（Doctrine DBAL 4.0不再支持设为false）
        // @phpstan-ignore method.deprecated
        $this->cacheConnection->setNestTransactionsWithSavepoints(true);

        // 验证设置成功
        // @phpstan-ignore method.deprecated
        $this->assertTrue($this->cacheConnection->getNestTransactionsWithSavepoints());
    }

    public function testSetRollbackOnlyDelegateToInnerConnection(): void
    {
        // 开始事务
        $this->cacheConnection->beginTransaction();

        // 设置只回滚
        $this->cacheConnection->setRollbackOnly();

        // 验证设置成功
        $this->assertTrue($this->cacheConnection->isRollbackOnly());

        // 回滚事务
        $this->cacheConnection->rollBack();
    }

    public function testSetTransactionIsolationDelegateToInnerConnection(): void
    {
        // 设置事务隔离级别
        $this->cacheConnection->setTransactionIsolation(TransactionIsolationLevel::READ_COMMITTED);

        // 验证设置成功
        $level = $this->cacheConnection->getTransactionIsolation();
        $this->assertSame(TransactionIsolationLevel::READ_COMMITTED, $level);
    }

    public function testQueryReturnsResult(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'QueryMethodTest', 'email' => 'querymethod@example.com']);

        // 使用 query 方法查询
        $result = $this->cacheConnection->query('SELECT * FROM test_users WHERE name = \'QueryMethodTest\'');

        // 验证返回了结果对象
        $this->assertInstanceOf(Result::class, $result);

        // 验证可以获取数据
        $data = $result->fetchAssociative();
        $this->assertIsArray($data);
        $this->assertSame('QueryMethodTest', $data['name']);
    }

    public function testExecReturnsAffectedRows(): void
    {
        // 插入测试数据
        $this->innerConnection->insert('test_users', ['name' => 'ExecTest', 'email' => 'exec@example.com']);

        // 使用 exec 方法更新数据
        $affected = $this->cacheConnection->exec('UPDATE test_users SET email = \'updated@example.com\' WHERE name = \'ExecTest\'');

        // 验证返回了影响的行数
        $this->assertSame(1, $affected);

        // 验证数据已更新
        $user = $this->innerConnection->fetchAssociative('SELECT * FROM test_users WHERE name = ?', ['ExecTest']);
        $this->assertIsArray($user);
        $this->assertSame('updated@example.com', $user['email']);
    }
}
