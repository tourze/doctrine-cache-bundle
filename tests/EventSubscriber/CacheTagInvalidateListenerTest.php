<?php

namespace Tourze\DoctrineCacheBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Tourze\DoctrineCacheBundle\EventSubscriber\CacheTagInvalidateListener;

class CacheTagInvalidateListenerTest extends TestCase
{
    /**
     * @var TagAwareCacheInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private TagAwareCacheInterface $cacheMock;

    /**
     * @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private LoggerInterface $loggerMock;

    private CacheTagInvalidateListener $listener;

    protected function setUp(): void
    {
        $this->cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->listener = new CacheTagInvalidateListener($this->cacheMock, $this->loggerMock);
    }

    /**
     * 直接测试refreshCache方法，避开final event类
     */
    public function testRefreshCache(): void
    {
        $entity = new TestEntity();
        $entity->id = 123;

        $this->cacheMock->expects($this->once())
            ->method('invalidateTags')
            ->with($this->callback(function (array $tags) {
                // 只确认有两个标签，内容由于依赖外部类不做检查
                return count($tags) === 2;
            }));

        // 使用反射来直接调用protected/private方法
        $reflection = new \ReflectionClass(CacheTagInvalidateListener::class);
        $method = $reflection->getMethod('refreshCache');
        $method->invoke($this->listener, $entity);
    }

    /**
     * 测试异常处理
     */
    public function testExceptionHandling(): void
    {
        $entity = new TestEntity();
        $entity->id = 999;

        $exception = new \RuntimeException('Test exception');

        $this->cacheMock->expects($this->once())
            ->method('invalidateTags')
            ->willThrowException($exception);

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo('清除实体标签缓存时发生错误'),
                $this->callback(function ($context) use ($exception, $entity) {
                    return $context['exception'] === $exception && $context['object'] === $entity;
                })
            );

        // 使用反射来直接调用protected/private方法
        $reflection = new \ReflectionClass(CacheTagInvalidateListener::class);
        $method = $reflection->getMethod('refreshCache');
        $method->invoke($this->listener, $entity);
    }
}

/**
 * 用于测试的实体类
 */
class TestEntity
{
    public int $id;

    public function getId(): int
    {
        return $this->id;
    }
}
