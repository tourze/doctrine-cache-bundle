<?php

namespace Tourze\DoctrineCacheBundle\Connection;

use Closure;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\TransactionIsolationLevel;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
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

    public function __construct(
        private readonly Connection $inner,
        private readonly CacheInterface $cache,
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

    public function setAutoCommit($autoCommit): void
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
            $tableTag = static::filterVar($table);
            $tags = [
                $tableTag,
                $tableTag . '_' . ($criteria['id'] ?? ''),
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
            $tableTag = static::filterVar($table);
            $tags = [
                $tableTag,
                $tableTag . '_' . ($criteria['id'] ?? ''),
            ];
            $this->cache->invalidateTags($tags);
        }
    }

    public function insert($table, array $data, array $types = []): int|string
    {
        try {
            return $this->inner->insert($table, $data, $types);
        } finally {
            $this->cache->invalidateTags([static::filterVar($table)]);
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

        return static::generateRowsTraversable($rows);
    }

    public function iterateAssociative(string $query, array $params = [], array $types = []): \Traversable
    {
        $rows = $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => iterator_to_array($this->inner->iterateAssociative($query, $params, $types)),
        );

        return static::generateRowsTraversable($rows);
    }

    public function iterateKeyValue(string $query, array $params = [], array $types = []): \Traversable
    {
        $rows = $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => iterator_to_array($this->inner->iterateKeyValue($query, $params, $types)),
        );

        return static::generateRowsTraversable($rows);
    }

    public function iterateAssociativeIndexed(string $query, array $params = [], array $types = []): \Traversable
    {
        $rows = $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => iterator_to_array($this->inner->iterateAssociativeIndexed($query, $params, $types)),
        );

        return static::generateRowsTraversable($rows);
    }

    public function iterateColumn(string $query, array $params = [], array $types = []): \Traversable
    {
        $rows = $this->callCache(
            __FUNCTION__,
            $query,
            [$params, $types],
            fn () => iterator_to_array($this->inner->iterateColumn($query, $params, $types)),
        );

        return static::generateRowsTraversable($rows);
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

    public function executeCacheQuery($sql, $params, $types, QueryCacheProfile $qcp): Result
    {
        return $this->inner->executeCacheQuery($sql, $params, $types, $qcp);
    }

    public function executeStatement($sql, array $params = [], array $types = []): int|string
    {
        try {
            return $this->inner->executeStatement($sql, $params, $types);
        } finally {
            $this->cache->invalidateTags(static::extractCacheTags($sql, $params));
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

    public function transactional(Closure $func): mixed
    {
        return $this->inner->transactional($func);
    }

    public function setNestTransactionsWithSavepoints($nestTransactionsWithSavepoints): void
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

    public function createSavepoint($savepoint): void
    {
        $this->inner->createSavepoint($savepoint);
    }

    public function releaseSavepoint($savepoint): void
    {
        $this->inner->releaseSavepoint($savepoint);
    }

    public function rollbackSavepoint($savepoint): void
    {
        $this->inner->rollbackSavepoint($savepoint);
    }

    public function getWrappedConnection()
    {
        return $this->inner->getWrappedConnection();
    }

    public function getNativeConnection()
    {
        return $this->inner->getNativeConnection();
    }

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

    public function executeUpdate(string $sql, array $params = [], array $types = []): int
    {
        return $this->executeStatement($sql, $params, $types);
    }

    public function query(string $sql): Result
    {
        return $this->executeQuery($sql);
    }

    public function exec(string $sql): int
    {
        return $this->executeStatement($sql);
    }

    /**
     * 生成缓存KEY
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
    private function extractCacheTags(string $sql, array $params): array
    {
        $result = [];

        // 按照doctrine的风格，我们应该可以直接正则匹配出来的
        preg_match_all('@ FROM (.*?) @', $sql, $matches);
        foreach ($matches[1] as $str) {
            if (static::isRealTable($str)) {
                $result[] = trim(static::filterVar($str));
            }
        }
        preg_match_all('@ JOIN (.*?) @', $sql, $matches);
        foreach ($matches[1] as $str) {
            if (static::isRealTable($str)) {
                $result[] = trim(static::filterVar($str));
            }
        }
        preg_match_all('@UPDATE (.*?) @', $sql, $matches);
        foreach ($matches[1] as $str) {
            if (static::isRealTable($str)) {
                $result[] = trim(static::filterVar($str));
            }
        }
        preg_match_all('@INSERT INTO (.*?) @', $sql, $matches);
        foreach ($matches[1] as $str) {
            if (static::isRealTable($str)) {
                $result[] = trim(static::filterVar($str));
            }
        }
        preg_match_all('@DELETE FROM (.*?) @', $sql, $matches);
        foreach ($matches[1] as $str) {
            if (static::isRealTable($str)) {
                $result[] = trim(static::filterVar($str));
            }
        }

        $result = array_unique($result);

        return array_values($result);
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
    private function callCache(string $func, string $query, array $params, callable $callback): mixed
    {
        if (!($_ENV['DOCTRINE_CACHE_TABLE_SWITCH'] ?? true)) {
            return $callback();
        }
        if (!$this->cacheStrategy->shouldCache($query, $params)) {
            return $callback();
        }

        $cacheKey = $this->buildCacheKey($func, $query, $params);
        $tags = $this->extractCacheTags($query, $params);

        if (!$this->isOpenCache()) {
            try {
                $this->cache->delete($cacheKey);
                // $this->cache->invalidateTags($tags); // 这样子会不会缓存清除过多了？
            } catch (\Throwable $exception) {
                $this->logger->warning('数据库连接主动清除缓存失败', ['exception' => $exception]);
            }

            return $callback();
        }

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($func, $query, $params, $tags, $callback) {
            // 标签
            $item->tag($tags);

            // 过期时间
            $duration = null;
            foreach ($tags as $tag) {
                if (null !== $tag) {
                    break;
                }
                $duration = $_ENV["DOCTRINE_CACHE_TABLE_DURATION_{$tag}"] ?? null;
            }
            if (null === $duration) {
                $duration = $_ENV['DOCTRINE_GLOBAL_CACHE_TABLE_DURATION'] ?? 60 * 60 * 24; // 默认一天
            }
            $item->expiresAfter($duration);

            try {
                return $callback();
            } finally {
                $this->logger->debug('写入数据库缓存', [
                    'func' => $func,
                    'tags' => $tags,
                    'duration' => $duration,
                    'query' => $query,
                    'params' => $params,
                ]);
            }
        });
    }

    private static function generateRowsTraversable(array $rows): \Traversable
    {
        while (!empty($rows)) {
            $row = array_shift($rows);
            yield $row;
        }
    }
}
