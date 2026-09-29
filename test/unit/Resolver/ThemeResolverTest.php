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

namespace WebwareTest\Theme\Resolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Theme\Resolver\ThemeResolver;

#[CoversClass(ThemeResolver::class)]
#[CoversMethod(ThemeResolver::class, 'resolve')]
final class ThemeResolverTest extends TestCase
{
    /** @var array<string, array<string, string>> */
    private const MAPS = [
        'default' => [
            'app::home-page'  => '/app/templates/default/app/home-page.phtml',
            'layout::default' => '/app/templates/default/layout/default.phtml',
        ],
        'acme'    => [
            'app::home-page' => '/app/templates/acme/app/home-page.phtml',
        ],
    ];

    #[Test]
    public function defaultsToTheDefaultThemeWhenNoneIsConfigured(): void
    {
        $resolver = new ThemeResolver(self::MAPS);

        static::assertSame(
            '/app/templates/default/app/home-page.phtml',
            $resolver->resolve('app::home-page'),
        );
    }

    #[Test]
    public function fallsBackToDefaultForAnAddressTheActiveThemeDoesNotCarry(): void
    {
        $resolver = new ThemeResolver(self::MAPS, 'acme');

        static::assertSame(
            '/app/templates/default/layout/default.phtml',
            $resolver->resolve('layout::default'),
        );
    }

    #[Test]
    public function fallsBackToDefaultWhenTheActiveThemeHasNoMapAtAll(): void
    {
        $resolver = new ThemeResolver(self::MAPS, 'a-theme-nobody-shipped');

        static::assertSame(
            '/app/templates/default/app/home-page.phtml',
            $resolver->resolve('app::home-page'),
        );
    }

    #[Test]
    public function resolvesAnAddressTheActiveThemeCarries(): void
    {
        $resolver = new ThemeResolver(self::MAPS, 'acme');

        static::assertSame(
            '/app/templates/acme/app/home-page.phtml',
            $resolver->resolve('app::home-page'),
        );
    }

    #[Test]
    public function returnsFalseForAnAddressNoThemeCarries(): void
    {
        $resolver = new ThemeResolver(self::MAPS, 'acme');

        static::assertFalse($resolver->resolve('admin::dashboard'));
    }

    #[Test]
    public function returnsFalseWhenNoMapsAreConfigured(): void
    {
        $resolver = new ThemeResolver([]);

        static::assertFalse($resolver->resolve('app::home-page'));
    }
}
