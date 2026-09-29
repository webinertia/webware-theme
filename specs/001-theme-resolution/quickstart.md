# Quickstart: Theme Resolution

**Feature**: `specs/001-theme-resolution/` · **Date**: 2026-09-29

## For a designer or developer refreshing a client's site

A theme is a directory. Nothing is compiled, published or registered.

```
templates/acme/                 # the theme; `acme` is the name
├── layout/default.phtml        # overrides the package's layout
├── partials/nav.phtml          # overrides one partial
└── assets/acme.css             # this theme's styling
```

1. Create the directory and copy into it only what the redesign changes. Anything you do not copy
   continues to come from the component's `default` theme.
2. Make the assets reachable: add their entries to the resource map (names are the ones the components
   already use; you are changing values, not inventing names).
3. Activate it:

```php
// config/autoload/theme.global.php
return [
    'theme' => [
        'active' => 'acme',
        'roots'  => [
            ['component' => 'app', 'path' => __DIR__ . '/../../templates/acme'],
        ],
    ],
];
```

4. Clear the aggregated config cache — the active theme is read from configuration, and a cached
   configuration will keep serving the previous theme.

## For a package author

Put markup under `templates/default/` and publish the root from your `ConfigProvider`. You never
mention themes in your own code:

```
your-package/
├── src/ConfigProvider.php       # publishes templates/default as this package's root
└── templates/default/
    ├── layout/default.phtml
    └── body.phtml
```

An application that installs your package gets a working interface with no theme configuration. If you
also want to ship an alternate theme, add `templates/<name>/` beside `default` — same convention, no
registration.

Rules that keep this working across packages:

- Template names are **theme-less**. Name a template for what it is (`body`, `partials/nav`), never for
  a theme, so that component-to-component references survive a theme change.
- A template that you ship for others to override must be published under a name they can predict;
  otherwise they cannot override it.
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
