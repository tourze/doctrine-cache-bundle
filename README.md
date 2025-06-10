# Doctrine Cache Bundle

[![Latest Version](https://img.shields.io/packagist/v/tourze/doctrine-cache-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/doctrine-cache-bundle)
[![License](https://img.shields.io/github/license/tourze/php-monorepo.svg?style=flat-square)](https://github.com/tourze/php-monorepo/blob/main/packages/doctrine-cache-bundle/LICENSE)

[English](README.md) | [中文](README.zh-CN.md)

A Symfony bundle that provides caching capabilities for Doctrine ORM, with automatic cache invalidation based on entity changes.

## Features

- Automatic cache invalidation based on entity lifecycle events (insert, update, delete)
- Tag-based cache invalidation strategy for better cache control
- Cache strategy pattern allows for flexible cache behavior customization
- Seamless integration with Symfony and Doctrine
- Compatible with PSR-6 cache implementations

## Installation

```bash
composer require tourze/doctrine-cache-bundle
```

### Requirements

- PHP 8.1+
- Symfony 6.4+
- Doctrine ORM 2.20+ or 3.0+

## Quick Start

### 1. Register the bundle in your `config/bundles.php`:

```php
<?php

return [
    // ... other bundles
    Tourze\DoctrineCacheBundle\DoctrineCacheBundle::class => ['all' => true],
];
```

### 2. Basic usage example:

The bundle automatically listens to Doctrine entity lifecycle events and invalidates related cache tags when entities are changed:

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
        // Get posts from cache or database
        $cacheKey = 'all_posts';
        $cacheItem = $cache->getItem($cacheKey);
        
        if (!$cacheItem->isHit()) {
            $posts = $entityManager->getRepository(Post::class)->findAll();
            $cacheItem->set($posts);
            
            // Tag the cache with the entity class name
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

When a Post entity is updated, the bundle will automatically invalidate the cache with the tagged name.

## Configuration

No specific configuration is needed for basic functionality. The bundle automatically registers the required services.

If you want to implement custom cache strategies, you can create your own strategy class:

```php
<?php

namespace App\Cache\Strategy;

use Tourze\CacheStrategy\CacheStrategy;

class CustomCacheStrategy implements CacheStrategy
{
    public function shouldCache(string $query, array $params): bool
    {
        // Your logic to determine if a query should be cached
        return true; // or false
    }
}
```

Register your strategy as a service with the appropriate tag:

```yaml
# config/services.yaml
services:
    App\Cache\Strategy\CustomCacheStrategy:
        tags: ['doctrine.cache.entity_cache_strategy']
```

## Best Practices

- Use specific cache tags to minimize invalidation scope
- Be mindful of complex entity relationships when using cache invalidation
- For high-performance applications, consider implementing custom cache strategies

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This bundle is released under the MIT License. See the [LICENSE](LICENSE) file for details.
