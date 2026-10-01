# Decisions and Handoff: Theme Resolution

**Feature**: `specs/001-theme-resolution/` · **Last updated**: 2026-10-01 · **Branch**: `prototype/resolver`

This file is the single place to start. Read it first, then `tasks.md` (what is left), then `spec.md`
(what must be true). Every decision has an ID so a later session can cite it, and a status so it is
clear what is settled and what still needs the owner (Joey Smith). When a decision changes, edit its
row and add a dated line; never delete a row.

## 1. Where things stand

| Area | State |
|---|---|
| `ThemeResolver`, `ThemeResolverFactory`, `AggregateResolverFactory`, `ConfigProvider` | Built on `prototype/resolver` (commit `d1c05dc`). 7 unit tests, 5 integration tests. Mago `format`, `lint`, `analyze`, `guard` clean. Not merged: there is no PR for this branch yet. |
| Theme-aware `asset()` helper | Built on `feat/asset-helper` (stacked on `prototype/resolver`, Phase 10 T042–T046, T048; T047 docs open). `ConfigProvider` now has the key constants and registers `AssetFactory` under `view_helpers.factories`. |
| Asset publishing (installer) | Not built. Requirements live in `webinertia/project-tracking#6` (the Webware component installer RFC). |
| Default theme visuals | Worked out in `webinertia/default-theme#1`, a scratch space (D-021): the shipped stylesheet lives in `webinertia/webware` at `public/theme/default/css/theme.css`. |
| Port of webware-acl, webware-admin, webware-usermanager templates to a neutral `default` theme | Started: usermanager PR #69 open, acl moved on an uncommitted branch. See `tasks.md` Phase 12. |
| `ims` theme (the IMS originals, verbatim) | Partly present in `webinertia/webware` (`src/App/templates/ims/`, mapped in `config/autoload/theme.global.php`). Admin pages not yet copied. |

Open pull requests that this work depends on (checked 2026-10-01): `webware-htmx#22` (merge state was
`UNKNOWN`, re-check; it edits the same `ConfigProvider` that `webware-htmx#23` changed),
`webware-usermanager#69`, `webware#7`, `default-theme#1`.

## 2. Standing rules (the owner's, apply to every task)

1. **Every change lands through a pull request.** Never push to `1.0.x` or any default branch. This
   branch (`prototype/resolver`) is a work branch, not a release line.
2. **Never delete, rename or move a file without the owner's explicit approval.** `git mv` of templates
   into `templates/default/<namespace>/` has been approved for the theme port; nothing else has.
3. **Releases and tags belong to the owner.** Never create, move or delete a tag; never run
   `gh release`. If a change needs a release, say so loudly and stop.
4. **Commits are signed off** (`git commit -s`) as `Joey Smith <jsmith@webinertia.net>`. Never override
   the author or committer identity, never derive one from the OS username.
5. **Merge commits are the default.** Squash only for a stated, compelling reason, said before merging.
6. **The IMS repository (`~/github.com/tyrsson/inventory-management-system`) is read-only.** No edits,
   no branch switches. IMS code is never lost, and never lives in a webware component: it belongs in the
   `ims` theme or in IMS components.
7. **Never change form field names** in templates.
8. **Named arguments everywhere** in PHP (the `literal-named-argument` lint fails the gate).
9. **Do not run application code while a debug session may be attached.** Check
   `ss -ltnp | grep -E ':(9000|9003)'` first. Static tools (`mago`) and reading files are always safe.
10. **Do not probe Packagist.** Use `git ls-remote --tags` and `gh release list`.
11. **Do exactly the scope asked.** Anything else you notice gets one line at most. See
    `vendor/webware/webware-tools/agent-working-agreements.md`.
12. Gates before every commit: `mago fmt`, then `mago lint`, `mago analyze`, `mago guard` by **exit
    code** (never read only the last line of output: an `INFO` line can hide a warning, and a warning
    fails CI), then `composer test`, `composer test-integration`. MSI floors are 95/95 in
    `webware-ci.json`. CI for `webinertia/webware` is switched off, so run everything locally there.

## 3. Decision log

Status values: **Settled** (built or confirmed by the owner), **Accepted** (proposed and not
contradicted; confirm if you disagree), **Proposed** (needs the owner), **Open** (undecided),
**Superseded**.

