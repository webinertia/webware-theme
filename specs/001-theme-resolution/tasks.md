# Tasks: Theme Resolution for Webware Packages

**Input**: `specs/001-theme-resolution/spec.md`, `plan.md`

**Feature**: `001-theme-resolution` · **Package**: `webware/webware-theme` · **Namespace**: `Webware\Theme`

Notes that apply to every task: `mago fmt` before any commit; all four gates clean
(`format --check`, `lint`, `analyze`, `guard`); every test class carries `#[CoversClass]` and
`#[CoversMethod]` with the matching imports (`requireCoverageMetadata="true`); MSI gates are 95/95 from
`webware-ci.json` and are not a per-task parameter.

## Phase 1 — Theme contract

- [ ] T001 Define `ThemeInterface` in `src/ThemeInterface.php`: the name, the root it resolves from,
      and nothing else. A theme is a directory; identity is its name (`@api`, since consumers
      reference it)
- [ ] T002 Implement `Theme` in `src/Theme.php` as a `final readonly` value object with a
      `fromArray`-style named constructor; validate the name as a single path segment (no separators,
      no `..`) so a configuration value can never escape its root
- [ ] T003 Define the theme root contract: how a package declares `templates/<theme>` as a root, as a
      docblock `@type` alias in `ConfigProvider` in the house style (`@type`, not `@phpstan-type`)

## Phase 2 — Resolver

- [ ] T004 Implement `Resolver\ThemeResolver` in `src/Resolver/ThemeResolver.php`, implementing
      `Laminas\View\Resolver\ResolverInterface`. It MUST return `false` — never throw — for a name it
      cannot resolve, so `AggregateResolver` continues to the next resolver
- [ ] T005 Apply the ordered root chain (application theme → component theme → component default) and
      return the first match, so fallback is per template and not per theme (FR-004)
- [ ] T006 Resolve from a map built once, not by walking directories per lookup (FR-005): build the
      map when the resolver is constructed, and memoize resolved names in the instance
- [ ] T007 Build the map without touching the filesystem where the configuration already names the
      templates; where a root must be enumerated, scan it once at build time
- [ ] T008 Decide and document the behaviour when a map entry points at a file that does not exist:
      either trust the entry (a build-time artefact) or stat-check it and fall through. Record the
      choice and its cost in `plan.md` Complexity Tracking
- [ ] T009 Register the resolver in `src/Resolver/Container/ThemeResolverFactory.php` and wire it to
      the aggregate ahead of the map/path resolvers, so an active theme wins over package defaults

## Phase 3 — Configuration

- [ ] T010 Define the configuration shape (active theme + roots + per-component roots) as a docblock
      `@type` alias, with `ConfigProvider` publishing the package's own defaults
- [ ] T011 Read configuration only — no container bootstrapping at render time, no database access,
      at boot or at render (FR-006)
- [ ] T012 Select the active theme from configuration alone (FR-008) and default to `default` when
      nothing is configured, so an application with no theme configuration renders (US1 scenario 3)
- [ ] T013 Document that a cached aggregated config must be cleared for a theme switch to take effect
      (edge case in `spec.md`)
- [ ] T014 Derive roots for application and client modules from their registered PSR-4 namespaces,
      taking the namespace root's sibling `templates/` directory; skip `autoload-dev` prefixes and
      handle a prefix that maps to more than one directory (FR-011)

## Phase 4 — Asset names

- [ ] T015 Define the resource-map contribution: the package and every component publish
      `view_manager.asset.resource_map` entries; a theme re-values names and never introduces them
      (FR-009, and the `Asset` helper throws on an unknown name). Settle the exact asset path shape and
      filenames under `public/theme/<theme>/` while doing so — assets are served from there, never from
      a theme's `templates/` root
- [ ] T016 Contribute the default theme's entries from `ConfigProvider` so that an application with no
      theme configuration resolves every asset name the shipped templates use

