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

namespace Webware\Theme;

use Laminas\View\Resolver\AggregateResolver;
use Webware\Theme\Resolver\Container\AggregateResolverFactory;
use Webware\Theme\Resolver\Container\ThemeResolverFactory;
use Webware\Theme\Resolver\ThemeResolver;

/**
 * Wiring entry point for the package.
 *
 * Declared under `extra.laminas.config-provider` in composer.json, so a consumer's config aggregator
 * merges this without any further registration.
 *
 * Merge this provider **after** `Mezzio\LaminasView\ConfigProvider`: both own
 * `AggregateResolver::class`, the later provider wins, and the theme being consulted first is the
 * whole point of the package.
 *
 * @type ThemeConfig array{
 *     active?: non-empty-string,
 *     themes?: array<non-empty-string, array<non-empty-string, non-empty-string>>,
 * }
 * @type DependenciesConfig array{
 *     factories: array<class-string, class-string>,
 * }
 * @type ProviderConfig array{
 *     dependencies: DependenciesConfig,
 * }
 * @internal
 */
final class ConfigProvider
{
    /** @return DependenciesConfig */
    private function getDependencies(): array
    {
        return [
            'factories' => [
                ThemeResolver::class     => ThemeResolverFactory::class,
                AggregateResolver::class => AggregateResolverFactory::class,
            ],
        ];
    }

    /** @return ProviderConfig */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }
}
