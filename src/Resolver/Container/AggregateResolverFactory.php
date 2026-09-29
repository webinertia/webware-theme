<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Theme package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\Theme\Resolver\Container;

use Laminas\View\Resolver\AggregateResolver;
use Laminas\View\Resolver\ResolverInterface;
use Laminas\View\Resolver\TemplateMapResolver;
use Mezzio\LaminasView\NamespacedPathStackResolver;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webware\Theme\Resolver\ThemeResolver;

/**
 * Composes the resolver aggregate with the theme consulted first, then mezzio's map, then its path
 * stack.
 *
 * mezzio's own factory attaches the map and the path stack at equal priority, which leaves their
 * relative order to the queue. Here the order is stated outright, which is what makes an address the
 * active theme carries win and every other address resolve exactly as it did before this package was
 * installed.
 *
 * Registering this factory replaces `Mezzio\LaminasView\TemplateResolverFactory` through the
 * `AggregateResolver::class` service key, so the theme package's provider must be merged after
 * mezzio's.
 *
 * @internal
 */
final class AggregateResolverFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): ResolverInterface
    {
        $aggregate = new AggregateResolver();

        $aggregate->attach($container->get(ThemeResolver::class), 100);
        $aggregate->attach($container->get(TemplateMapResolver::class), 50);
        $aggregate->attach($container->get(NamespacedPathStackResolver::class), 1);

        return $aggregate;
    }
}
