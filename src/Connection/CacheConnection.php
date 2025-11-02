<?php

declare(strict_types=1);

namespace Tourze\DoctrineCacheBundle\Connection;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Type;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Tourze\CacheStrategy\CacheStrategy;
use Tourze\DoctrineCacheBundle\Result\ArrayResult;

/**
 * 对指定的连接进行一层包装
 * 目的是为了根据表名做一层缓存处理
 * 在这里，我们主要是写缓存，清除缓存通过订阅doctrine事件来做。
 * 之所以要分开，是因为需要考虑事务情景。
 */
class CacheConnection extends Connection
{
    private bool $openCache = true;

    // @phpstan-ignore-next-line
    public function __construct(
        private readonly Connection $inner,
        private readonly TagAwareCacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly CacheStrategy $cacheStrategy,
    ) {
        parent::__construct($this->inner->getParams(), $this->inner->getDriver(), $this->inner->getConfiguration());
    }

    public function isOpenCache(): bool
    {
        return $this->openCache;
    }

    public function setOpenCache(bool $openCache): void
    {
        $this->openCache = $openCache;
    }

    public function getParams(): array
    {
        return $this->inner->getParams();
    }

    public function getDatabase(): ?string
    {
        return $this->inner->getDatabase();
    }

    public function getDriver(): Driver
    {
        return $this->inner->getDriver();
    }

    public function getConfiguration(): Configuration
    {
        return $this->inner->getConfiguration();
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->inner->getDatabasePlatform();
    }

    public function createExpressionBuilder(): ExpressionBuilder
    {
        return $this->inner->createExpressionBuilder();
    }

    protected function connect(): DriverConnection
    {
        return $this->inner->connect();
    }

    public function isAutoCommit(): bool
    {
        return $this->inner->isAutoCommit();
    }

