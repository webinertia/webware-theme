# Tasks: Theme Resolution for Webware Packages

**Input**: `specs/001-theme-resolution/spec.md`, `plan.md`

**Feature**: `001-theme-resolution` · **Package**: `webware/webware-theme` · **Namespace**: `Webware\Theme`

**Status key** (updated 2026-10-01 against commit `d1c05dc` on `prototype/resolver`): `[X]` is done or
**Superseded** (a decision replaced it; do not build it), `[ ]` is open. A bold note after the ID says why.
The decisions behind every note are in `decisions.md` (IDs `D-001`…); read it first, including its
standing rules. Task IDs are never reused: the next free ID is **T070**.

Notes that apply to every task: `mago fmt` before any commit; all four gates clean
(`format --check`, `lint`, `analyze`, `guard`); every test class carries `#[CoversClass]` and
`#[CoversMethod]` with the matching imports (`requireCoverageMetadata="true`); MSI gates are 95/95 from
`webware-ci.json` and are not a per-task parameter.

## Phase 1 — Theme contract

- [X] T001 **Dropped (D-005).** No `ThemeInterface`; config key constants instead (T062). Original text: define `ThemeInterface` in `src/ThemeInterface.php`: the name, the root it resolves from,
      and nothing else. A theme is a directory; identity is its name (`@api`, since consumers
      reference it)
- [X] T002 **Dropped (D-005).** The single-segment name check moves to T043 / T063. Original text: implement `Theme` in `src/Theme.php` as a `final readonly` value object with a
      `fromArray`-style named constructor; validate the name as a single path segment (no separators,
      no `..`) so a configuration value can never escape its root
- [X] T003 **Superseded (D-006, D-003).** Define the theme root contract: how a package declares `templates/<theme>` as a root, as a
      docblock `@type` alias in `ConfigProvider` in the house style (`@type`, not `@phpstan-type`)

## Phase 2 — Resolver

- [X] T004 **Built** (`ThemeResolver`, 7 unit tests). Implement `Resolver\ThemeResolver` in `src/Resolver/ThemeResolver.php`, implementing
      `Laminas\View\Resolver\ResolverInterface`. It MUST return `false` — never throw — for a name it
      cannot resolve, so `AggregateResolver` continues to the next resolver
- [X] T005 **Superseded (D-002, D-003).** Build, per namespace, the paths that namespace is served from — module by module, and within
      each module the active theme's directory before `default` — so fallback is per template and not per
      theme (FR-004)
- [X] T006 **Done by D-002:** the maps are config arrays, the lookup is an array access, nothing to memoize. Resolve from a map built once, not by walking directories per lookup (FR-005): build the
      map when the resolver is constructed, and memoize resolved names in the instance
- [X] T007 **Superseded (D-002):** the resolver never touches the filesystem; maps are declared in config. Build the map without touching the filesystem where the configuration already names the
      templates; where a root must be enumerated, scan it once at build time
- [X] T008 **Decided (D-017):** trust the entry, no stat-check. Decide and document the behaviour when a map entry points at a file that does not exist:
      either trust the entry (a build-time artefact) or stat-check it and fall through. Record the
      choice and its cost in `plan.md` Complexity Tracking
- [X] T009 **Built** (`ThemeResolverFactory`, `AggregateResolverFactory`: theme 100, map 50, path stack 1). Register the resolver in `src/Resolver/Container/ThemeResolverFactory.php` and wire it to
      the aggregate ahead of the map/path resolvers, so an active theme wins over package defaults

## Phase 2b — Prototype the construction before committing to it

Next free IDs, following the convention above. Both shapes hang off two measured facts: a map's key is the
address the renderer asks for (`app::home-page`), and the resolver gets a factory, so the active theme can
be injected from configuration.

- [ ] T035 **Not run** (D-002); revisit only if a requirement the map cannot meet appears. Prototype **namespace-keyed paths** (mezzio's `templates.paths`, the active theme's directory
      prepended per namespace). Measure the lookup cost with a theme installed, and confirm the ordering
      hazard: integer-keyed lists append in provider order, so the first-registered path wins a
      same-named file, which is the opposite of what a theme needs
