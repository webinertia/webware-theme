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

namespace Webware\Theme\View\Helper\Container;

use Laminas\View\Helper\Asset;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webware\Theme\ConfigPath;
use Webware\Theme\ConfigProvider;
use Webware\Theme\Exception\InvalidThemeNameException;
use Webware\Theme\Resolver\ThemeResolver;

use function is_string;
use function ltrim;
use function preg_match;
use function sprintf;
use function str_starts_with;

/**
 * Builds laminas-view's stock `Asset` helper from a map the active theme has been merged into.
 *
 * `Asset` is `final readonly` and knows nothing about themes, so the theme is applied here, once: the
 * `default` theme's values first, the active theme's over them. A relative value becomes
 * `/theme/<theme>/<value>` using the theme that defined the name, so the fallback is per asset name.
 * Entries already under laminas-view's own `view_helper_config.asset.resource_map` are the base, so an
 * application's existing map keeps working.
 *
 * @internal
 */
final class AssetFactory
{
    private const string URL_PREFIX = '/theme/';

    /** @throws InvalidThemeNameException */
    private static function assertSegment(string $name): void
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $name)) {
            throw new InvalidThemeNameException(sprintf(
                'The theme name "%s" is not a single path segment.',
                $name,
            ));
        }
    }

    private static function url(string $theme, string $value): string
    {
        return match (true) {
            str_starts_with($value, 'https://'),
            str_starts_with($value, 'http://'),
            str_starts_with($value, '//'),
                => $value,
            default                                                                                                => self::URL_PREFIX
                . $theme
                . '/'
                . ltrim($value, characters: '/'),
        };
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidThemeNameException
     */
    public function __invoke(ContainerInterface $container): Asset
    {
        /** @var mixed $config */
        $config = $container->has('config') ? $container->get('config') : [];
        $theme  = ConfigPath::array($config, ConfigProvider::THEME);
        $assets = ConfigPath::array($theme, ConfigProvider::ASSETS);

        /** @var mixed $active */
        $active = $theme[ConfigProvider::ACTIVE] ?? null;
        $active = is_string($active) && '' !== $active ? $active : ThemeResolver::DEFAULT_THEME;

        $map = ConfigPath::array($config, 'view_helper_config', 'asset', 'resource_map');

        foreach ([ThemeResolver::DEFAULT_THEME, $active] as $name) {
            self::assertSegment(name: $name);

            /** @var array<string, string> $values */
            $values = ConfigPath::array($assets, $name);

            foreach ($values as $asset => $value) {
                $map[$asset] = self::url(
                    theme: $name,
                    value: $value,
                );
            }
        }

        /** @var array<non-empty-string, non-empty-string> $map */
        return new Asset($map);
    }
}
