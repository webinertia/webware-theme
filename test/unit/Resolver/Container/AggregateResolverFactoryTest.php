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

namespace WebwareTest\Theme\Resolver\Container;

use Laminas\Stdlib\PriorityQueue;
use Laminas\View\Resolver\AggregateResolver;
use Laminas\View\Resolver\TemplateMapResolver;
use Mezzio\LaminasView\NamespacedPathStackResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Webware\Theme\Resolver\Container\AggregateResolverFactory;
use Webware\Theme\Resolver\ThemeResolver;

#[CoversClass(AggregateResolverFactory::class)]
#[CoversMethod(AggregateResolverFactory::class, '__invoke')]
final class AggregateResolverFactoryTest extends TestCase
{
    private const DEFAULT_APP = __DIR__ . '/../../../integration/TestAssets/themes/default/app';

    #[Test]
    public function theNamespacedPathStackAnswersWhatNeitherElseCarries(): void
    {
        $aggregate = $this->build([], []);

        static::assertStringEndsWith(
            '/themes/default/app/home-page.phtml',
            (string) $aggregate->resolve('app::home-page.phtml'),
        );
    }

    #[Test]
    public function theResolversAreAttachedAtTheDocumentedPriorities(): void
    {
        $queue = $this->build([], [])->getIterator();
        static::assertInstanceOf(PriorityQueue::class, $queue);

        $priorities = [];
        foreach ($queue->toArray(PriorityQueue::EXTR_BOTH) as $entry) {
            $priorities[$entry['data']::class] = $entry['priority'];
        }

        static::assertSame(
            [
                ThemeResolver::class               => 100,
                TemplateMapResolver::class         => 50,
                NamespacedPathStackResolver::class => 1,
            ],
            $priorities,
        );
    }

    #[Test]
    public function theTemplateMapIsConsultedBeforeThePathStack(): void
    {
        $aggregate = $this->build([], ['app::home-page.phtml' => '/map.phtml']);

        static::assertSame('/map.phtml', $aggregate->resolve('app::home-page.phtml'));
    }

    #[Test]
    public function theThemeResolverIsConsultedBeforeTheTemplateMap(): void
    {
        $aggregate = $this->build(['app::home-page.phtml' => '/theme.phtml'], ['app::home-page.phtml' => '/map.phtml']);

        static::assertSame('/theme.phtml', $aggregate->resolve('app::home-page.phtml'));
    }

    /** @param array<non-empty-string, non-empty-string> $theme */
    private function build(array $theme, array $map): AggregateResolver
    {
        $services = [
            ThemeResolver::class               => new ThemeResolver(['default' => $theme]),
            TemplateMapResolver::class         => new TemplateMapResolver($map),
            NamespacedPathStackResolver::class => new NamespacedPathStackResolver(['script_paths' => [
                'app' => self::DEFAULT_APP,
            ]]),
        ];

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(static fn(string $id): mixed => $services[$id]);

        $aggregate = new AggregateResolverFactory()->__invoke($container);
        static::assertInstanceOf(AggregateResolver::class, $aggregate);

        return $aggregate;
    }
}
