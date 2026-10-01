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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Theme\ConfigPath;

#[CoversClass(ConfigPath::class)]
#[CoversMethod(ConfigPath::class, 'array')]
final class ConfigPathTest extends TestCase
{
    #[Test]
    public function aMissingKeyIsAnEmptyArray(): void
    {
        static::assertSame([], ConfigPath::array(['a' => []], 'a', 'missing'));
    }

    #[Test]
    public function aNonArrayAnywhereOnThePathIsAnEmptyArray(): void
    {
        static::assertSame([], ConfigPath::array(['a' => 'x'], 'a', 'b'));
        static::assertSame([], ConfigPath::array('not an array', 'a'));
        static::assertSame([], ConfigPath::array(['a' => 'x'], 'a'));
    }

    #[Test]
    public function returnsTheNestedArrayAtThePath(): void
    {
        $config = ['a' => ['b' => ['c' => 1]]];

        static::assertSame(['c' => 1], ConfigPath::array($config, 'a', 'b'));
    }

    #[Test]
    public function withNoKeysTheSourceArrayIsReturned(): void
    {
        static::assertSame(['a' => 1], ConfigPath::array(['a' => 1]));
    }
}
