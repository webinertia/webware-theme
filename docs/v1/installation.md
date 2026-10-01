# Installation

```bash
composer require webware/webware-theme
```

Requires PHP `~8.4.1 || ~8.5.0`, `laminas/laminas-view` and `mezzio/mezzio-laminasviewrenderer`.

## Register the config provider

`Webware\Theme\ConfigProvider` is declared under `extra.laminas.config-provider`, so a config aggregator
that reads composer's providers picks it up. If you list providers by hand, put it
**after** `Mezzio\LaminasView\ConfigProvider`:

```php
$aggregator = new ConfigAggregator([
    Mezzio\LaminasView\ConfigProvider::class,
    Webware\Theme\ConfigProvider::class, // must come after
    // ...
]);
```

Both providers define `Laminas\View\Resolver\AggregateResolver`, and the later one wins. This package's
factory attaches the theme resolver ahead of mezzio's own, which is the whole point of the package.

## What the provider registers

| Where | Entry |
|---|---|
| `dependencies.factories` | `ThemeResolver` and `AggregateResolver` (replacing mezzio's factory) |
| `view_helpers.factories` | `Laminas\View\Helper\Asset` built by `AssetFactory` |

## Not part of this package yet

Installing a theme's assets into `public/`, and the command and admin widget that create and switch
themes, are specified in `specs/001-theme-resolution/` (tasks T032 to T034 and T065 to T068) and are not
built.
