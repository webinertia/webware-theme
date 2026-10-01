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

namespace Webware\Theme\Resolver;

use Laminas\View\Resolver\ResolverInterface;
use Override;

/**
 * Resolves a namespaced template address against the active theme's map.
 *
 * The name handed in is the address the renderer asks for — `app::home-page`, `layout::default`,
 * `admin::dashboard` — and a map is keyed by exactly that, so a hit is an `isset` and nothing
 * touches the filesystem. An address the active theme does not carry falls back to the `default`
 * theme's entry for the same address, and a name neither theme carries returns `false` so the
 * aggregate resolver continues to the next resolver.
 *
 * @internal
 */
final class ThemeResolver implements ResolverInterface
{
    /**
     * The theme every other theme falls back to. Module directories are named for their theme, so
     * this is also the directory name a module ships when it ships one theme.
     */
    final public const string DEFAULT_THEME = 'default';

    /**
     * @param array<non-empty-string, array<non-empty-string, non-empty-string>> $maps
     *        theme name => template address => absolute path
     * @param non-empty-string $active
     */
    public function __construct(
        private readonly array $maps,
        private readonly string $active = self::DEFAULT_THEME,
    ) {}

    #[Override]
    public function resolve(string $name): string|false
    {
        return (
            $this->maps[$this->active][$name]
                ?? $this->maps[self::DEFAULT_THEME][$name]
                ?? false
        );
    }
}