- [X] T036 **Built and chosen (D-002).** Prototype **theme-keyed maps** (`[theme][address] => path` for every theme at once, active
      theme injected into the resolver, `default` as the per-address fallback). Confirm the active theme
      becomes a lookup-time fact, and measure what building the maps costs (directory scan or manifest)
- [ ] T037 **Not run** (D-002). Prototype **merge-last theme map**: a theme ships its overrides as an address-keyed map, and a
      provider or post-processor registered last merges it over the rest, so the theme wins by the same
      rule that makes `templates.map` an override channel today — and every address the theme does not
      list keeps its earlier entry, which is per-address fallback for free. Runtime stays a plain
      `TemplateMapResolver`: no custom resolver, no stats. Confirm what the rebuild costs at config time
      and whether the config cache absorbs it
- [X] T038 **Recorded in `decisions.md` D-002.** Write down which won and why (build-time theme vs lookup-time theme is the real fork), then
      fold it into the resolver tasks above rather than leaving two constructions in the code

## Phase 3 — Configuration

- [X] T010 **Built** (`@type ThemeConfig`: `active`, `themes`; the `roots` list is gone, D-006). Define the configuration shape (active theme + roots + per-component roots) as a docblock
      `@type` alias, with `ConfigProvider` publishing the package's own defaults
- [X] T011 **Built.** Read configuration only — no container bootstrapping at render time, no database access,
      at boot or at render (FR-006)
- [X] T012 **Built** (`theme.active` absent or empty means `default`; test `defaultsToTheDefaultThemeWhenNoneIsConfigured`). Select the active theme from configuration alone (FR-008) and default to `default` when
      nothing is configured, so an application with no theme configuration renders (US1 scenario 3)
- [ ] T013 **Open.** Document that a cached aggregated config must be cleared for a theme switch to take effect
      (edge case in `spec.md`)
- [X] T014 **Superseded (D-006).** Derive roots for application and client modules from their registered PSR-4 namespaces,
      taking the namespace root's sibling `templates/` directory; skip `autoload-dev` prefixes and
      handle a prefix that maps to more than one directory (FR-011)

## Phase 4 — Asset names

- [X] T015 **Superseded by Phase 10 (T042–T048), D-007…D-009.** Define the resource-map contribution: the package and every component publish
      `view_helper_config.asset.resource_map` entries; a theme re-values names and never introduces them
      (FR-009, and the `Asset` helper throws on an unknown name). Settle the exact asset path shape and
      filenames under `public/theme/<theme>/` while doing so — assets are served from there, never from
      a theme's `templates/` root
- [X] T016 **Superseded by Phase 10 (T042–T048).** Contribute the default theme's entries from `ConfigProvider` so that an application with no
      theme configuration resolves every asset name the shipped templates use

## Phase 4b — Theme installer (FR-015)

Task IDs are assigned in the order they were added, so this phase carries the next free numbers rather
than displacing the ones below it.

- [ ] T032 **Re-scoped (D-012): installing belongs to the composer plugin installer** (`project-tracking#6`), not this package. Original text: implement the theme installer: given a theme, materialise its assets under
      `public/theme/<theme>/` from the theme's own source. Nothing here is a build step — it is the one
      place that writes into a served location
- [ ] T033 **Open; requirements are now in `project-tracking#6`** (symlink in dev, copy in prod, idempotent, removes what a package stopped shipping). Decide and record how far "manage" goes: create only, update when the theme's assets change,
      and what happens on uninstall. Whichever way it goes, the installer owns it rather than leaving
      stale files in `public/`