| ID | Date | Decision | Status | Evidence / why |
|---|---|---|---|---|
| D-001 | 2026-09-29 | Theme resolution is a `Laminas\View\Resolver\ResolverInterface` registered in the aggregate. The renderer is not modified. | Settled | `research.md`, "Why not the renderer". The renderer approach paid a stat per path on every lookup. |
| D-002 | 2026-09-29 | The construction is **theme-keyed maps**: `theme.themes[<theme>][<address>] => absolute path`, with the active theme injected and `default` as the per-address fallback. `namespace-keyed paths` and `merge-last theme map` were **not** prototyped. | Settled | Built in `ThemeResolver`; the active theme is a lookup-time fact, no filesystem call. Revisit the other two only if a requirement the map cannot meet appears. |
| D-003 | 2026-09-29 | `ThemeResolver` only answers addresses a theme map lists. Everything else falls through the aggregate to `TemplateMapResolver` then `NamespacedPathStackResolver`. A component's own **default** theme is therefore served by the component publishing `templates/default/<namespace>/` under that namespace in `templates.paths`, not by a `theme.themes.default` entry. Priorities: theme 100, map 50, path stack 1. | Settled | `AggregateResolverFactory`; the app's `theme.global.php` lists only the `ims` overrides. |
| D-004 | 2026-09-29 | `ConfigProvider` for this package must be merged **after** `Mezzio\LaminasView\ConfigProvider`: both own `AggregateResolver::class` and the later provider wins. | Settled | Docblock in `src/ConfigProvider.php`. The guarantee is a requirement on the installer (`project-tracking#6`). |
| D-005 | 2026-10-01 | **Dropped.** No `Theme` / `ThemeInterface` objects (T001, T002). Keep it minimal: add **constants for the known config keys** (`theme`, `active`, `themes`, `assets`) and validate a theme name as a single path segment where it is used (the asset factory, T043). | Settled | Owner, 2026-10-01: "If we do not need them lets keep it minimal and just add const for the known config keys." Prototype works without them. Tasks T062, T063. |
| D-006 | 2026-10-01 | `theme.roots` and PSR-4 root derivation (FR-011, T014) are **superseded** by `theme.themes` maps declared in configuration. | Superseded | The earlier docs described a `roots` list; the code never had one. |
| D-007 | 2026-10-01 | Theme assets are served from `public/theme/<theme>/{css,js,img,fonts}/`, URL `/theme/<theme>/…`. One name per kind: `img` (not `images`). | Accepted | Proposed 2026-10-01 and the owner proceeded on it. Reasons: the `.htaccess` serves existing files and directories before routing, so a bare `/<theme>/` directory at the web root would shadow a route of the same name (`/admin`, `/user`); one prefix for cache headers; keeps `public/` tidy. |
| D-008 | 2026-10-01 | The theme-aware asset helper **overrides the factory** for `Laminas\View\Helper\Asset` and returns a stock `Asset` built from a merged map. No subclass (the class is `final readonly`). Config: `theme.assets.<theme>.<name> => 'css/theme.css'` (relative to the theme's directory) or an absolute URL. The factory prefixes `/theme/<theme>/` using the theme that **defined** the name, so fallback active → `default` is per name. Absolute URLs (`https://`, `//`) pass through unchanged. Unknown names still throw (stock behaviour; fail loudly). The helper is shared; the active theme is configuration, not per request. | Accepted | `laminas-view/src/Helper/Service/AssetFactory.php` builds `new Asset($resourceMap)` once from `view_helper_config.asset.resource_map`. |
| D-009 | 2026-10-01 | The laminas-view config key is **`view_helper_config.asset.resource_map`**, not `view_manager.asset.resource_map` as earlier docs said. | Settled | Measured in `AssetFactory` (`assertArray('view_helper_config', …)`, `assertArray('asset', …)`, `assertArray('resource_map', …)`). |
| D-010 | 2026-10-01 | Component behaviour JavaScript ships with the **component** (webware-acl for the ACL page), never with a theme. A theme ships CSS that styles the component's neutral hooks. | Accepted | The ACL page's behaviour is about 400 lines of the IMS app's `public/assets/js/app.js` (from line 301, "ACL Wizard controller"), selecting by `ims-acl-*` ids and classes and building markup strings that contain them. It cannot live in a theme. |
| D-011 | 2026-10-01 | Component assets are stored **per theme**, so each theme can publish its own version of a component's assets: `public/theme/<theme>/component/<component>/…`, URL `/theme/<theme>/component/<component>/…`. There is no theme-neutral `public/component/` directory. The component ships a default set (its `default` theme); another theme may supply its own, and the per-name fallback to `default` (D-008) applies. | Settled (path shape is my reading of the owner's answer; correct it if you meant otherwise) | Owner, 2026-10-01: "it would be better if those were stored per theme so each theme could publish their own." Supersedes the earlier proposal of `public/component/<component>/`. Task T064. |
| D-012 | 2026-10-01 | **Installing is the composer plugin installer's job** (publishes theme and component assets from `vendor/` into `public/theme/<theme>/`; requirements in `project-tracking#6`). **A command** in the Webware CLI provides: creating a new theme based on `default`, registering it, and switching the active theme. **An admin widget** lets an administrator pick the active theme from the installed themes. So `webware-theme` is not the installer; whether the command lives in this package (needs `webware/webware-console`, `Console\` guard rule: a `final` `*Command` with `#[AsCommand]`, factory in `Console\Container\`) or elsewhere, and where the widget lives (webware-admin `WidgetInterface`), is for the plan. | Settled (split of responsibilities); placement of the command and widget open | Owner, 2026-10-01. Tasks T032–T034 are re-scoped; T065–T068 added. The widget needs a list of installed themes and a persisted active theme, neither of which exists (see Next actions). |
| D-013 | 2026-10-01 | `ims-*` classes in the three components' templates are **styling and behaviour hooks whose CSS and JS live in the IMS app**, not IMS content. In the `default` theme they become neutral hooks: `ims-acl-X` → `acl-X`, `ims-widget-X` → `widget-X`, `ims-badge-xs` → `badge-xs`, `ims-rules-panel-` → `acl-rules-panel-`. `ims-col-*` (usermanager list) must **not** become `col-*`, which collides with Bootstrap's grid; use `list-col-*`. The rename changes markup and JS together or the page breaks. | Accepted | 84 rules in IMS `public/assets/css/custom.css`; the ACL templates have no inline script that selects by these names. |
| D-014 | 2026-10-01 | The verbatim IMS originals of every ported template go into the `ims` theme in `webinertia/webware` (`src/App/templates/ims/<namespace>/`, mapped in `theme.global.php`), and the IMS CSS/JS go with that theme. | Accepted | The owner: "all of the ims specific stuff will get ported verbatim into the ims-theme". |
| D-015 | 2026-10-01 | The shipped `default` theme is **stock Bootstrap 5.3.8 markup**, dark by default, with a Light/Dark control that persists in `localStorage` under `webware-theme`. No build step. Two logo colours: `#7e5ae0` is `primary`, `#05a578` is `secondary`. Component tokens are restated in `default-theme/css/theme.css` because Bootstrap's compiled components hard-code their blue. | Settled | `default-theme#1`. WCAG AA checked: white on purple 4.74:1, black on green 6.66:1, dark-mode purple text tint 6.50:1. |
| D-016 | 2026-10-01 | Mapping green to `secondary` makes `.btn-secondary`, `.text-bg-secondary` and `.alert-secondary` green rather than neutral grey. Bootstrap's own `success` (`#198754`) sits close to the brand green. HTTP-method and privilege colours in the ACL page stay on Bootstrap's semantic success/warning/danger/info, not the brand colours. | Accepted | Called out in `default-theme#1`; not contradicted. |
| D-017 | 2026-10-01 | `ThemeResolver` trusts a map entry and does not stat the file (T008). A missing file surfaces as the renderer's own error. | Settled | The resolver does no filesystem call by design (FR-005). |
| D-018 | 2026-10-01 | The home page (`app::home-page`) is to be a CMS-style landing page loading content blocks from installed components through a PSR-14 collect event, mirroring `webware-admin` (`DashboardMiddleware` dispatches `RegisterWidgetEvent`, listeners add `WidgetInterface`, `AclWidgetFilterIterator` filters by role). The contract is **not** admin's `WidgetInterface`: it needs a `region`. Where the contract lives is **a new component** (owner, 2026-10-01: "Will be provided by a new component. Im still deciding on a few things there before that work will start."). | Settled (location); details open, work not started | Do not start it until the owner says so. Also unchecked: how `AclWidgetFilterIterator` resolves an anonymous user (`DashboardMiddleware` passes it `null`). |
| D-019 | 2026-10-01 | Home-page work is **out of this feature's scope** except for the `default` theme templates that render it. | Accepted | Keeps `webware-theme` about resolution and assets. |
| D-020 | 2026-10-01 | **Creating a theme (the command, D-012) copies the `default` theme's primary files under the given name**: in 1.0 only the layout, body and home-page templates and the main CSS file. It then **registers configuration**: a template map (`theme.themes.<name>`) covering **only the copied templates**, and **sets the new theme as active** (`theme.active`). | Settled (behaviour and storage: the command writes an autoloaded config file named `theme.{theme-name}.global.php`, owner 2026-10-01) | Owner, 2026-10-01: "A copy of the default files will be created and named as the provided name. I think in 1.0 we will just copy the primary templates. Layout, body, home-page and main css file. Configuration will also need to be registered and a template map covering just the copied templates etc and it will set the newly created theme as active." Everything not copied falls back to `default` per address (D-002), so the copy stays small. Tasks T065, T066. |
| D-021 | 2026-10-01 | **There is no `default-theme` package.** `webinertia/default-theme` was only a place to work out colours and the mockup. The `default` theme ships with its owners: each **component** ships its own default templates (`templates/default/<namespace>/`) and its own component assets; **`webware/webware`** ships the application's default templates (`src/App/templates/default/`) and the default theme's stylesheet (`public/theme/default/css/theme.css`), registered as `theme.assets.default.theme.css`. Nothing needs to be packaged or published for the default theme's own CSS. | Settled | Owner, 2026-10-01: "The repo I created was just to have a place to workout the colors etc. The default templates will ship with their components and with webware/webware." Supersedes the T050 question (how to package it) and the assumption in D-014 and T049 that the repo is a deliverable. |

