<?php

namespace Tourze\DoctrineCacheBundle\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Tourze\DoctrineHelper\CacheHelper;

/**
 * 对象变更时，自动清除对应标签的缓存
 */
#[AsDoctrineListener(event: Events::postRemove, priority: -99)]
#[AsDoctrineListener(event: Events::postPersist, priority: -99)]
#[AsDoctrineListener(event: Events::postUpdate, priority: -99)]
class CacheTagInvalidateListener
{
    public function __construct(
        private readonly TagAwareCacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function postRemove(PostRemoveEventArgs $eventArgs): void
    {
        $this->refreshCache($eventArgs->getObject());
    }

    public function postPersist(PostPersistEventArgs $eventArgs): void
    {
        $this->refreshCache($eventArgs->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $eventArgs): void
    {
        $this->refreshCache($eventArgs->getObject());
    }

    public function refreshCache(object $object): void
    {
        try {
            $this->cache->invalidateTags([
                CacheHelper::getClassTags(ClassUtils::getClass($object)),
                CacheHelper::getObjectTags($object),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('清除实体标签缓存时发生错误', [
                'exception' => $exception,
                'object' => $object,
            ]);
        }
    }
}
