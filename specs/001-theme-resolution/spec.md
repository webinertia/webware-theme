# Feature Specification: Theme Resolution for Webware Packages

**Feature Branch**: `001-theme-resolution`

**Created**: 2026-09-29

**Status**: In progress. The resolver is built as a prototype (see `decisions.md` section 1); the
theme-aware asset helper, asset publishing, the default theme's assets and the port of the base
templates are open (`tasks.md` Phases 10 to 13). Requirements below carry a note where a decision
changed them; the reasons are in `decisions.md`.

**Input**: User description: "I want to simply build a resolver which will simplify, I hope, also
supporting the custom template layer for htmx. The benefit is that laminas already provides and will
use the AggregateResolver so that we can fallback to the default if a given theme does not ship a
given template. So the approach is we provide a "default" theme in all webware components if they
require layouts, templates etc. Users can then install additional themes or create one based on the
default that they can then modify."

## Purpose

Webware applications are built to be re-skinned. A design or development shop starts from a Webware
application, builds a client's site on top of it, and later refreshes that site — often years later —
without rewriting the application. This spec defines the mechanism that makes that possible: themes as
a directory convention, resolved by name, with per-template fallback to the `default` theme every
package ships.

The mechanism is deliberately small. It replaces earlier iterations that modified the renderer and
pushed additional paths onto a resolver stack, which worked but made every template lookup pay for
every layer.

## Scope

**In scope:** theme resolution for laminas-view; the directory and naming convention; the default
theme shipped by packages; the active-theme selection and its configuration; asset name resolution
through laminas-view's asset helpers, made theme-aware; where theme and component assets are served
from; the visual baseline of the `default` theme; the port of the base templates of webware-acl,
webware-admin and webware-usermanager to a neutral `default` theme; the webware-htmx body/layout layer
expressed as ordinary theme templates; the extraction of the IMS application's current shell and admin
pages, verbatim, into an `ims` theme.

**Out of scope (explicit):**

- An asset-serving middleware. Assets are resolved through laminas-view's helpers
  (`asset()` / `headLink()` / `headScript()` / `basePath()`), which is what the ecosystem already uses.
- Any build step. The default stack is htmx, plain CSS and the latest Bootstrap CSS; a theme is markup
  plus assets, and nothing compiles.
- Database-backed theme storage. Theme configuration is config only.
- The Tailwind (or any alternate CSS framework) bridge. It is a later addition and, per the LCDD
  direction, is expected to be a theme that overrides markup plus an asset-helper entry rather than a
  pipeline.
- Per-request theme switching. The active theme is a configuration value; nothing in this spec
  requires resolving a theme from a session, a store, or a request. (The Light/Dark colour mode is a
  client-side `data-bs-theme` toggle, not a theme switch.)
- The home-page content-block contract (a PSR-14 collect event modelled on webware-admin's dashboard
  widgets). This feature only supplies the `default` theme templates that render it. See `decisions.md`
  D-018 and D-019.
- The installer itself. Its requirements live in `webinertia/project-tracking#6`; this spec states only
  what it must produce (FR-015).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A client redesign ships as a theme, not a fork (Priority: P1)

A shop that built a client's site on Webware is asked to refresh it. They create a theme that
overrides only what the redesign changes — a layout, a few templates, a stylesheet — and every other
template continues to come from the packages, unchanged.

**Why this priority:** this is the entire commercial reason the component exists. Without it, a
redesign means forking and maintaining a copy of every template in every package.

**Independent Test:** create a theme that overrides exactly one layout template, set it as active,
and confirm that (a) the overridden template renders from the theme and (b) every other template still
renders from the package's `default` theme.

**Acceptance Scenarios:**

1. **Given** a package shipping `templates/default/layout/default.phtml` and a theme directory
   `templates/acme/layout/default.phtml`, **When** `acme` is the active theme, **Then** the layout
   renders from `acme` and every template `acme` does not ship renders from `default`.
2. **Given** a theme that ships no `body` template, **When** a page renders, **Then** the body comes
   from `default` and the page is complete — no error, no empty region.
3. **Given** an application with no theme configured, **When** it boots, **Then** the `default` theme
   is used and nothing asks the developer to configure a theme.