- [ ] T034 **Re-scoped (D-012).** The *installer* is the composer plugin. The console command is for creating a theme from `default`, registering it and switching the active theme (T065–T067). Whether that command lives in this package (registered under
      `Webware\Console\ConsoleInterface::class`, which makes `webware/webware-console` a dependency, as
      it is for webware-migration) or elsewhere is still to record in `plan.md`. Original text: decide whether the installer is a console command in this package (registered under

## Phase 5 — The convention in the packages

- [ ] T017 Define the layout convention and write it into the package README:
      `<module>/templates/<theme>/<namespace>/<name>.phtml`, addressed as `<namespace>::<name>` — a theme
      is a directory of namespace directories, and it is never part of the address
- [ ] T018 Confirm the convention against a package with existing templates (webware-navigation,
      webware-htmx) and record any divergence rather than moving files in a package outside this
      feature's scope
- [X] T019 **Resolved by design (D-002, D-006):** maps are config, so precedence is the config merge (later wins), not a module order. Settle the cross-module question the convention leaves open: when more than one module serves
      the same namespace, which module's path is consulted first (record it in `plan.md`)

## Phase 6 — The htmx configuration prerequisite (FR-012)

- [ ] T039 **In progress: `webinertia/webware-htmx#22` is open** (merge state was `UNKNOWN` on 2026-10-01, re-check). Make webware-htmx read **one key per value** for the body and the layout, whose values are
      theme-resolvable template names, replacing the five-key lookup and the package-published
      `templates.map['body::default']` entry. This is the only part of webinertia/webware-htmx#21 this
      feature needs: without it a theme has no name to resolve for the body
- [ ] T040 **Open.** Integration-test that the body name resolves **through the resolver** rather than through a map
      entry the package publishes itself, and that a theme overriding only the layout still gets the
      default body
- [ ] T041 **Open.** Keep webware-htmx free of a dependency on this package: it reads the configured name and hands
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

- [ ] T022 **Partly met:** theme hit, per-address fallback and unknown-returns-`false` are tested; name validation (separators) is not, and belongs to T043. Unit: `test/unit/Resolver/ThemeResolverTest.php` — a theme hit, a per-template fallback to
      `default`, an unknown name returning `false`, and name validation rejecting separators
- [ ] T023 **Superseded in intent by D-002** (there is no per-namespace ordering to test); re-scope to "two themes each carry the same address" if a test is wanted. Unit: the per-namespace ordering, including the case where two modules serve the same
      namespace and ship the same template name
- [ ] T024 **Partly met:** `ThemeResolutionTest` (3 tests) uses real files under `test/integration/TestAssets/themes/` for hit, fallback and the theme beating Mezzio's map; the "does not scan per lookup" claim is not asserted. Integration: real template files across two roots, proving fallback and that resolution
      does not scan per lookup
- [X] T025 **Built** (`ConfigProviderWiringTest`, green). Keep `test/integration/ConfigProviderWiringTest.php` green: it asserts
      `extra.laminas.config-provider` agrees with the namespace

## Phase 9 — Spec-kit tracking

- [X] T026 **Done** (specs and `.specify` are tracked on this branch). Keep `.specify/` and `/specs/` tracked in this repository (deliberate divergence from the
      preset's alignment tasks, which add both to `.gitignore`): this component's development is
      spec-driven, so the scaffolding and the specs live and evolve with it
- [X] T027 **Done** (`.specify/.gitignore` ignores `presets/`). Keep the vendored preset copy (`.specify/presets/`) local via `.specify/.gitignore`; it is
      released content shipped in `webware/webware-tools/presets/`

## Phase 10 — Theme-aware asset helper (FR-009, FR-009a; D-007, D-008, D-009)

Why: laminas-view's `Asset` helper is `final readonly` and is built once from
`view_helper_config.asset.resource_map`, with no idea of a theme. The fix is a factory, not a subclass.

- [ ] T042 Define the asset configuration shape as an `@type ThemeAssetsConfig` in `ConfigProvider`:
      `theme.assets.<theme>.<name> => value`, where `value` is a path relative to that theme's directory
      (`css/theme.css`) or an absolute URL (`https://`, `//`). Names are a fixed vocabulary defined by the
      components; a theme re-values them
- [ ] T043 Implement `Webware\Theme\View\Helper\Container\AssetFactory` (`final`, returns a stock
      `Laminas\View\Helper\Asset`). Merge `default` first, then the active theme over it. A relative value
      becomes `/theme/<theme>/<value>` using the theme that **defined** the name, so fallback is per name;
      absolute URLs pass through unchanged. Validate every theme name as a single path segment (no `/`,
      `\`, `..`, empty) and throw a package exception otherwise. Never throw for an unknown name here: the
      stock `Asset` does that at call time, which is wanted
- [ ] T044 Register it for `Laminas\View\Helper\Asset::class` in the **view helper** factories, not the
      container's `dependencies`. Verify the exact config key in `laminas-view/src/ConfigProvider.php`
      before writing it (`view_helper_config` holds helper options; the plugin-manager key is a different
      one). Merge order matters: this provider must come after `Mezzio\LaminasView\ConfigProvider`
      (D-004, `project-tracking#6`)
- [ ] T045 Unit tests for the factory: active theme wins; per-name fallback to `default`; absolute URL
      unchanged; unknown name throws from the helper; a traversal theme name is rejected; an empty config
      yields a helper whose every call throws
- [ ] T046 Integration test through a real `HelperPluginManager` (or the `ServiceManager` the other
      integration tests use) that `asset('theme.css')` returns `/theme/default/css/theme.css`, then the
      active theme's value after switching `theme.active`
- [ ] T047 Document in `quickstart.md` how a theme author adds an asset and where the file goes
      (`public/theme/<theme>/…`, published by the installer, never hand-written)
- [ ] T048 Record the final key paths in `data-model.md` and `research.md` (they currently describe
      `view_manager.asset.resource_map`, which is wrong, D-009)

## Phase 11 — The default theme's assets (repository `webinertia/default-theme`; D-015, D-016)

- [ ] T049 Land `webinertia/default-theme#1` (the Bootstrap 5.3.8 token overrides in `css/theme.css` and
      `mockup/index.html`). Owner review; do not merge without them
- [ ] T050 Decide how the default theme is packaged so the installer can publish it to
      `public/theme/default/{css,js,img,fonts}/` (composer package name and layout). Record it in
      `decisions.md`
- [ ] T051 Extend the mockup with the shell: navbar with the three navigation areas (`main`, `admin`,
      `user`), footer, toast container for the messenger, and the Light/Dark control. Check each page in
      both modes
- [ ] T052 Port the CSS the three components need (the `ims-acl-*`, `ims-widget-*`, `ims-badge-*` and
      `ims-col-*` rules, 84 in IMS `public/assets/css/custom.css`) into `default-theme/css/` with the
      neutral class names from D-013 and the palette tokens. Keep HTTP-method and privilege colours on
      Bootstrap's semantic colours (D-016); replace hard-coded Bootstrap RGB values with tokens
- [ ] T053 CMS-style landing page layout mockup for `app::home-page` (hero plus regions). Blocked on the
      contract location, D-018

## Phase 12 — Port the base templates to a neutral `default` theme (FR-018 to FR-020; D-010, D-013, D-014)

Scope: webware-acl, webware-admin, webware-usermanager. Everything IMS-specific is copied **verbatim**
into the `ims` theme first; the `default` copy then loses the `ims-` coupling. No IMS code is lost and
none lives in a component (standing rule 6).

- [ ] T054 webware-usermanager: land PR #69 (auth pages already neutral and under
      `templates/default/user/`), then replace the remaining `ims-widget-*` and `ims-col-*` hooks in
      `admin-widget.phtml` and `list-users.phtml` (five each). Use `list-col-*`, never `col-*` (D-013)
- [ ] T055 webware-acl: move `templates/acl` to `templates/default/acl`, update
      `ConfigProvider::getTemplates()` and its test. **Started on branch `feat/default-theme-templates`
      in the local clone, uncommitted as of 2026-10-01: check `git status` before redoing it.** Then
      rename the hooks per D-013 in `admin-acl.phtml` (about 37 occurrences), `admin-widget.phtml` and
      `partials/protect-route-wizard.phtml` (about 30). Change ids, classes, `data-*` references and
      the markup strings built in JavaScript together
- [ ] T056 webware-acl: extract the ACL page controller (IMS `public/assets/js/app.js` lines 301 to 704,
      "ACL Wizard controller") into webware-acl as plain JavaScript with neutral selectors and no build
      step, shipped as a **component** asset (D-010), and register its asset name. Needs Phase 10 and a
      served location (D-011, D-012)
- [ ] T057 webware-admin: move `templates/admin` to `templates/default/admin` and update the template
      path and its test (the dashboard is 14 lines and already free of `ims` markup)
- [ ] T058 `webinertia/webware`: copy the verbatim originals (from each component's git history or the IMS
      repository) into `src/App/templates/ims/<namespace>/` and map them in the `ims` block of
      `config/autoload/theme.global.php`. Include the IMS CSS and JS with that theme
- [ ] T059 Verify in a browser with the `default` theme active, dark and light: the ACL overview, roles,
      resources, the user list and the dashboard render with the palette, and the ACL search, filters,
      rules offcanvas and protect-route wizard work. Needs an admin user in the app database

## Phase 13 — Cross-repository dependencies

- [ ] T060 `project-tracking#6` (the installer RFC) carries the asset-publishing requirements (added
      2026-10-01). Until an installer exists, publish assets with a documented one-off command and remove
      it when #6 lands; nobody hand-creates files under `public/theme/`
- [ ] T061 Decide where the home-page block contract lives (D-018), then write its spec and plan in that
      repository. This feature only needs the `default` theme templates that render it (D-019)

## Phase 14 — Owner decisions of 2026-10-01 (D-005, D-011, D-012)

- [ ] T062 Add constants for the known config keys in `ConfigProvider` (`THEME = 'theme'`, `ACTIVE`,
      `THEMES`, `ASSETS`) and use them in `ThemeResolverFactory` and the tests instead of the string
      literals (D-005). No new classes
- [ ] T063 Validate a theme name as a single path segment (no separators, no `..`) at the one place it
      becomes a path, the asset factory (T043); test it. Do not add a value object (D-005)
- [ ] T064 Component assets are per theme (D-011): in T042–T043 the asset values for a component live
      under `theme.assets.<theme>` and resolve to `/theme/<theme>/component/<component>/…`; document the
      layout in `data-model.md` (it currently shows the superseded `public/component/` path) and
      `quickstart.md`
- [ ] T065 Specify (in `spec.md`) the theme-management command (D-012, D-020): given a name, copy the
      `default` theme's layout, body and home-page templates and main CSS file into a new theme of that
      name; register a `theme.themes.<name>` map covering only the copied templates; set `theme.active`
      to it, written to the autoloaded config file `theme.{theme-name}.global.php` (owner, 2026-10-01).
      Also switch the active theme to an existing one. Settle where `theme.active` lives when several
      theme files exist (a shared key in two autoload files is last-wins)
- [ ] T066 Implement the command(s) from T065, following the `Console\` guard rule, once T065 is
      agreed and webware-console placement is recorded (T034). The copy must not overwrite an existing
      theme, and the name is validated as a single path segment (T063)
- [ ] T067 Tests for T066 to the 100% line and MSI floors
- [ ] T068 Admin widget to choose the active theme from the installed themes (D-012), using
      webware-admin's `WidgetInterface`. It **only switches** between already installed themes and never
      creates one (owner, 2026-10-01); the switch must change `theme.active` where T065 puts it. Depends
      on T065 (installed-theme list and persistence) and
      belongs in the repository the owner chooses. Not started
- [X] T069 Done 2026-10-01 for the layout line; the Theme/roots wording is already marked superseded. Update `data-model.md`: replace the `public/component/` layout with the per-theme one (see
      T064) and drop the superseded Theme/roots wording now that D-005 is settled

## Verification

- [ ] T028 `mago format --check && mago lint && mago analyze && mago guard` — all four clean
- [ ] T029 `composer test`, `composer test-integration`, `composer test-coverage`,
      `composer mutation-test` — 100% line coverage, MSI at or above 95
- [ ] T030 Render a page in an application with the packages installed and no theme configured (SC-003,
      SC-006), then activate a theme that overrides one layout and confirm everything else falls back
- [ ] T031 Push the branch, open the PR, and confirm the required workflow's checks are green —
      which requires `ci-target` to be set on the repository first
