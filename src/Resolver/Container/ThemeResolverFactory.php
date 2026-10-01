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

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webware\Theme\ConfigProvider;
use Webware\Theme\Resolver\ThemeResolver;

use function is_array;
use function is_string;

/**
 * Builds the theme resolver from the merged `theme` configuration.
 *
 * This is where the active theme enters: the resolver is not told which theme to use at call time, so
 * the configuration is read once, here, and the resolver holds the answer.
 *
 * @import-type ThemeConfig from ConfigProvider
 * @internal
 */
final class ThemeResolverFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): ThemeResolver
    {
        /** @var mixed $raw */
        $raw = $container->has('config') ? $container->get('config') : [];

        /** @var array<string, mixed> $config */
        $config = is_array($raw) ? $raw : [];

        /** @var mixed $theme */
        $theme = $config[ConfigProvider::THEME] ?? [];

        /** @var ThemeConfig $theme */
        $theme = is_array($theme) ? $theme : [];

        /** @var mixed $maps */
        $maps = $theme[ConfigProvider::THEMES] ?? [];

        /** @var array<non-empty-string, array<non-empty-string, non-empty-string>> $maps */
        $maps = is_array($maps) ? $maps : [];

        /** @var mixed $active */
        $active = $theme[ConfigProvider::ACTIVE] ?? null;

        return new ThemeResolver(
            $maps,
            is_string($active) && '' !== $active ? $active : ThemeResolver::DEFAULT_THEME,
        );
    }
}