---

### User Story 2 - A package author ships a default theme without knowing the mechanism (Priority: P2)

Someone writing a Webware package puts their markup under `templates/default/` and registers it the
same way every other package does. They write no theme code, and a consuming application gets a
working interface with no configuration.

**Why this priority:** adoption depends on this being less work than not using themes. A package author
must not have to learn, or even read about, theme resolution.

**Independent Test:** add a template to a package under `templates/default/`, consume the package from
a second application, and render it with no theme configuration present.

**Acceptance Scenarios:**

1. **Given** a package whose `ConfigProvider` publishes its template root, **When** an application
   registers that provider, **Then** the package's templates resolve with no application-side theme
   configuration.
2. **Given** two packages each shipping a `default` theme, **When** both are installed, **Then** each
   package's templates resolve from its own root and neither shadows the other's.

---

### User Story 3 - The IMS application's shell becomes the `ims` theme (Priority: P3)

The application shell currently inside the webware-htmx package (navbar, sidebar, store selector,
navigation calls) moves into an `ims` theme, and the package keeps a `default` theme whose templates
call only helpers the Webware packages themselves provide.

**Why this priority:** it removes an existing defect (a package template calling an application helper
that does not exist) and proves the convention on a real, non-trivial theme.

**Independent Test:** install the packages without the IMS theme and render a page — no missing-helper
error; then activate `ims` and confirm the IMS shell renders with its own helpers available.

**Acceptance Scenarios:**

1. **Given** the packages installed with no application code, **When** a page renders under `default`,
   **Then** every helper called by the shipped default templates is registered by an installed package.
2. **Given** the `ims` theme active in the IMS application, **When** a page renders, **Then** the shell
   markup is identical to what the application renders today.

---

### User Story 4 - The default theme looks like Webware, not like a framework skeleton (Priority: P2)

A person installs the packages and opens the admin pages. They see stock Bootstrap markup in a dark
theme built from the two logo colours of the webinertia org, with a Light option, and nothing that
mentions IMS or a store.

**Why this priority:** a default theme nobody wants to keep makes every install a redesign.

**Independent Test:** with no theme configured, load the ACL overview, the user list and the dashboard
in a browser, in dark and in light.

**Acceptance Scenarios:**

1. **Given** a first visit, **When** a page renders, **Then** it is dark and the Dark option is the
   pressed one.
2. **Given** the Light option is chosen, **When** the page is reloaded, **Then** it is still light.
3. **Given** any rendered `default` page, **When** its markup is searched, **Then** it contains no
   `ims-` class or id and no IMS branding.

---

### User Story 5 - The IMS application keeps its look, verbatim (Priority: P3)

The IMS application's admin pages move into the `ims` theme exactly as they were, with their own CSS
and JavaScript, while the packages move to the neutral `default` theme.

**Why this priority:** it is what lets the components lose the IMS coupling without losing any IMS code.

**Independent Test:** activate `ims` and compare the rendered ACL, user and dashboard markup with what
the IMS application renders today.

**Acceptance Scenarios:**

1. **Given** `theme.active` is `ims`, **When** the ACL overview renders, **Then** the markup is the
   original markup and the original script works against it.
2. **Given** `theme.active` is `default`, **When** the same page renders, **Then** the neutral hooks
   are present and the component's own script works against them.

---

### Edge Cases

- The active theme directory does not exist at all — the application still renders from `default`
  rather than failing.
- A theme ships a template name that no default provides — the name is simply unknown, and the failure
  is the same failure an unknown template name has always produced.
- A theme's configured asset name is not present in the resource map. The asset helper throws on an
  unknown name, so asset names are a vocabulary: themes re-value names, they do not invent them.
- A theme re-values only some asset names. The rest resolve from `default`, per name, and the URL points
  at the theme that defined the name.
- A theme name that is not a single path segment (`a/b`, `..`, empty) is rejected before it is used to
  build a path or a URL.
- The active theme is changed in configuration but the aggregated config is cached — a cached
  configuration must be cleared for the switch to take effect, and this must be documented.
