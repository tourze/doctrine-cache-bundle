<?php

declare(strict_types=1);

namespace Tourze\DoctrineCacheBundle\Tests\EventSubscriber;

use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Tourze\DoctrineCacheBundle\EventSubscriber\CacheTagInvalidateListener;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * @internal
 */
#[CoversClass(CacheTagInvalidateListener::class)]
#[RunTestsInSeparateProcesses]
final class CacheTagInvalidateListenerTest extends AbstractEventSubscriberTestCase
{
    private CacheTagInvalidateListener $listener;
    private TagAwareCacheInterface $cache;
    private LoggerInterface $logger;

    protected function onSetUp(): void
    {
        // 从服务容器获取真实服务实例
        $this->listener = self::getService(CacheTagInvalidateListener::class);
        $this->cache = self::getService(TagAwareCacheInterface::class);
        $this->logger = self::getService(LoggerInterface::class);
    }

    public function testListenerCanBeRetrievedFromContainer(): void
    {
        // 验证监听器可以从容器中获取
        $this->assertInstanceOf(CacheTagInvalidateListener::class, $this->listener);
    }

    public function testListenerHasDependencies(): void
    {
        // 验证监听器的依赖注入正确
        $this->assertInstanceOf(TagAwareCacheInterface::class, $this->cache);
        $this->assertInstanceOf(LoggerInterface::class, $this->logger);
    }

    public function testRefreshCacheMethodExists(): void
    {
        // 验证 refreshCache 方法存在
        $this->assertTrue(method_exists($this->listener, 'refreshCache'));

        // 使用反射验证方法签名
        $reflection = new \ReflectionMethod($this->listener, 'refreshCache');
        $this->assertTrue($reflection->isPublic());
        $this->assertCount(1, $reflection->getParameters());

        $param = $reflection->getParameters()[0];
        $this->assertEquals('object', $param->getName());

        // 验证参数类型是 object
        $paramType = $param->getType();
        $this->assertNotNull($paramType);
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertEquals('object', $paramType->getName());
    }

    public function testPostRemoveMethodExists(): void
    {
        // 验证 postRemove 方法存在并且有正确的签名
        $this->assertTrue(method_exists($this->listener, 'postRemove'));

        $reflection = new \ReflectionMethod($this->listener, 'postRemove');
        $this->assertTrue($reflection->isPublic());
        $this->assertCount(1, $reflection->getParameters());

        $param = $reflection->getParameters()[0];
        $paramType = $param->getType();
        $this->assertNotNull($paramType);
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertEquals(PostRemoveEventArgs::class, $paramType->getName());
    }

    public function testPostPersistMethodExists(): void
    {
        // 验证 postPersist 方法存在并且有正确的签名
        $this->assertTrue(method_exists($this->listener, 'postPersist'));

        $reflection = new \ReflectionMethod($this->listener, 'postPersist');
        $this->assertTrue($reflection->isPublic());
        $this->assertCount(1, $reflection->getParameters());

        $param = $reflection->getParameters()[0];
        $paramType = $param->getType();
        $this->assertNotNull($paramType);
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertEquals(PostPersistEventArgs::class, $paramType->getName());
    }

    public function testPostUpdateMethodExists(): void
    {
        // 验证 postUpdate 方法存在并且有正确的签名
        $this->assertTrue(method_exists($this->listener, 'postUpdate'));

        $reflection = new \ReflectionMethod($this->listener, 'postUpdate');
        $this->assertTrue($reflection->isPublic());
        $this->assertCount(1, $reflection->getParameters());

        $param = $reflection->getParameters()[0];
        $paramType = $param->getType();
        $this->assertNotNull($paramType);
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertEquals(PostUpdateEventArgs::class, $paramType->getName());
    }

    public function testRefreshCacheWithObjectThatHasGetIdMethod(): void
    {
        // 创建一个匿名类来模拟具有 getId 方法的实体
        $testObject = new class {
            private int $id = 123;

            public function getId(): int
            {
                return $this->id;
            }
        };

        // 调用 refreshCache 方法应该不抛出异常
        $this->listener->refreshCache($testObject);

        // 验证方法执行成功（通过没有异常来判断）
        $this->assertTrue(true);
    }

    public function testRefreshCacheWithObjectWithoutGetIdMethod(): void
    {
        // 创建一个没有 getId 方法的对象
        $testObject = new \stdClass();

        // 调用 refreshCache 方法
        // 根据实现，这应该记录错误但不抛出异常（因为有 try-catch）
        $this->listener->refreshCache($testObject);

        // 验证方法执行完成（异常被捕获）
        $this->assertTrue(true);
    }

    public function testRefreshCacheWithObjectThatReturnsNullId(): void
    {
        // 创建一个返回 null ID 的对象
        $testObject = new class {
            public function getId(): ?int
            {
                return null;
            }
        };

        // 调用 refreshCache 方法应该不抛出异常
        $this->listener->refreshCache($testObject);

        // 验证方法执行成功
        $this->assertTrue(true);
    }

    public function testListenerIsReadonly(): void
    {
        // 验证监听器类是 readonly 的（PHP 8.2+ 特性）
        $reflection = new \ReflectionClass($this->listener);
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->isFinal());
    }

    public function testListenerHasCorrectAttributes(): void
    {
        // 验证监听器类有正确的属性标记
        $reflection = new \ReflectionClass($this->listener);

        // 检查 AsDoctrineListener 属性
        $attributes = $reflection->getAttributes();
        $this->assertNotEmpty($attributes, 'Listener should have attributes');

        // 验证至少有监听器相关的属性
        $attributeNames = array_map(fn($attr) => $attr->getName(), $attributes);

        // 根据实际实现，应该有 AsDoctrineListener 和 WithMonologChannel 属性
        $hasDoctrineListener = false;
        foreach ($attributeNames as $name) {
            if (str_contains($name, 'AsDoctrineListener') || str_contains($name, 'DoctrineListener')) {
                $hasDoctrineListener = true;
                break;
            }
        }

        $this->assertTrue($hasDoctrineListener, 'Listener should have Doctrine listener attributes');
    }

    public function testCacheInterfaceIsTagAware(): void
    {
        // 验证注入的缓存实现支持标签功能
        $this->assertInstanceOf(TagAwareCacheInterface::class, $this->cache);

        // 验证 invalidateTags 方法存在
        $this->assertTrue(method_exists($this->cache, 'invalidateTags'));
    }
}
