# Quickstart: Theme Resolution

**Feature**: `specs/001-theme-resolution/` · **Date**: 2026-09-29

## For a designer or developer refreshing a client's site

A theme is a key in the `theme.themes` configuration: a map from template address to the file that
replaces it. Its assets are files under `public/theme/<theme>/`. Nothing is compiled or scanned; a
template path in the map is used as written and is not checked on disk (D-017).

```
src/App/templates/
├── default/                          # App's own theme, shipped with it
│   ├── app/home-page.phtml                 -> app::home-page.phtml
│   └── layout/default.phtml                -> layout::default
└── acme/                             # the theme; only what the redesign changes
    ├── app/home-page.phtml                 -> app::home-page.phtml
    └── layout/default.phtml                -> layout::default

public/theme/acme/                    # this theme's assets, where they can be served
└── css/acme.css
```

Addresses stay mezzio's namespaced ones (`<namespace>::<name>`); the theme selects the first directory
segment and is never part of the address. Templates and assets live in different places because they are
served differently — `templates/` is never a served location, so nothing under it can be requested by a
browser.

1. Put the theme's templates in a directory of your choosing (the layout above is the convention) and
   list each address the theme overrides in `theme.themes.<name>`. Anything not listed continues to
   come from `default`, address by address.
2. Its assets go under `public/theme/<theme>/{css,js,img,fonts}/`, and their **names** are pointed at
   them in `theme.assets.<theme>` (planned, T042; the asset helper does not read it yet) — the names
   are the ones the components already use; you are changing values, not inventing names.
3. Activate it:

```php
// config/autoload/theme.global.php
return [
    'theme' => [
        'active' => 'acme',
        'themes' => [
            'acme' => [
                'app::home-page.phtml' => __DIR__ . '/../../src/App/templates/acme/app/home-page.phtml',
                'layout::default'      => __DIR__ . '/../../src/App/templates/acme/layout/default.phtml',
            ],
        ],
    ],
];
```

4. Clear the aggregated config cache — the active theme is read from configuration, and a cached
   configuration will keep serving the previous theme.

## For a package author

Put markup under `templates/default/<namespace>/` and publish the root from your `ConfigProvider`. You
never mention themes in your own code:

```
your-package/
├── src/ConfigProvider.php                  # publishes templates/ as this package's root
└── templates/default/
    ├── your-namespace/admin-settings.phtml      -> your-namespace::admin-settings.phtml
    └── layout/default.phtml                     -> layout::default
```

Address templates the way mezzio does — `<namespace>::<name>`, where the namespace is one you choose
(`your-namespace`, or a shared one such as `layout`). An application that installs the package gets a
working interface with no theme configuration; if you want to ship an alternate theme, add
`templates/<name>/<namespace>/` beside `default` — same convention, no registration.

Rules that keep this working across packages:

- Addresses are **namespaced and theme-less**. Name a template for what it is (`body`, `layout/default`),
  never for a theme, so component-to-component references survive a theme change.
- A template you ship for others to override must live under a namespace they can predict; otherwise they
  cannot override it.
- The `default` theme may only call helpers that your package's own dependencies register. A package
  cannot call an application helper (this is the defect that moving the IMS shell into the `ims` theme
  fixes).

## For an application switching themes

```php
'theme' => ['active' => 'ims'],
```

Everything not overridden by `ims` falls back per template. There is no per-request theme selection:
the active theme is configuration, and switching it is a configuration change plus a cache clear.

## What a successful run looks like

- A page renders with no theme configured at all (the `default` theme).
- A theme overriding one layout and one partial changes nothing else on the page.
- No template lookup costs a directory walk that a `default`-theme lookup would not have cost.
- The markup a theme does not touch is byte-identical to the markup before the theme existed.
