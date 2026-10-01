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

namespace WebwareTestIntegration\Theme;

use Laminas\View\Resolver\AggregateResolver;
use Laminas\View\Resolver\TemplateMapResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Theme\Resolver\ThemeResolver;

use function is_file;

/**
 * Exercises resolution against real files: the active theme wins an address it carries, an address
 * it does not carry comes from `default`, and both hold when the theme resolver sits in a real
 * aggregate ahead of mezzio's map.
 */
#[CoversClass(ThemeResolver::class)]
#[CoversMethod(ThemeResolver::class, 'resolve')]
final class ThemeResolutionTest extends TestCase
{
    private const ASSETS = __DIR__ . '/TestAssets/themes';

    #[Test]
    public function anAddressTheThemeDoesNotCarryStillResolvesThroughTheMap(): void
    {
        $mezzio = new TemplateMapResolver([
            'admin::dashboard' => self::ASSETS . '/default/app/home-page.phtml',
        ]);

        $aggregate = new AggregateResolver();
        $aggregate->attach(new ThemeResolver($this->maps(), 'acme'), 100);
        $aggregate->attach($mezzio, 50);

        static::assertSame(
            self::ASSETS . '/default/app/home-page.phtml',
            $aggregate->resolve('admin::dashboard'),
        );
    }

    #[Test]
    public function fallsBackToTheDefaultThemesFileForAnAddressTheActiveThemeLacks(): void
    {
        $maps = $this->maps();
        unset($maps['acme']['layout::default']);

        $resolver = new ThemeResolver($maps, 'acme');

        $path = $resolver->resolve('layout::default');

        static::assertSame(self::ASSETS . '/default/layout/default.phtml', $path);
        static::assertIsString($path);
        static::assertTrue(is_file($path));
    }

    #[Test]
    public function resolvesTheActiveThemesFileFromDisk(): void
    {
        $resolver = new ThemeResolver($this->maps(), 'acme');

        $path = $resolver->resolve('app::home-page');

        static::assertIsString($path);
        static::assertSame(self::ASSETS . '/acme/app/home-page.phtml', $path);
        static::assertTrue(is_file($path));
    }

    #[Test]
    public function theThemeWinsAnAddressMezziosMapAlsoCarries(): void
    {
        // The same address, published by the module the old way, through mezzio's own map.
        $mezzio = new TemplateMapResolver([
            'app::home-page' => self::ASSETS . '/default/app/home-page.phtml',
        ]);

        $aggregate = new AggregateResolver();
        $aggregate->attach(new ThemeResolver($this->maps(), 'acme'), 100);
        $aggregate->attach($mezzio, 50);

        static::assertSame(
            self::ASSETS . '/acme/app/home-page.phtml',
            $aggregate->resolve('app::home-page'),
        );
    }

    /** @return array<string, array<string, string>> */
    private function maps(): array
    {
        return [
            'default' => [
                'app::home-page'  => self::ASSETS . '/default/app/home-page.phtml',
                'layout::default' => self::ASSETS . '/default/layout/default.phtml',
            ],
            'acme'    => [
                'app::home-page'  => self::ASSETS . '/acme/app/home-page.phtml',
                'layout::default' => self::ASSETS . '/acme/layout/default.phtml',
            ],
        ];
    }
}
