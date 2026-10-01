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

namespace WebwareTest\Theme\View\Helper\Container;

use Laminas\View\Exception\InvalidArgumentException;
use Laminas\View\Helper\Asset;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Webware\Theme\Exception\InvalidThemeNameException;
use Webware\Theme\View\Helper\Container\AssetFactory;

#[CoversClass(AssetFactory::class)]
#[CoversMethod(AssetFactory::class, '__invoke')]
final class AssetFactoryTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function absoluteUrls(): iterable
    {
        yield 'https' => ['bootstrap', 'https://cdn.example.com/bootstrap.css'];
        yield 'http' => ['insecure', 'http://cdn.example.com/y.js'];
        yield 'protocol relative' => ['protocol', '//cdn.example.com/x.js'];
    }

    /** @return iterable<string, array{string}> */
    public static function badThemeNames(): iterable
    {
        yield 'traversal' => ['..'];
        yield 'nested' => ['a/b'];
        yield 'backslash' => ['a\\b'];
        yield 'leading dot' => ['.hidden'];
        yield 'space' => ['a b'];
    }

    #[Test]
    public function aLeadingSlashOnARelativeValueDoesNotEscapeTheThemeDirectory(): void
    {
        $asset = $this->build($this->config('default'));

        static::assertSame('/theme/default/css/leading.css', $asset('leading'));
    }

    #[Test]
    #[DataProvider('absoluteUrls')]
    public function anAbsoluteUrlPassesThroughUnchanged(string $name, string $expected): void
    {
        $asset = $this->build($this->config('acme'));

        static::assertSame($expected, $asset($name));
    }

    #[Test]
    public function aNameTheActiveThemeLacksFallsBackToDefaultByName(): void
    {
        $asset = $this->build($this->config('acme'));

        static::assertSame('/theme/default/js/app.js', $asset('app.js'));
    }

    #[Test]
    public function anEmptyActiveNameMeansDefault(): void
    {
        $asset = $this->build($this->config(''));

        static::assertSame('/theme/default/css/theme.css', $asset('theme.css'));
    }

    #[Test]
    public function anUnknownNameThrowsFromTheHelper(): void
    {
        $asset = $this->build($this->config('acme'));

        $this->expectException(InvalidArgumentException::class);

        $asset('missing.css');
    }

    #[Test]
    #[DataProvider('badThemeNames')]
    public function aThemeNameThatIsNotASingleSegmentIsRejected(string $name): void
    {
        $this->expectException(InvalidThemeNameException::class);

        $this->build($this->config($name));
    }

    #[Test]
    public function entriesUnderLaminasViewsOwnKeyAreKept(): void
    {
        $config                       = $this->config('acme');
        $config['view_helper_config'] = ['asset' => ['resource_map' => ['legacy.css' => '/legacy.css']]];

        $asset = $this->build($config);

        static::assertSame('/legacy.css', $asset('legacy.css'));
    }

    #[Test]
    public function malformedAssetEntriesYieldAnEmptyMap(): void
    {
        $asset = $this->build([
            'theme'              => ['assets' => 'x'],
            'view_helper_config' => ['asset' => 'y'],
        ]);

        $this->expectException(InvalidArgumentException::class);

        $asset('theme.css');
    }

    #[Test]
    public function malformedThemeConfigurationYieldsAnEmptyMap(): void
    {
        $asset = $this->build(['theme' => 'x', 'view_helper_config' => 'y']);

        $this->expectException(InvalidArgumentException::class);

        $asset('theme.css');
    }

    #[Test]
    public function theActiveThemeWinsANameItDefines(): void
    {
        $asset = $this->build($this->config('acme'));

        static::assertSame('/theme/acme/css/acme.css', $asset('theme.css'));
    }

    #[Test]
    public function theDefaultThemeValuesAreServedFromItsOwnDirectory(): void
    {
        $asset = $this->build($this->config('default'));

        static::assertSame('/theme/default/css/theme.css', $asset('theme.css'));
    }

    #[Test]
    public function withoutConfigurationEveryNameThrowsFromTheHelper(): void
    {
        $asset = $this->build([], hasConfig: false);

        $this->expectException(InvalidArgumentException::class);

        $asset('theme.css');
    }

    /** @param array<string, mixed> $config */
    private function build(array $config, bool $hasConfig = true): Asset
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn($hasConfig);
        $container->method('get')->willReturn($config);

        return new AssetFactory()->__invoke($container);
    }

    /** @return array<string, mixed> */
    private function config(string $active): array
    {
        return [
            'theme' => [
                'active' => $active,
                'assets' => [
                    'default' => [
                        'theme.css' => 'css/theme.css',
                        'app.js'    => 'js/app.js',
                        'bootstrap' => 'https://cdn.example.com/bootstrap.css',
                        'protocol'  => '//cdn.example.com/x.js',
                        'insecure'  => 'http://cdn.example.com/y.js',
                        'leading'   => '/css/leading.css',
                    ],
                    'acme'    => [
                        'theme.css' => 'css/acme.css',
                    ],
                ],
            ],
        ];
    }
}