## Phase 4b — Theme installer (FR-015)

Task IDs are assigned in the order they were added, so this phase carries the next free numbers rather
than displacing the ones below it.

- [ ] T032 Implement the theme installer: given a theme, materialise its assets under
      `public/theme/<theme>/` from the theme's own source. Nothing here is a build step — it is the one
      place that writes into a served location
- [ ] T033 Decide and record how far "manage" goes: create only, update when the theme's assets change,
      and what happens on uninstall. Whichever way it goes, the installer owns it rather than leaving
      stale files in `public/`
- [ ] T034 Decide whether the installer is a console command in this package (registered under
      `Webware\Console\ConsoleInterface::class`, which makes `webware/webware-console` a dependency, as
      it is for webware-migration) or an application-level command. Record the choice in `plan.md`

## Phase 5 — The convention in the packages

- [ ] T017 Define the `templates/<theme>/` layout convention for packages and write it into the
      package README: a theme is a directory named for the theme, inside `templates/`
- [ ] T018 Confirm the convention against an existing package (webware-navigation's templates) and
      record any divergence rather than moving files in a package outside this feature's scope

## Phase 6 — The htmx configuration prerequisite (FR-012)

- [ ] T019 Make webware-htmx read **one key per value** for the body and the layout, whose values are
      theme-resolvable template names, replacing the five-key lookup and the package-published
      `templates.map['body::default']` entry. This is the only part of webinertia/webware-htmx#21 this
      feature needs: without it a theme has no name to resolve for the body
- [ ] T020 Integration-test that the body name resolves **through the resolver** rather than through a map
      entry the package publishes itself, and that a theme overriding only the layout still gets the
      default body
- [ ] T021 Keep webware-htmx free of a dependency on this package: it reads the configured name and hands
      it to the aggregate resolver, whichever package supplies it

## Phase 7 — Consumer-side work, deliberately not in this list

Everything else in webinertia/webware-htmx#21 is work the convention *enables* but does not depend on:

- Extracting the IMS shell out of `webware-htmx/templates/body/default.phtml` into an `ims` theme, and
  shipping a helper-clean `default` body in its place
- Correcting webware-htmx's override documentation (its README states the opposite of how the config
  aggregator merges)
- Renderer packaging, the `TemplateRendererInterface` alias, and how a team on another client-scripting
  approach is served

## Phase 8 — Tests

- [ ] T022 Unit: `test/unit/Resolver/ThemeResolverTest.php` — a theme hit, a per-template fallback to
      `default`, an unknown name returning `false`, and name validation rejecting separators
- [ ] T023 Unit: the root-chain order, including the case where two roots ship the same template name
- [ ] T024 Integration: real template files across two roots, proving fallback and that resolution
      does not scan per lookup
- [ ] T025 Keep `test/integration/ConfigProviderWiringTest.php` green: it asserts
      `extra.laminas.config-provider` agrees with the namespace

## Phase 9 — Spec-kit tracking

- [ ] T026 Keep `.specify/` and `/specs/` tracked in this repository (deliberate divergence from the
      preset's alignment tasks, which add both to `.gitignore`): this component's development is
      spec-driven, so the scaffolding and the specs live and evolve with it
- [ ] T027 Keep the vendored preset copy (`.specify/presets/`) local via `.specify/.gitignore`; it is
      released content shipped in `webware/webware-tools/presets/`

## Verification

- [ ] T028 `mago format --check && mago lint && mago analyze && mago guard` — all four clean
- [ ] T029 `composer test`, `composer test-integration`, `composer test-coverage`,
      `composer mutation-test` — 100% line coverage, MSI at or above 95
- [ ] T030 Render a page in an application with the packages installed and no theme configured (SC-003,
      SC-006), then activate a theme that overrides one layout and confirm everything else falls back
- [ ] T031 Push the branch, open the PR, and confirm the required workflow's checks are green —
      which requires `ci-target` to be set on the repository first