- Two components ship the same template name and different themes are active — resolution must be
  deterministic, driven by the configured root order, not by directory scan order.

## Requirements *(mandatory)*

**Constitution**: no constitution is ratified in this repository yet
(`.specify/memory/constitution.md` still holds the template's placeholders), so the requirements below
are not gated against project principles. The fleet standards that do apply are the agent working
agreements, the four Mago gates, and the MSI floors in `webware-ci.json`.

### Functional Requirements

- **FR-001**: A theme MUST be a directory named for the theme, directly inside the `templates/` directory
  of a module that ships or overrides templates. A module's theme directory holds one subdirectory per
  template namespace.
- **FR-002**: Templates MUST keep mezzio's namespaced addressing — `<namespace>::<name>`, e.g.
  `app::home-page.phtml`, `layout::default`, `admin::dashboard.phtml`. The theme is never part of the
  address; it selects the first directory segment under `templates/`.
- **FR-003** *(revised 2026-10-01, D-002/D-003)*: Resolution MUST consult the active theme's template
  map before anything else, then the framework's own map and path-stack resolvers. A component's
  `default` theme is served by the component publishing `templates/default/<namespace>/` under its
  namespace; the theme map carries only overrides.
- **FR-004**: Fallback MUST be per template, not per theme: a template the active theme does not ship
  resolves from `default` in the same namespace, and a template no module ships falls through to the
  framework's own resolvers.
- **FR-004a**: Any module MUST be able to override another module's template by placing that namespace
  under its own theme directory — this is how an application overrides a vendor template without
  touching the package that ships it.
- **FR-005**: Resolution MUST NOT add a filesystem walk per lookup. Map lookups are the expected
  mechanism; any scan performed to build a map MUST happen once.
- **FR-006**: Theme configuration MUST come from configuration only. No database access, at boot or at
  render time.
- **FR-007**: Every module that ships layouts or templates MUST ship a `default` theme.
- **FR-008**: The active theme MUST be selectable by a configuration value alone. A module that ships no
  directory for the active theme contributes only its `default`.
- **FR-009** *(revised 2026-10-01, D-007 to D-009)*: Assets MUST be resolved through laminas-view's
  `asset()` helper, made theme-aware by overriding that helper's **factory** (the class is
  `final readonly`). Values live under `theme.assets.<theme>.<name>`; the laminas-view key the stock
  factory reads is `view_helper_config.asset.resource_map`. No request-time asset middleware. Asset
  files MUST live under `public/theme/<theme>/{css,js,img,fonts}/` (URL `/theme/<theme>/…`) and never
  inside a theme's `templates/` root, which is not a served location.
- **FR-009a**: The theme-aware helper MUST resolve a name from the active theme, fall back to `default`
  per name, pass absolute URLs through unchanged, and still throw on a name no theme defines.
- **FR-010**: A theme MUST work without a build step. Markup and assets are the deliverables.
- **FR-011** *(superseded 2026-10-01, D-006)*: Module roots derived from PSR-4 namespaces. Replaced by
  theme maps declared in configuration (`theme.themes`); there is no `roots` list.
- **FR-012**: The body and the layout MUST each be named by **one** configuration value, and that value
  MUST be a theme-resolvable template name (`body`, `layout`) rather than a path the owning package
  publishes as a map entry. This is what lets a theme point at a body template at all, so the collapse of
  webware-htmx's five-key lookup (`templates.layout`, `templates.body`, `templates.default_layout`,
  `templates.default_body`, `view_manager.default_layout`) is a **prerequisite of this feature** — it is
  the one part of webinertia/webware-htmx#21 that is required here. The package reads the name and hands
  it to whatever resolver is registered in the aggregate; it does not depend on this package to do so.
- **FR-013**: The shipped `default` theme MUST call only helpers that the package's own dependencies
  provide.
- **FR-014**: A theme's templates MUST be able to call helpers contributed by any installed module's
  `ConfigProvider`.
