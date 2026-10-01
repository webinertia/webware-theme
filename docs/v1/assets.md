# Assets

laminas-view's `asset()` helper maps a fixed name to a URL and throws on a name it does not know. It is
`final readonly` and has no idea of a theme, so this package replaces its **factory**, not the helper.
`Webware\Theme\View\Helper\Container\AssetFactory` is registered under `view_helpers.factories` and
returns the stock `Laminas\View\Helper\Asset`.

```php
<link rel="stylesheet" href="<?= $this->asset('theme.css') ?>">
```

## How the map is built

1. Start from `view_helper_config.asset.resource_map`, so an application's existing entries keep working.
2. Apply `theme.assets.default`, then `theme.assets.<active>` over it.
3. For each value:
   - starting with `https://`, `http://` or `//`: used unchanged;
   - anything else: `/theme/<theme>/<value>`, with leading slashes on the value removed.

The `<theme>` in the URL is the theme that **defined** the value, so a name the active theme does not
define still points at the `default` theme's file. Fallback is per name.

## Where the files go

```
public/theme/<theme>/css/...
public/theme/<theme>/js/...
public/theme/<theme>/img/...
public/theme/<theme>/fonts/...
```

The prefix `/theme/` is deliberate: the web server serves existing files and directories before it routes,
so a directory named after a theme at the web root could shadow a route of the same name.

A component's own assets are planned per theme under `public/theme/<theme>/component/<component>/`
(decision D-011). Publishing files into `public/` is the composer plugin installer's job
(`webinertia/project-tracking#6`) and is not part of this package; until it exists, place the files by
hand.

## Theme names

A theme name that becomes part of a URL must be a single path segment: it matches
`^[A-Za-z0-9][A-Za-z0-9_-]*$`. Anything else, for example `..`, `a/b` or `.hidden`, makes the helper's
factory throw `Webware\Theme\Exception\InvalidThemeNameException`. Both the `default` theme and the active
theme are checked.

## Unknown names

The stock helper throws `Laminas\View\Exception\InvalidArgumentException` for a name no theme defines.
That is intended: an asset a template asks for must exist.
