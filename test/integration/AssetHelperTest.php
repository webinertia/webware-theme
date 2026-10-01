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

use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Helper\Asset;
use Laminas\View\HelperPluginManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Theme\ConfigProvider;
use Webware\Theme\View\Helper\Container\AssetFactory;

/**
 * The asset helper is resolved the way a view does, through the real plugin manager configured by this
 * package's own provider, and switching the active theme changes what a name resolves to.
 */
#[CoversClass(AssetFactory::class)]
#[CoversMethod(AssetFactory::class, '__invoke')]
final class AssetHelperTest extends TestCase
{
    #[Test]
    public function switchingTheActiveThemeChangesTheValue(): void
    {
        static::assertSame('/theme/acme/css/acme.css', $this->helper('acme')('theme.css'));
    }

    #[Test]
    public function theDefaultThemeServesTheDefaultValue(): void
    {
        static::assertSame('/theme/default/css/theme.css', $this->helper('default')('theme.css'));
    }

    private function helper(string $active): Asset
    {
        $container = new ServiceManager();
        $container->setService('config', [
            ConfigProvider::THEME => [
                ConfigProvider::ACTIVE => $active,
                ConfigProvider::ASSETS => [
                    'default' => ['theme.css' => 'css/theme.css'],
                    'acme'    => ['theme.css' => 'css/acme.css'],
                ],
            ],
        ]);

        $plugins = new HelperPluginManager($container, new ConfigProvider()->__invoke()['view_helpers']);

        $asset = $plugins->get('asset');
        static::assertInstanceOf(Asset::class, $asset);

        return $asset;
    }
}