## 4. Next actions, in order

1. Open a PR for `prototype/resolver` (docs and prototype) so the work is reviewable. Do not merge
   without the owner.
2. Build the asset helper (Phase 10, T042–T048). It unblocks everything that serves CSS or JS.
3. Land `default-theme#1` and `webware-usermanager#69` (owner review), then continue Phase 12 with the
   ACL templates: finish the rename (D-013) and extract the ACL JavaScript into webware-acl (D-010).
4. The owner's four answers are recorded (D-005, D-011, D-012, D-018). D-018 is **a new component, not yet
   named or specified; the owner is still deciding details, so do not start it**. The theme-selection
   command and widget (D-012) need a persisted active theme; settled 2026-10-01: the command writes an
   **autoloaded config file** named `theme.{theme-name}.global.php` (owner: "The filename will be
   theme.{theme-name}.global.php"). The
   **admin widget never creates a theme**: it only switches between already installed themes.
   **`theme.active` lives in `theme.settings.global.php`**, a settings file for themes that can hold
   other theme settings later (owner, 2026-10-01). Autoload files merge in glob order and the later
   file wins a shared key, so a per-theme file must not set `active`: otherwise every non-active
   theme's file would need editing on a switch. The command (creating) and the widget (switching)
   both write `theme.active` in `theme.settings.global.php`; a per-theme file holds only that theme's
   `theme.themes.<name>` map (and its assets).

## 5. Gotchas that cost time already

- `mago lint` and `mago analyze` output ends with an `INFO` line; checking only the last line hid a
  failing `literal-named-argument` warning once. Check exit codes.
- `#[CoversClass]` cannot target a trait or a class outside `src` (PHPUnit warns, and Infection then
  refuses to start). Use `#[CoversTrait]` for traits and omit `#[UsesClass]` for vendor classes.
- A public method gains a `PublicVisibility` mutant in Infection. Only a test that calls it directly
  kills it.
- File-creation tools can write asynchronously. Wait and check the file size before running `mago fmt`.
- Bash history expansion breaks `feat!:` in double quotes. Use `set +H` and single quotes.
- The browser tool cannot open `file://` paths outside a trusted folder. Serve a directory with
  `python3 -m http.server <port> --bind 127.0.0.1` on a port that is not 8080 (the owner's) and stop it
  afterwards.
- The webinertia clones are not kept in sync. `git fetch` and compare before trusting a clone.
