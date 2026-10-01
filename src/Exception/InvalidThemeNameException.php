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

namespace Webware\Theme\Exception;

use InvalidArgumentException;

/**
 * A theme name that is not a single path segment, so it could not be used to build a safe URL.
 *
 * @internal
 */
final class InvalidThemeNameException extends InvalidArgumentException {}
