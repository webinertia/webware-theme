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
| `root` | `non-empty-string` | Absolute path to the theme's directory: `<component>/templates/<name>`, or an application/client module's equivalent |

`default` is the reserved name: every package that ships templates ships `default`, and `default` is the
last root consulted. It cannot collide with mezzio's internal unnamespaced bucket, which is the private
constant `__DEFAULT__` and unreachable as a template namespace.

## Theme root

A directory a theme resolves against, plus where it came from. Roots are contributed in a declared
order; the resolver walks that order per template name.

| Field | Type | Source |
|---|---|---|
| `component` | `non-empty-string` | The declaring package or module |
| `path` | `non-empty-string` | Absolute directory |
| `origin` | `package` \| `module` | `package` roots are declared by a `ConfigProvider`; `module` roots are derived from a registered PSR-4 namespace (its namespace root's sibling `templates/`) |

Derivation rules for `module` roots (T014): take the namespace root directory, use its sibling
`templates/`; skip `autoload-dev` prefixes; handle a prefix that maps to more than one directory by
emitting a root per directory.

## Resolution chain

Applied per template name; the first match wins, and a miss falls through to the next resolver in the
aggregate (which is what makes fallback per template rather than per theme).

| Order | Root | Purpose |
|---|---|---|
| 1 | application theme (`templates/<active>` of the app or client module) | the redesign |
| 2 | component theme (`<component>/templates/<active>`) | a theme that also ships component-specific overrides |
| 3 | component default (`<component>/templates/default`) | what the package shipped |
| 4 | the framework's own resolvers (`templates.map`, then `templates.paths`) | anything not theme-owned |

## Configuration shape

The package reads one configuration block; components contribute their own roots and their default
theme's asset names through the same block, so an application overrides by writing a value rather than
by controlling provider order.

```
theme
├── active            # the theme name; absent means `default`
├── roots             # list<ThemeRoot>, ordered
└── templates         # map<template name, path> contributed by the active theme
view_manager
└── asset               # laminas-view's own key, published by components
    └── resource_map    # map<asset name, url|path>; themes re-value, never introduce
```

Merge behaviour this shape depends on, measured in `ConfigAggregator::mergeArray()`: string keys replace
with later-wins (so `templates` and `resource_map` overrides are automatic), while integer keys append
(so a `roots` list accumulates in provider order and cannot be reordered by a later provider).

## Resolver

| Responsibility | Detail |
|---|---|
| Input | a theme-less template name (`body`, `layout/default`, `partials/nav`) |
| Output | an absolute path, or `false` so the aggregate continues |
| Build | the name→path map is built once, at construction |
| Memoization | resolved names are remembered per instance; nothing above memoizes (`research.md`) |
| Failure | never throws for an unknown name — throwing would break the aggregate's fallback |

## Asset name

| Field | Type | Rules |
|---|---|---|
| `name` | `non-empty-string` | Defined by a component; a fixed vocabulary. The `Asset` helper throws on anything unknown |
| `value` | `non-empty-string` | A URL or a base-path-relative path. A theme overrides the value, including a CDN URL for a framework stylesheet |
