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

use function is_array;

/**
 * Reads a nested array out of merged configuration, treating anything that is not an array as absent.
 *
 * @internal
 */
final class ConfigPath
{
    /**
     * @return array<array-key, mixed>
     */
    public static function array(mixed $source, string ...$keys): array
    {
        foreach ($keys as $key) {
            /** @var mixed $source */
            $source = is_array($source) ? $source[$key] ?? null : null;
        }

        return is_array($source) ? $source : [];
    }
}