- **FR-015**: Creating and managing a theme's asset files MUST be the theme installer's responsibility
  rather than a manual task. Running the installer is what puts a theme's assets under
  `public/theme/<theme>/`; a developer never creates them by hand, and the components' `default` themes
  need no manual asset step. A component's assets (for example the ACL page JavaScript) are stored
  per theme, under `public/theme/<theme>/component/<component>/`, so each theme can publish its own
  (D-011). Publishing symlinks in
  development, copies in production, is idempotent, and removes what a package no longer ships. The
  installer's own requirements are tracked in `webinertia/project-tracking#6` (D-012). Installing is the
  composer plugin installer's job; creating, registering and switching themes is a command, and choosing
  one is an admin widget (D-012, tasks T065–T068).
- **FR-016**: The shipped `default` theme MUST use stock Bootstrap 5.3 markup and spacing, be dark by
  default with a Light/Dark control that persists the choice, and need no build step (D-015).
- **FR-017**: The two colours of the webinertia logo MUST be the theme's `primary` (`#7e5ae0`) and
  `secondary` (`#05a578`) in both colour modes. Text and control colours MUST meet WCAG AA (4.5:1) in
  both modes; where a brand colour does not, a tint or shade is used for that role only (D-015, D-016).
- **FR-018**: A component's templates MUST live under `templates/default/<namespace>/` and publish that
  path under their namespace. In the `default` theme they MUST NOT carry `ims-` classes, ids or
  branding; styling hooks use neutral prefixes (D-013).
- **FR-019**: A component's behaviour JavaScript MUST ship with the component, select only by the
  neutral hooks, and never be part of a theme (D-010). A theme supplies CSS for those hooks.
- **FR-020**: Every template ported to the `default` theme MUST also exist, verbatim, in the `ims` theme
  of `webinertia/webware`, with its CSS and JavaScript, so no IMS code is lost and none lives in a
  component (D-014).

### Key Entities

- **Theme**: a name plus the root it resolves from. Configuration-driven; identity is the directory
  name.
- **Theme root** *(superseded, D-006)*: there is no theme root entity. A theme is a key in
  `theme.themes` (address to path) and, for assets, a key in `theme.assets`.
- **Resolver**: the component that turns a namespaced address (`<namespace>::<name>`) into a path, using
  the paths that namespace is served from, with the active theme's directory before `default`.
- **Asset name**: a fixed vocabulary entry in the resource map that a theme may re-value.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A redesign that changes one layout, one partial and one stylesheet requires zero PHP
  changes and touches no file outside the theme directory.
- **SC-002**: Resolving a template that the active theme does not ship costs no additional directory
  scan relative to resolving one it does ship.
- **SC-003**: A package's templates render in a consuming application with zero theme configuration
  present.
- **SC-004**: Switching the active theme is a configuration change plus a config-cache clear — no code
  change, no file move.
- **SC-005**: Extracting the IMS shell into the `ims` theme leaves the rendered markup identical.
- **SC-006**: Installing the packages without any application code renders a page without a
  missing-helper error.

## Assumptions

- The package identity is settled: `webware/webware-theme`, namespace `Webware\Theme`. The name used in
  the earlier axleus iteration (`ThemeManager`) is not carried over; the package is named for the
  concern rather than for a service inside it.
- The runtime dependency is `laminas/laminas-view` alone. `mezzio/mezzio-laminasviewrenderer` and
  `webware/webware-htmx` stay consumers, not dependencies.
- Which module's path is consulted first is **no longer a question** (D-002): themes are address maps
  merged by the config aggregator, so the later provider wins and `theme.themes` entries override.
- The default theme's directory name is `default`, which cannot collide with mezzio's internal
  unnamespaced bucket (`__DEFAULT__`, a private constant, unreachable as a template namespace).
- Configuration merging decides override precedence: integer-keyed lists append (first-registered
  path wins a same-named file) while string-keyed maps replace (later provider wins). Maps are
  therefore the override channel unless provider order is deliberately controlled.
- `laminas-view` 3.x ships no resolver cache, so anything that touches the filesystem at resolve time
  pays per lookup; this is why FR-005 exists.
- `.specify/` and `/specs/` are **tracked** in this repository on purpose (`tasks.md` T026); only the
  vendored preset copy under `.specify/presets/` is ignored. An earlier version of this note said the
  opposite and was wrong.
