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

namespace WebwareTest\Theme;

use Laminas\View\ConfigProvider as LaminasViewConfigProvider;
use Laminas\View\Helper\Asset;
use Laminas\View\Resolver\AggregateResolver;
use Mezzio\LaminasView\ConfigProvider as MezzioLaminasViewConfigProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Webware\ConfigManager\Attribute\LoadAfter;
use Webware\Theme\ConfigProvider;
use Webware\Theme\Resolver\Container\AggregateResolverFactory;
use Webware\Theme\Resolver\Container\ThemeResolverFactory;
use Webware\Theme\Resolver\ThemeResolver;
use Webware\Theme\View\Helper\Container\AssetFactory;

#[CoversClass(ConfigProvider::class)]
#[CoversMethod(ConfigProvider::class, '__invoke')]
#[CoversMethod(ConfigProvider::class, 'getDependencies')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function getDependenciesProvidesTheResolverAndTheAggregateFactories(): void
    {
        $expected = [
            'factories' => [
                ThemeResolver::class     => ThemeResolverFactory::class,
                AggregateResolver::class => AggregateResolverFactory::class,
            ],
        ];

        static::assertSame($expected, new ConfigProvider()->getDependencies());
    }

    #[Test]
    public function providesTheResolverAndTheAggregateFactories(): void
    {
        $expected = [
            'dependencies' => [
                'factories' => [
                    ThemeResolver::class     => ThemeResolverFactory::class,
                    AggregateResolver::class => AggregateResolverFactory::class,
                ],
            ],
            'view_helpers' => [
                'factories' => [
                    Asset::class => AssetFactory::class,
                ],
            ],
        ];

        static::assertSame($expected, new ConfigProvider()->__invoke());
    }

    #[Test]
    public function requiresTheViewProvidersToMergeFirst(): void
    {
        $attribute = new ReflectionClass(ConfigProvider::class)->getAttributes(LoadAfter::class);

        static::assertCount(1, $attribute);
        static::assertSame(
            [LaminasViewConfigProvider::class, MezzioLaminasViewConfigProvider::class],
            $attribute[0]->newInstance()->providers,
        );
    }
}
