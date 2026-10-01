# Data Model: Theme Resolution

**Feature**: `specs/001-theme-resolution/` · **Date**: 2026-09-29

No database, no persistence. Every entity below is either a value object built from configuration or a
service that reads configuration; `spec.md` FR-006 makes that a requirement rather than an implementation
detail.

## Theme

Identity is the directory name. There is no registry and no per-theme metadata file.

| Field | Type | Rules |
|---|---|---|
| `name` | `non-empty-string` | A single path segment: no directory separators, no `..`, not empty. It is used as a directory name under `templates/`, so anything else is a path-escape (T002) |
| `root` | — | **Superseded (D-006):** there is no root field. A theme is a key in `theme.themes` (template overrides) and in `theme.assets` (asset values) |

`default` is the reserved name: every module that ships templates ships `default`, and it is the fallback
consulted after the active theme. It cannot collide with mezzio's internal unnamespaced bucket, which is
the private constant `__DEFAULT__` and unreachable as a template namespace.

## The layout on disk

Every module — a package, or a module the application ships itself (`App` is just one of them, shipped by
`webware/webware`) — keeps its templates as `<module>/templates/<theme>/<namespace>/<name>.phtml`, and a
template is addressed `<namespace>::<name>`. The theme is **not** part of the address.

```
src/App/templates/
├── default/                          # App's default theme
│   ├── app/home-page.phtml                 -> app::home-page.phtml
│   ├── layout/default.phtml                -> layout::default
│   ├── admin/dashboard.phtml               -> admin::dashboard.phtml    (vendor template, overridden here)
│   └── usermanager/profile.phtml           -> user::profile.phtml       (vendor template, overridden here)
└── acme/                             # the acme theme: only what it changes
    ├── app/home-page.phtml
    └── layout/default.phtml

src/ims-store/templates/              # a module the application ships itself
├── default/ims-store/admin-settings.phtml  -> ims-store::admin-settings.phtml
└── acme/ims-store/admin-settings.phtml     # the acme override, inside that module
```

A vendor template comes from the vendor module's own `templates/default/<namespace>/` and is overridden by
any module that places that namespace under its own theme directory — which is how the application
overrides `admin::dashboard.phtml` without touching the package that ships it.

## Resolution

For a namespace, the resolver assembles the paths that namespace is served from — module by module, and
within each module the active theme's directory before `default` — and addresses resolve against that.
Per-template fallback falls out of the ordering: a template the active theme does not ship resolves from
`default`, and the framework's own resolvers take anything not theme-owned at all.

Both questions below are **settled** (D-002, D-003). The list is kept so the alternatives stay on record:

- **Module order** for a namespace served by more than one module — presumably the application's own
  module first.
- **Namespace-keyed paths or theme-keyed maps** — the constructions worth prototyping, since each decides
  whether the active theme is a build-time or a lookup-time fact:
  - *namespace-keyed paths*: mezzio's own `templates.paths` shape, with the active theme's directory
    prepended per namespace (walked per lookup).
  - *theme-keyed maps*: `[theme][address] => path` for every theme at once, with the active theme
    injected into the resolver, which falls back to `default` per address (`research.md` has the lookup
    measurements). Lookup-time theme.
  - *merge-last theme map*: a theme ships its overrides as an address-keyed map and is merged **last**, so
    it wins by the same later-wins rule that makes `templates.map` an override channel today. Every
    address the theme does not list keeps the earlier entry, so per-address fallback is the absence of a
    key — no fallback logic at all — and the runtime stays a plain `TemplateMapResolver`. Build-time
    theme.

  The key of a map is already the address the renderer asks for, which is why the map is the override
  point today; the key of a path list is the namespace, and its integer-keyed lists append in provider
  order, so the first-registered path wins a same-named file.

## Theme root *(superseded, D-006; kept for history)*

A directory a theme resolves against in one module, plus where it came from.

| Field | Type | Source |
|---|---|---|
| `module` | `non-empty-string` | The module contributing it |
| `path` | `non-empty-string` | `<module>/templates/<theme>/`, absolute |
| `origin` | `package` \| `module` | `package` roots are declared by a `ConfigProvider`; `module` roots are derived from a registered PSR-4 namespace (its namespace root's sibling `templates/`) |

Derivation rules for `module` roots (T014): take the namespace root directory, use its sibling
`templates/`; skip `autoload-dev` prefixes; handle a prefix that maps to more than one directory by
emitting a root per directory.

## Configuration shape

The package reads one configuration block; components contribute their own roots and their default
theme's asset names through the same block, so an application overrides by writing a value rather than
by controlling provider order.

```
theme
├── active            # the theme name; absent means `default`
├── themes            # map<theme, map<address, absolute path>>: overrides only (BUILT)
└── assets            # map<theme, map<asset name, relative path | absolute URL>> (PLANNED, T042)
view_helper_config
└── asset               # laminas-view's own key; its stock AssetFactory reads it (D-009)
    └── resource_map    # flat map<asset name, url>; the theme-aware factory builds it at runtime (D-008)
```

Merge behaviour this shape depends on, measured in `ConfigAggregator::mergeArray()`: string keys replace
with later-wins (so `themes` and `assets` overrides are automatic), while integer keys append
(so an integer-keyed list accumulates in provider order and cannot be reordered by a later provider,
which is why this shape has none).

## Resolver

| Responsibility | Detail |
|---|---|
| Input | a namespaced address (`app::home-page.phtml`, `layout::default`, `admin::dashboard.phtml`) |
| Output | an absolute path, or `false` so the aggregate continues |
| Build | the name→path map is built once, at construction |
| Memoization | resolved names are remembered per instance; nothing above memoizes (`research.md`) |
| Failure | never throws for an unknown name — throwing would break the aggregate's fallback |

## Theme installer

Creating and managing a theme's asset files is the installer's job, not the developer's: a redesign does
not hand-write `public/theme/<theme>/…` any more than it hand-writes a lock file.

| Responsibility | Detail |
|---|---|
| Create | materialise a theme's assets under `public/theme/<theme>/` from the theme's own source |
| Manage | keep them in step with what the theme ships, including removing what it no longer ships |
| Scaffold | seed a new theme from `default`, so a new `templates/<name>/` plus its assets start from something that renders |

Still open: how far "manage" goes (create only, update on change, remove on uninstall), and whether the
installer is a console command in this package or lives in the application. If it is a command here, the
fleet's console contract applies — registered under `Webware\Console\ConsoleInterface::class` with
lazy resolution — which makes `webware/webware-console` a dependency of this package, as it is for
`webware/webware-migration`.

## Asset name

Asset files live under `public/` and **never** inside a theme's `templates/` root, which is not a served
location. The layout is settled (D-007):

```
public/theme/<theme>/{css,js,img,fonts}/...     URL /theme/<theme>/...      theme assets
public/component/<component>/...               URL /component/<component>/...   component assets (proposed, D-011)
```

One directory name per kind: `img`, not `images`. A theme name is a single path segment.

| Field | Type | Rules |
|---|---|---|
| `name` | `non-empty-string` | Defined by a component; a fixed vocabulary. The `Asset` helper throws on anything unknown |
| `value` | `non-empty-string` | A path relative to the defining theme's directory (`css/theme.css`) or an absolute URL. A theme overrides the value, including with a CDN URL for a framework stylesheet |
