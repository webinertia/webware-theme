# Implementation Plan: Theme Resolution for Webware Packages

**Feature**: `specs/001-theme-resolution/spec.md` · **Branch**: `001-theme-resolution` · **Date**: 2026-09-29

## Summary

Ship `webware/webware-theme`: a theme contract, a resolver that applies an ordered root chain with
per-template fallback, and the convention that a theme is a directory under a package's `templates/`.
Packages contribute roots and asset names through their `ConfigProvider`; applications (and client
modules) contribute theirs as configuration, with module roots derivable from registered PSR-4
namespaces. No middleware, no build step, no database.

## Technical Context

- **PHP**: `~8.4.1 || ~8.5.0` (matches the fleet; tooling platform `8.4.99`)
- **Runtime deps**: `laminas/laminas-view ^3.0`, `psr/container ^2.0`. `mezzio/mezzio-laminasviewrenderer`
  and `webware/webware-htmx` stay consumers, not dependencies.
- **Dev deps**: `webware/webware-tools ^1.0.0-beta.5` (the first release shipping
  `agent-working-agreements.md`), PHPUnit 13.3, Infection, PHPBench, roave BC-check.
- **Tooling**: mago 1.50.0 (pinned by `webware-tools`), four gates in CI
  (`format --check`, `lint`, `analyze`, `guard`), MSI floors 95/95 in `webware-ci.json`.
- **Tests**: unit on the WSL host; integration requires no database for this package
  (`run_integration` stays `false` unless that changes).
- **Storage**: none. Theme configuration is configuration.
- **Target**: `0.1.0`/`1.0.0-alpha` line on `1.0.x`, per the fleet's default branch convention.

### Measured constraints the design answers to

All measured in `webinertia/webware` against the installed `laminas/laminas-view` 3.x and
`mezzio/mezzio-laminasviewrenderer` on 2026-09-29.

| Fact | Source | Consequence |
|---|---|---|
| `TemplateMapResolver::resolve()` is `$this->map[$name] ?? false` — no filesystem call | `laminas-view/src/Resolver/TemplateMapResolver.php` | Map lookup is the resolution mechanism; a theme contributes entries, not paths |
| `TemplatePathStack::resolve()` → `resolveToPath()` creates an `SplFileInfo` and calls `isReadable()` per path, on hits and misses | `laminas-view/src/Resolver/TemplatePathStack.php` | Path stacking is the cost the resolver exists to remove (FR-005) |
| `NamespacedPathStackResolver::resolve()` lazily builds one `TemplatePathStack` per namespace | `mezzio-laminasviewrenderer/src/NamespacedPathStackResolver.php` | One `namespace::name` per theme is one stat list per theme |
| `AggregateResolver::resolve()` `continue`s on `false`, returns the first hit | `laminas-view/src/Resolver/AggregateResolver.php` | Per-template fallback is free between resolvers — the hook the design uses |
| No `ResolveCache` anywhere in `laminas-view` 3.x `src` | grep, 2026-09-29 | Nothing memoizes resolution; the resolver memoizes its own results |
| `ConfigAggregator::mergeArray()`: `is_int($key)` appends; string keys replace and recurse | `laminas-config-aggregator/src/ConfigAggregator.php` | Maps are the override channel (later wins); path lists give the first-registered provider priority |
| `Laminas\View\Helper\Asset::__invoke()` throws on an unknown name; `resource_map` is string-keyed | `laminas-view/src/Helper/Asset.php` | Asset names are a fixed vocabulary; themes re-value names and cannot invent them (FR-009) |
| `LaminasRendererFactory` reads five keys for layout/body (`templates.layout`, `templates.body`, `templates.default_layout`, `templates.default_body`, `view_manager.default_layout`); first non-empty is the layout, last is the body | `webware-htmx/src/View/LaminasRendererFactory.php` | FR-012 collapses this to two theme-resolved names |
| `webware-htmx/templates/body/default.phtml` is the IMS shell and calls `$this->imsMessenger()`, a helper defined nowhere in the app repo or in `webware/vendor` | measured 2026-09-29 | US3 exists: the shell moves to `ims`, `default` becomes helper-clean (FR-013) |

## Constitution Check

No constitution is ratified in this repository — `.specify/memory/constitution.md` is the untouched
init template (`[PROJECT_NAME]`, `[PRINCIPLE_1_NAME]`, … placeholders intact). There are therefore no
project principles to gate this plan against. The gates that do apply, and that any implementation
must pass: the fleet agent working agreements, the four Mago gates, MSI 95/95 from `webware-ci.json`,
and the package's own coverage metadata requirements.

## Project Structure

### Documentation (this feature)

```
specs/001-theme-resolution/
├── spec.md          # this feature's requirements
├── plan.md          # this file
├── tasks.md         # dependency-ordered task list
└── research.md      # resolution-cost findings and the decisions they forced
```

Note: `.specify/` and `/specs/` are ignored by this repository's `.gitignore` (inherited from
`repo-template`), so these documents are local unless that is revisited.

### Source Code (repository root)

```
src/
├── ConfigProvider.php              # wiring entry point; already present, currently empty
├── ThemeInterface.php              # a theme: name + root + optional overrides
├── Theme.php                       # value object implementation (final readonly)
├── Resolver/
│   ├── ThemeResolver.php           # ordered root chain + per-template fallback, memoized
│   └── Container/ThemeResolverFactory.php
└── Exception/
    └── ThemeNotConfiguredException.php
templates/                          # only if this package ships markup of its own
test/
├── unit/
│   └── Resolver/ThemeResolverTest.php
└── integration/
    ├── ConfigProviderWiringTest.php     # already present; asserts config-provider ↔ namespace
    └── ThemeResolutionTest.php          # real template files across two roots and a fallback
```

The implementation is deliberately smaller than the doc set: a contract, a resolver, and wiring. There
is no renderer subclass — `webware-htmx` keeps `View\LaminasRenderer` for body/layout layering, and
this package only answers "which file is this template name?".

## Complexity Tracking

| Deviation | Why | What it costs |
|---|---|---|
| Theme roots contribute **map entries** rather than more `templates.paths` (the standard mezzio route) | FR-005: a path is a stat per lookup per path, and there is no resolver cache to absorb it in laminas-view 3 | Roots must be enumerable at map-build time (a directory scan or a manifest), and a theme that changes on disk needs the map rebuilt |
| The resolver is a `ResolverInterface` instead of a renderer modification | The previous iteration modified the renderer and pushed paths onto its stack; keeping resolution out of the renderer keeps `webware-htmx` free to own body/layout layering | Resolution and rendering are configured in two places, and their interaction needs an integration test |
| Theme identity is the directory name, not a registry entry | It is the convention that makes a redesign a directory of files, and it keeps the whole thing configuration-only (FR-006) | Two packages cannot ship different themes under the same name, and there is no per-theme metadata beyond the directory |
