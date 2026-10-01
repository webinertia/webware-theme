# Template resolution

`Webware\Theme\Resolver\ThemeResolver` is a `Laminas\View\Resolver\ResolverInterface`. It does one array
lookup and never touches the filesystem:

```
maps[active][address]  ??  maps['default'][address]  ??  false
```

Returning `false` lets the aggregate resolver continue to the next resolver.

## Order

`AggregateResolverFactory` builds the aggregate with the resolvers mezzio would have had, and attaches the
theme resolver in front:

| Priority | Resolver |
|---|---|
| 100 | `ThemeResolver` |
| 50 | `Laminas\View\Resolver\TemplateMapResolver` (`templates.map`) |
| 1 | `Mezzio\LaminasView\NamespacedPathStackResolver` (`templates.paths`) |

So a theme override wins, a component's own `templates.map` entry comes next, and its path stack is the
last resort. A component therefore keeps working with no theme configuration at all.

## Writing a theme

1. Put the theme's templates anywhere; the convention is `templates/<theme>/<namespace>/<name>.phtml`.
2. List each overridden address in `theme.themes.<theme>` (see [configuration](configuration.md)).
3. Set `theme.active`.

Only the addresses you list change. For the rest, `default` is used.

## What it is not

- It does not stack directories or scan for files, so a missing file is not detected at resolution time.
- It does not select a theme per request. The active theme is configuration.
