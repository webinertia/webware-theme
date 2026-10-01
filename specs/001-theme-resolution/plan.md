# Implementation Plan: Theme Resolution for Webware Packages

**Feature**: `specs/001-theme-resolution/spec.md` · **Branch**: `prototype/resolver` · **Date**: 2026-09-29 · **Revised**: 2026-10-01

> **Read `decisions.md` first.** It holds the current state, the standing rules and the decision log
> (D-001…). Where this plan and a decision disagree, the decision is newer.

## Implementation status (2026-10-01)

| Piece | State |
|---|---|
| `ThemeResolver`, `ThemeResolverFactory`, `AggregateResolverFactory`, `ConfigProvider` wiring | **Built** (`d1c05dc`); 7 unit + 5 integration tests; Mago clean |
| `Theme` / `ThemeInterface`, PSR-4 root derivation, `theme.roots` | **Not built and superseded or open** (D-005, D-006) |
| Theme-aware `asset()` helper (`AssetFactory`) | Not built; `tasks.md` Phase 10 |
| Asset publishing / installer | Not built; requirements in `project-tracking#6` (D-012) |
| Default theme assets and the port of the three components' templates | Open; `tasks.md` Phases 11 and 12 |

## Summary

Ship `webware/webware-theme`: a resolver that answers an address from theme-keyed maps
(`theme.themes[<theme>][<address>]`, active theme injected, `default` as the per-address fallback), a
theme-aware `asset()` helper, and the convention that a component's own default theme is its
`templates/default/<namespace>/` published under its namespace. Packages contribute their default
templates through their `ConfigProvider`; applications contribute theme maps and asset values as
configuration. No middleware, no build step, no database.

One consumer change is a **prerequisite**, because a resolver can only resolve a name it is given: the
package that layers the body and layout (webware-htmx) must name each with one configuration value
(Phase 6, T039–T041 of `tasks.md`, and the required subset of webinertia/webware-htmx#21; the work is
PR `webinertia/webware-htmx#22`). Everything else
in that RFC is enabled by this feature's convention but not required by it.

## Technical Context

- **PHP**: `~8.4.1 || ~8.5.0` (matches the fleet; tooling platform `8.4.99`)
- **Runtime deps**: `laminas/laminas-view ^3.0`, `psr/container ^2.0`. `mezzio/mezzio-laminasviewrenderer`
  and `webware/webware-htmx` stay consumers, not dependencies.
- **Console (conditional)**: if the installer turns out to be a command in this package (T034),
  `webware/webware-console` becomes a dependency — as it is for webware-migration — and the guard rule for
  `Console\` applies: a `final` class named `*Command` carrying
  `#[Symfony\Component\Console\Attribute\AsCommand]`, directly in `Console\`, with its factory in
  `Console\Container\`.
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
| `Laminas\View\Helper\Asset::__invoke()` throws on an unknown name; the class is `final readonly` and is built once by `AssetFactory` from `view_helper_config.asset.resource_map` (not `view_manager.asset`, as earlier drafts said) | `laminas-view/src/Helper/Asset.php`, `Helper/Service/AssetFactory.php`; re-measured 2026-10-01 | Asset names are a fixed vocabulary (FR-009). Theme awareness is a factory override that builds a merged map (D-008), not a subclass |
| `webware/public/.htaccess` serves any existing file, link or **directory** before routing | `webware/public/.htaccess` | A bare `/<theme>/` directory at the web root would shadow a route of that name; assets live under `/theme/<theme>/` (D-007) |
| The ACL page's behaviour is ~400 lines of the IMS app's `public/assets/js/app.js` (from line 301), selecting by `ims-acl-*` ids and classes and building markup strings with them; its styling is 84 rules in `public/assets/css/custom.css`; the component templates contain no script that selects by those names | IMS repo, read-only; 2026-10-01 | A neutral rename must move markup, script and CSS together; the script ships with the component (D-010, D-013) |
| `webware/vendor/webware/webware-theme` is a symlink to the sibling clone and the app's `composer.lock` pins it as a path dist at the prototype commit | `webware/composer.lock`; 2026-10-01 | The app works only where that clone is on `prototype/resolver`; the resolver must reach `1.0.x` by PR |
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
├── decisions.md     # START HERE: state, standing rules, decision log, next actions
├── spec.md          # this feature's requirements
├── plan.md          # this file
├── tasks.md         # dependency-ordered task list
├── research.md      # what was measured, and why the renderer-modifying approach was dropped
├── data-model.md    # entities, the layout on disk, resolution, the configuration shape
└── quickstart.md    # using a theme from both sides: theme author and package author
```

Note: `.specify/` and `/specs/` are **tracked** in this repository (`tasks.md` T026); only the vendored
preset under `.specify/presets/` is ignored.

### Source Code (repository root)

```
src/
├── ConfigProvider.php              # BUILT: registers the resolver and the aggregate factories
├── ThemeInterface.php              # NOT BUILT, open decision D-005
├── Theme.php                       # NOT BUILT, open decision D-005 (the name check is still needed by T043)
├── Resolver/
│   ├── ThemeResolver.php           # BUILT: theme-keyed map lookup, active theme then default
│   └── Container/ThemeResolverFactory.php   # BUILT (and AggregateResolverFactory.php beside it)
├── View/Helper/Container/AssetFactory.php   # PLANNED (T043): theme-aware asset() factory (D-008)
├── Installer/                      # OPEN (D-012): may live in the Webware installer instead
│   ├── ThemeInstaller.php          # creates and manages a theme's assets under public/theme/<theme>/
│   └── Container/ThemeInstallerFactory.php
├── Console/                        # only if the installer is a command here (T034)
│   ├── InstallThemeCommand.php     # final, *Command, #[AsCommand] -- the Console\ guard rule
│   └── Container/InstallThemeCommandFactory.php
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

The implementation is deliberately smaller than the doc set: a contract, a resolver, an installer and
wiring. There is no renderer subclass — `webware-htmx` keeps `View\LaminasRenderer` for body/layout
layering, and this package only answers "which file is this template name?" and "what does a theme's
installed asset set look like?".

## Complexity Tracking

| Deviation | Why | What it costs |
|---|---|---|
| Resolution consults theme-keyed maps rather than the framework's `templates.paths` in provider order | FR-005 (no filesystem walk per lookup) plus the measured asymmetry: integer-keyed path lists append, so the first-registered provider wins a same-named file and a theme could not win | The active theme is a lookup-time fact, no build or scan. The cost: every overridden address must be listed in config (D-002 settled this after the prototype) |
| The asset helper is made theme-aware by replacing its factory, not by subclassing | `Asset` is `final readonly` and built once from a flat map (D-008) | The factory must be registered after `Mezzio\LaminasView\ConfigProvider` and the active theme is fixed per process (no per-request themes) |
| A component's behaviour JavaScript ships with the component, not the theme (D-010) | The ACL page's script selects by the markup's hooks and builds markup strings with them | Components need an asset publishing path (D-011, D-012) the app does not have yet |
| The resolver is a `ResolverInterface` instead of a renderer modification | The previous iteration modified the renderer and pushed paths onto its stack; keeping resolution out of the renderer keeps `webware-htmx` free to own body/layout layering | Resolution and rendering are configured in two places, and their interaction needs an integration test |
| Theme identity is the directory name, not a registry entry | It is the convention that makes a redesign a directory of files, and it keeps the whole thing configuration-only (FR-006) | Two packages cannot ship different themes under the same name, and there is no per-theme metadata beyond the directory |
