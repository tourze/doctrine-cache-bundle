<?php

namespace Tourze\DoctrineCacheBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\DoctrineCacheBundle\DependencyInjection\DoctrineCacheExtension;
use Tourze\DoctrineCacheBundle\EventSubscriber\CacheTagInvalidateListener;
use Tourze\DoctrineCacheBundle\Strategy\CacheStrategyCollector;

class DoctrineCacheExtensionTest extends TestCase
{
    public function testLoad(): void
    {
        $container = new ContainerBuilder();
        $extension = new DoctrineCacheExtension();

        // 模拟服务加载
        $extension->load([], $container);

        // 验证关键服务是否已注册
        $this->assertTrue($container->has(CacheTagInvalidateListener::class));
        $this->assertTrue($container->has(CacheStrategyCollector::class));

        // 验证服务配置
        $tagInvalidateListener = $container->getDefinition(CacheTagInvalidateListener::class);
        $this->assertTrue($tagInvalidateListener->isAutowired());
        $this->assertTrue($tagInvalidateListener->isAutoconfigured());

        $strategyCollector = $container->getDefinition(CacheStrategyCollector::class);
        $this->assertTrue($strategyCollector->isAutowired());
        $this->assertTrue($strategyCollector->isAutoconfigured());
    }
}
