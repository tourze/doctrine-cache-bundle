# Doctrine 缓存组件

[![最新版本](https://img.shields.io/packagist/v/tourze/doctrine-cache-bundle.svg?style=flat-square)]
(https://packagist.org/packages/tourze/doctrine-cache-bundle)
[![PHP 版本](https://img.shields.io/packagist/php-v/tourze/doctrine-cache-bundle.svg?style=flat-square)]
(https://packagist.org/packages/tourze/doctrine-cache-bundle)
[![开源协议](https://img.shields.io/github/license/tourze/php-monorepo.svg?style=flat-square)]
(https://github.com/tourze/php-monorepo/blob/main/packages/doctrine-cache-bundle/LICENSE)
[![构建状态](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?style=flat-square)]
(https://github.com/tourze/php-monorepo/actions)
[![Coverage](https://img.shields.io/codecov/c/github/tourze/php-monorepo?style=flat-square)]
(https://codecov.io/gh/tourze/php-monorepo)

[English](README.md) | [中文](README.zh-CN.md)

一个为 Doctrine ORM 提供缓存功能的 Symfony 组件，能够基于实体变更自动更新缓存。

## 功能特性

- 基于实体生命周期事件（插入、更新、删除）自动失效缓存
- 基于标签的缓存失效策略，实现更精细的缓存控制
- 缓存策略模式，支持灵活的缓存行为自定义
- 与 Symfony 和 Doctrine 无缝集成
- 兼容 PSR-6 缓存实现

## 系统要求

- PHP 8.1+
- Symfony 6.4+
- Doctrine ORM 2.20+ 或 3.0+

## 安装方法

```bash
composer require tourze/doctrine-cache-bundle
```

## 快速开始

### 1. 在 `config/bundles.php` 中注册组件：

```php
<?php

return [
    // ... 其他组件
    Tourze\DoctrineCacheBundle\DoctrineCacheBundle::class => ['all' => true],
];
```

### 2. 基本使用示例：

该组件会自动监听 Doctrine 实体生命周期事件，并在实体变更时使相关缓存标签失效：

```php
<?php

namespace App\Controller;

use App\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PostController extends AbstractController
{
    #[Route('/posts', name: 'app_posts')]
    public function index(
        EntityManagerInterface $entityManager,
        TagAwareAdapterInterface $cache
    ): Response {
        // 从缓存或数据库获取文章
        $cacheKey = 'all_posts';
        $cacheItem = $cache->getItem($cacheKey);
        
        if (!$cacheItem->isHit()) {
            $posts = $entityManager->getRepository(Post::class)->findAll();
            $cacheItem->set($posts);
            
            // 使用实体类名作为缓存标签
            $cacheItem->tag(['App_Entity_Post']);
            $cache->save($cacheItem);
        } else {
            $posts = $cacheItem->get();
        }
        
        return $this->render('post/index.html.twig', [
            'posts' => $posts,
        ]);
    }
}
```

当 Post 实体被更新时，组件会自动使带有对应标签名的缓存失效。

## 配置说明

基本功能不需要特定配置。组件会自动注册所需的服务。

## 高级用法

### 自定义缓存策略

如果你想实现自定义缓存策略，可以创建自己的策略类：

```php
<?php

namespace App\Cache\Strategy;

use Tourze\CacheStrategy\CacheStrategy;

class CustomCacheStrategy implements CacheStrategy
{
    public function shouldCache(string $query, array $params): bool
    {
        // 你的逻辑，用于确定查询是否应该被缓存
        return true; // 或 false
    }
}
```

在服务配置中注册你的策略并添加适当的标签：

```yaml
# config/services.yaml
services:
    App\Cache\Strategy\CustomCacheStrategy:
        tags: ['doctrine.cache.entity_cache_strategy']
```

### 环境变量

你可以通过环境变量控制缓存行为：

- `DOCTRINE_CACHE_TABLE_SWITCH`: 启用/禁用缓存 (默认: true)
- `DOCTRINE_GLOBAL_CACHE_TABLE_DURATION`: 默认缓存持续时间（秒）(默认: 86400)
- `DOCTRINE_CACHE_TABLE_DURATION_{table_name}`: 特定表的缓存持续时间

### 缓存标签命名

组件会根据从 SQL 查询中提取的表名自动生成缓存标签。常见的 SQL 操作（SELECT、INSERT、
UPDATE、DELETE）会被自动检测，并生成相应的缓存标签。

## 最佳实践

- 使用特定的缓存标签来最小化失效范围
- 在使用缓存失效时，注意复杂的实体关系
- 对于高性能应用，考虑实现自定义缓存策略

## 贡献指南

欢迎提交贡献！请随时提交 Pull Request。

## 开源协议

本组件基于 MIT 协议发布。详情请查看 [LICENSE](LICENSE) 文件。 
