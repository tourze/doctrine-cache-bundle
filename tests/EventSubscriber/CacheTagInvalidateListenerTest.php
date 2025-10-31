<?php

declare(strict_types=1);

namespace Tourze\DoctrineCacheBundle\Tests\EventSubscriber;

use BizUserBundle\Entity\BizUser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DoctrineCacheBundle\EventSubscriber\CacheTagInvalidateListener;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * @internal
 */
#[CoversClass(CacheTagInvalidateListener::class)]
#[RunTestsInSeparateProcesses]
final class CacheTagInvalidateListenerTest extends AbstractEventSubscriberTestCase
{
    protected function onSetUp(): void
    {
    }

    public function testRefreshCache(): void
    {
        $listener = self::getService(CacheTagInvalidateListener::class);

        $entity = new BizUser();
        $entity->setId(123);
        $entity->setUsername('test_user');

        $listener->refreshCache($entity);

        $this->expectNotToPerformAssertions();
    }

    public function testPostPersist(): void
    {
        $listener = self::getService(CacheTagInvalidateListener::class);

        $entity = new BizUser();
        $entity->setId(123);
        $entity->setUsername('test_user_persist');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $event = new PostPersistEventArgs($entity, $entityManager);

        $listener->postPersist($event);

        $this->expectNotToPerformAssertions();
    }

    public function testPostRemove(): void
    {
        $listener = self::getService(CacheTagInvalidateListener::class);

        $entity = new BizUser();
        $entity->setId(456);
        $entity->setUsername('test_user_remove');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $event = new PostRemoveEventArgs($entity, $entityManager);

        $listener->postRemove($event);

        $this->expectNotToPerformAssertions();
    }

    public function testPostUpdate(): void
    {
        $listener = self::getService(CacheTagInvalidateListener::class);

        $entity = new BizUser();
        $entity->setId(789);
        $entity->setUsername('test_user_update');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $event = new PostUpdateEventArgs($entity, $entityManager);

        $listener->postUpdate($event);

        $this->expectNotToPerformAssertions();
    }
}