    public function setAutoCommit(bool $autoCommit): void
    {
        $this->inner->setAutoCommit($autoCommit);
    }

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        return $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => $this->inner->fetchAssociative($query, $params, $types),
        );
    }

    public function fetchNumeric(string $query, array $params = [], array $types = []): array|false
    {
        return $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => $this->inner->fetchNumeric($query, $params, $types),
        );
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        return $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => $this->inner->fetchOne($query, $params, $types),
        );
    }

    public function isConnected(): bool
    {
        return $this->inner->isConnected();
    }

    public function isTransactionActive(): bool
    {
        return $this->inner->isTransactionActive();
    }

    public function delete(string $table, array $criteria = [], array $types = []): int|string
    {
        try {
            return $this->inner->delete($table, $criteria, $types);
        } finally {
            $tableTag = self::filterVar($table);
            $tags = [
                $tableTag,
                $tableTag . '_' . (is_scalar($criteria['id'] ?? '') ? (string) ($criteria['id'] ?? '') : ''),
            ];
            $this->cache->invalidateTags($tags);
        }
    }

    public function close(): void
    {
        $this->inner->close();
    }

    public function setTransactionIsolation(TransactionIsolationLevel $level): void
    {
        $this->inner->setTransactionIsolation($level);
    }

    public function getTransactionIsolation(): TransactionIsolationLevel
    {
        return $this->inner->getTransactionIsolation();
    }

    public function update(string $table, array $data, array $criteria = [], array $types = []): int|string
    {
        try {
            return $this->inner->update($table, $data, $criteria, $types);
        } finally {
            $tableTag = self::filterVar($table);
            $tags = [
                $tableTag,
                $tableTag . '_' . (is_scalar($criteria['id'] ?? '') ? (string) ($criteria['id'] ?? '') : ''),
            ];
            $this->cache->invalidateTags($tags);
        }
    }

    public function insert(string $table, array $data, array $types = []): int|string
    {
        try {
            return $this->inner->insert($table, $data, $types);
        } finally {
            $this->cache->invalidateTags([self::filterVar($table)]);
        }
    }

    public function quote(string $value): string
    {
        return $this->inner->quote($value);
    }

    public function fetchAllNumeric(string $query, array $params = [], array $types = []): array
    {
        return $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => $this->inner->fetchAllNumeric($query, $params, $types),
        );
    }

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        return $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => $this->inner->fetchAllAssociative($query, $params, $types),
        );
    }

    public function fetchAllKeyValue(string $query, array $params = [], array $types = []): array
    {
        return $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => $this->inner->fetchAllKeyValue($query, $params, $types),
        );
    }

    public function fetchAllAssociativeIndexed(string $query, array $params = [], array $types = []): array
    {
        return $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => $this->inner->fetchAllAssociativeIndexed($query, $params, $types),
        );
    }

    public function fetchFirstColumn(string $query, array $params = [], array $types = []): array
    {
        return $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => $this->inner->fetchFirstColumn($query, $params, $types),
        );
    }

    public function iterateNumeric(string $query, array $params = [], array $types = []): \Traversable
    {
        $rows = $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => iterator_to_array($this->inner->iterateNumeric($query, $params, $types)),
        );

        assert(is_array($rows));

        return self::generateRowsTraversable($rows);
    }

    public function iterateAssociative(string $query, array $params = [], array $types = []): \Traversable
    {
        $rows = $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => iterator_to_array($this->inner->iterateAssociative($query, $params, $types)),
        );

        assert(is_array($rows));

        return self::generateRowsTraversable($rows);
    }

    public function iterateKeyValue(string $query, array $params = [], array $types = []): \Traversable
    {
        $rows = $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => iterator_to_array($this->inner->iterateKeyValue($query, $params, $types)),
        );

        assert(is_array($rows));

        return self::generateRowsTraversable($rows);
    }

    public function iterateAssociativeIndexed(string $query, array $params = [], array $types = []): \Traversable
    {
        $rows = $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => iterator_to_array($this->inner->iterateAssociativeIndexed($query, $params, $types)),
        );

        assert(is_array($rows));

        return self::generateRowsTraversable($rows);
    }

    public function iterateColumn(string $query, array $params = [], array $types = []): \Traversable
    {
        $rows = $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => iterator_to_array($this->inner->iterateColumn($query, $params, $types)),
        );

        assert(is_array($rows));

        return self::generateRowsTraversable($rows);
    }

    public function prepare(string $sql): Statement
    {
        return $this->inner->prepare($sql);
    }

    public function executeQuery(
        string $sql,
        array $params = [],
        $types = [],
        ?QueryCacheProfile $qcp = null,
    ): Result {
        // 如果有自己的缓存策略，则直接调用上层算了
        if (null !== $qcp) {
            return $this->inner->executeQuery($sql, $params, $types, $qcp);
        }

        $data = $this->callCache(
            __FUNCTION__,
            $sql,
            [$params, $types],
            function () use ($sql, $params, $types) {
                $result = $this->inner->executeQuery($sql, $params, $types);

                return $result->fetchAllAssociative();
            },
        );

        return new Result(new ArrayResult($data), $this);
    }

    public function executeCacheQuery(string $sql, array $params, array $types, QueryCacheProfile $qcp): Result
    {
        return $this->inner->executeCacheQuery($sql, $params, $types, $qcp);
    }

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        try {
            return $this->inner->executeStatement($sql, $params, $types);
        } finally {
            $this->cache->invalidateTags(self::extractCacheTags($sql, $params));
        }
    }

    public function getTransactionNestingLevel(): int
    {
        return $this->inner->getTransactionNestingLevel();
    }

    public function lastInsertId(): int|string
    {
        return $this->inner->lastInsertId();
    }

    public function transactional(\Closure $func): mixed
    {
        return $this->inner->transactional($func);
    }

    public function setNestTransactionsWithSavepoints(bool $nestTransactionsWithSavepoints): void
    {
        $this->inner->setNestTransactionsWithSavepoints($nestTransactionsWithSavepoints);
    }

    public function getNestTransactionsWithSavepoints(): bool
    {
        return $this->inner->getNestTransactionsWithSavepoints();
    }

    public function beginTransaction(): void
    {
        $this->inner->beginTransaction();
    }

    public function commit(): void
    {
        $this->inner->commit();
    }

    public function rollBack(): void
    {
        $this->inner->rollBack();
    }

    public function createSavepoint(string $savepoint): void
    {
        $this->inner->createSavepoint($savepoint);
    }

    public function releaseSavepoint(string $savepoint): void
    {
        $this->inner->releaseSavepoint($savepoint);
    }

    public function rollbackSavepoint(string $savepoint): void
    {
        $this->inner->rollbackSavepoint($savepoint);
    }

    public function getWrappedConnection(): object
    {
        // getWrappedConnection() was removed in DBAL 4.0
        // Use getNativeConnection() instead
        $connection = $this->inner->getNativeConnection();

        return is_object($connection) ? $connection : (object) [];
    }

    public function getNativeConnection(): object
    {
        $connection = $this->inner->getNativeConnection();

        return is_object($connection) ? $connection : (object) [];
    }

    /**
     * @return AbstractSchemaManager<AbstractPlatform>
     */
    public function createSchemaManager(): AbstractSchemaManager
    {
        return $this->inner->createSchemaManager();
    }

    public function setRollbackOnly(): void
    {
        $this->inner->setRollbackOnly();
    }

    public function isRollbackOnly(): bool
    {
        return $this->inner->isRollbackOnly();
    }

    public function convertToDatabaseValue(mixed $value, string $type): mixed
    {
        return $this->inner->convertToDatabaseValue($value, $type);
    }

    public function convertToPHPValue(mixed $value, string $type): mixed
    {
        return $this->inner->convertToPHPValue($value, $type);
    }

    public function createQueryBuilder(): QueryBuilder
    {
        return $this->inner->createQueryBuilder();
    }

    /**
     * @param array<mixed> $params
     * @param array<int<0, max>|string, ArrayParameterType|ParameterType|Type|string> $types
     */
    public function executeUpdate(string $sql, array $params = [], array $types = []): int
    {
        $result = $this->executeStatement($sql, $params, $types);

        return is_int($result) ? $result : (int) $result;
    }

    public function query(string $sql): Result
    {
        return $this->executeQuery($sql);
    }

    public function exec(string $sql): int
    {
        $result = $this->executeStatement($sql);

        return is_int($result) ? $result : (int) $result;
    }

    /**
     * 生成缓存KEY
     */
    /**
     * @param array<mixed> $params
     */
    private function buildCacheKey(string $func, string $sql, array $params): string
    {
        return "sql_{$func}_" . md5(serialize([
            $sql,
            $params,
        ]));
    }

    private static function isRealTable(string $table): bool
    {
        if (str_starts_with($table, '#')) {
            return false;
        }

        return true;
    }

    /**
     * 拆分出有效的缓存标签
     */
    /**
     * @param array<mixed> $params
     * @return array<string>
     */
    private static function extractCacheTags(string $sql, array $params): array
    {
        $patterns = [
            '@ FROM (.*?) @',
            '@ JOIN (.*?) @',
            '@UPDATE (.*?) @',
            '@INSERT INTO (.*?) @',
            '@DELETE FROM (.*?) @',
        ];

        $result = [];
        foreach ($patterns as $pattern) {
            $result = array_merge($result, self::extractTablesFromPattern($sql, $pattern));
        }

        return array_values(array_unique($result));
    }

    /**
     * 从指定模式中提取表名
     */
    /**
     * @return array<string>
     */
    private static function extractTablesFromPattern(string $sql, string $pattern): array
    {
        $tables = [];
        preg_match_all($pattern, $sql, $matches);

        foreach ($matches[1] as $str) {
            if (self::isRealTable($str)) {
                $tables[] = trim(self::filterVar($str));
            }
        }

        return $tables;
    }

    /**
     * 过滤特殊字符
     */
    private static function filterVar(string $str): string
    {
        return str_replace(['`', '"', "'", "\t", "\r", "\n"], '', $str);
    }

    /**
     * 执行，并尝试读取缓存
     */
    /**
     * @param array<mixed> $params
     */
    /**
     * 通用缓存调用器（使用 PHPDoc 泛型让返回类型与回调一致）
     *
     * @template T
     * @param array<mixed> $params
     * @param callable():T $callback 回调需返回 T
     * @return T 返回与回调一致的类型 T
     */
    private function callCache(string $func, string $query, array $params, callable $callback): mixed
    {
        if (!$this->shouldUseCache($query, $params)) {
            // 直接返回回调结果（T）
            /** @var T $result */
            $result = $callback();

            return $result;
        }

        $cacheKey = $this->buildCacheKey($func, $query, $params);
        $tags = self::extractCacheTags($query, $params);

        if (!$this->isOpenCache()) {
            $this->handleCacheDisabled($cacheKey);

            /** @var T $result */
            $result = $callback();

            return $result;
        }

        /** @var T $cached */
        $cached = $this->cache->get($cacheKey, function (ItemInterface $item) use ($func, $query, $params, $tags, $callback) {
            $this->configureCacheItem($item, $tags);

            /** @var T $computed */
            $computed = $this->executeWithLogging($func, $query, $params, $tags, $callback);

            return $computed;
        });

        return $cached;
    }

    /**
     * 检查是否应该使用缓存
     */
    /**
     * @param array<mixed> $params
     */
    private function shouldUseCache(string $query, array $params): bool
    {
        if (!(bool) ($_ENV['DOCTRINE_CACHE_TABLE_SWITCH'] ?? true)) {
            return false;
        }

        return $this->cacheStrategy->shouldCache($query, $params);
    }

    /**
     * 处理缓存被禁用的情况
     */
    private function handleCacheDisabled(string $cacheKey): void
    {
        try {
            $this->cache->delete($cacheKey);
        } catch (\Throwable $exception) {
            $this->logger->warning('数据库连接主动清除缓存失败', ['exception' => $exception]);
        }
    }

    /**
     * 配置缓存项
     */
    /**
     * @param array<string> $tags
     */
    private function configureCacheItem(ItemInterface $item, array $tags): void
    {
        $item->tag($tags);
        $duration = $this->calculateCacheDuration($tags);
        $item->expiresAfter($duration);
    }

    /**
     * 计算缓存持续时间
     */
    /**
     * @param array<string> $tags
     */
    private function calculateCacheDuration(array $tags): int
    {
        $tagDuration = $this->findTagSpecificDuration($tags);
        if (null !== $tagDuration) {
            return $tagDuration;
        }

        return $this->getGlobalCacheDuration();
    }

    /**
     * @param array<string> $tags
     */
    private function findTagSpecificDuration(array $tags): ?int
    {
        foreach ($tags as $tag) {
            if (null === $tag) {
                continue;
            }

            $duration = $this->getTagDurationFromEnv($tag);
            if (null !== $duration) {
                return $duration;
            }
        }

        return null;
    }

    private function getTagDurationFromEnv(string $tag): ?int
    {
        $duration = $_ENV["DOCTRINE_CACHE_TABLE_DURATION_{$tag}"] ?? null;
        if (null === $duration) {
            return null;
        }

        return is_numeric($duration) ? (int) $duration : 0;
    }

    private function getGlobalCacheDuration(): int
    {
        $globalDuration = $_ENV['DOCTRINE_GLOBAL_CACHE_TABLE_DURATION'] ?? 60 * 60 * 24;

        return is_numeric($globalDuration) ? (int) $globalDuration : 60 * 60 * 24;
    }

    /**
     * 执行回调并记录日志
     */
    /**
     * @param array<mixed> $params
     * @param array<string> $tags
     */
    private function executeWithLogging(string $func, string $query, array $params, array $tags, callable $callback): mixed
    {
        try {
            return $callback();
        } finally {
            $this->logger->debug('写入数据库缓存', [
                'func' => $func,
                'tags' => $tags,
                'duration' => $this->calculateCacheDuration($tags),
                'query' => $query,
                'params' => $params,
            ]);
        }
    }

    /**
     * @param array<mixed> $rows
     * @return \Traversable<mixed>
     */
    /**
     * @template T
     * @param array<T> $rows
     * @return \Traversable<int, T>
     */
    private static function generateRowsTraversable(array $rows): \Traversable
    {
        while ([] !== $rows) {
            $row = array_shift($rows);
            yield $row;
        }
    }
}
