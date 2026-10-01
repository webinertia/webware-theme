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

namespace WebwareTest\Theme\Resolver\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Webware\Theme\Resolver\Container\ThemeResolverFactory;
use Webware\Theme\Resolver\ThemeResolver;

#[CoversClass(ThemeResolverFactory::class)]
#[CoversMethod(ThemeResolverFactory::class, '__invoke')]
final class ThemeResolverFactoryTest extends TestCase
{
    #[Test]
    public function aMissingActiveThemeMeansDefault(): void
    {
        static::assertSame('/default/layout.phtml', $this->build($this->config(null))->resolve('layout::default'));
    }

    #[Test]
    public function anAddressTheActiveThemeLacksComesFromDefault(): void
    {
        static::assertSame('/default/home.phtml', $this->build($this->config('acme'))->resolve('app::home'));
    }

    #[Test]
    public function anEmptyActiveThemeMeansDefault(): void
    {
        static::assertSame('/default/layout.phtml', $this->build($this->config(''))->resolve('layout::default'));
    }

    #[Test]
    public function aNonStringActiveThemeMeansDefault(): void
    {
        static::assertSame('/default/layout.phtml', $this->build($this->config(1))->resolve('layout::default'));
    }

    #[Test]
    public function anUnknownAddressIsNotResolved(): void
    {
        static::assertFalse($this->build($this->config('acme'))->resolve('missing::address'));
    }

    #[Test]
    public function malformedConfigurationNothingResolves(): void
    {
        static::assertFalse($this->build(['theme' => 'x'])->resolve('layout::default'));
        static::assertFalse($this->build(['theme' => ['themes' => 'x']])->resolve('layout::default'));
    }

    #[Test]
    public function theActiveThemeWinsAnAddressItCarries(): void
    {
        static::assertSame('/acme/layout.phtml', $this->build($this->config('acme'))->resolve('layout::default'));
    }

    #[Test]
    public function withoutConfigurationNothingResolves(): void
    {
        static::assertFalse($this->build([], hasConfig: false)->resolve('layout::default'));
    }

    /** @param array<string, mixed> $config */
    private function build(array $config, bool $hasConfig = true): ThemeResolver
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn($hasConfig);
        $container->method('get')->willReturn($config);

        return new ThemeResolverFactory()->__invoke($container);
    }

    /** @return array<string, mixed> */
    private function config(mixed $active): array
    {
        return [
            'theme' => [
                'active' => $active,
                'themes' => [
                    'default' => ['layout::default' => '/default/layout.phtml', 'app::home' => '/default/home.phtml'],
                    'acme'    => ['layout::default' => '/acme/layout.phtml'],
                ],
            ],
        ];
    }
}
