# Research: Theme Resolution

**Feature**: `specs/001-theme-resolution/` · **Date**: 2026-09-29

This is the record of what was measured and what was decided, so the decisions in `plan.md` can be
re-derived rather than taken on faith. Measurements were taken against the installed
`laminas/laminas-view` 3.x and `mezzio/mezzio-laminasviewrenderer` in `webinertia/webware` on
2026-09-29.

## Namespaced resolution is the base

mezzio addresses templates as `<namespace>::<name>`, and `NamespacedPathStackResolver` already owns one
`TemplatePathStack` per namespace. This feature builds on that rather than replacing it: the theme becomes
the **first directory segment** under a module's `templates/`, and the work is assembling, per namespace,
the paths that namespace is served from — the active theme's directory before `default` — so that "the
theme does not ship this template" resolves from `default` with no special case anywhere.

Earlier iterations instead pushed extra paths onto the resolver stack globally and modified the renderer to
do it. Same idea in the wrong place: it paid the cost for every template in the application whether or not
the theme had anything to say about that template.

## Why not the renderer

The earlier iterations of this component modified the renderer and pushed additional paths onto its
resolver stack. It worked, and it is discarded for cost reasons:

- `TemplatePathStack::resolve()` → `resolveToPath()` loops its paths doing `new SplFileInfo($path . $name)`
  and `isReadable()` — one stat per path, in order, **on a hit and on a miss**.
- `NamespacedPathStackResolver::resolve()` lazily constructs a `TemplatePathStack` **per namespace** and
  delegates. So each theme-as-namespace is its own stat list, and a template the active theme lacks
  walks the whole theme stack before the default stack is even consulted.
- Multiply by every layout and partial a page renders, and layer it by the number of themes installed.

There is no relief from caching: **`laminas/laminas-view` 3.x ships no `ResolveCache`** (`grep -rln
ResolveCache` over `src` returns nothing; the 2.x resolver cache is gone). Nothing memoizes resolution
for you, so whatever a resolver touches at resolve time, it pays per lookup.

## Why a resolver, and why maps

- `AggregateResolver::resolve()` iterates its priority queue, `continue`s when a resolver returns
  `false`, and returns the first hit. Per-template fallback is therefore **free between resolvers** —
  this is the hook the design uses.
- `TemplateMapResolver::resolve()` is `return $this->map[$name] ?? false;` — **no filesystem call at
  all**. Map entries are the cheap layer.
- Therefore the resolver contributes and consults **maps**, and the only path stack in play is the one
  the framework already has.

Consequence recorded as FR-005: resolution must not add a filesystem walk per lookup.

## Configuration precedence — measured, and counter-intuitive

`ConfigAggregator::mergeConfig()` → `mergeArray()` (the same logic as `Laminas\Stdlib\ArrayUtils::merge`):

- `is_int($key)` → `$a[] = $value`: integer keys **append**. A list from a later provider lands *after*
  the earlier one, so for `templates.paths` — walked in list order — the **first-registered path wins a
  same-named file**, i.e. packages would shadow a theme.
- string keys → `$a[$key] = $value`, recursing into string-keyed sub-arrays: **later wins**. This is how
  an application's `templates.map['body::default']` override works today, and why maps are the override
  channel.

Decision: themes and overrides are expressed as **maps**; path lists are the fallback floor, and
provider order is not relied upon as an override mechanism.

## Asset names are a vocabulary, not a fallback chain

`Laminas\View\Helper\Asset` is a `final readonly` lookup over `view_helper_config.asset.resource_map`
(earlier drafts said `view_manager.asset`, which is wrong; D-009) and
**throws** `InvalidArgumentException` on a name that is not present. Templates fall through; assets do
not. So a theme re-values names that components define, and cannot introduce or rename them.

Also measured: there is **no `resource_map` anywhere in the fleet yet**, and `webware/public/` holds only
`index.php` and `.htaccess` while the app layout already calls `$this->asset('messenger.js')` against an
empty map. The vocabulary is being defined from zero, which is why the naming rule can be set now
without a migration.

## Middleware was considered and dropped

Serving theme assets through a PSR-15 middleware is unnecessary: laminas-view already provides the
asset helpers (`asset()`, `headLink()`, `headScript()`, `basePath()`), and the application's
`public/.htaccess` short-circuits on existing files while rewriting everything else to `index.php`, so
assets that live under `public/` are served by the web server in production and by the front controller
in the single-server dev setup (`php -S … -t public/`, no router script, which reaches `public/index.php`
for paths that are not files).

## The webware-htmx body/layout layer

`LaminasRendererFactory` still reads **five** keys for one idea — `templates.layout`, `templates.body`,
`templates.default_layout`, `templates.default_body`, `view_manager.default_layout` — where the first
non-empty becomes the layout and the **last** non-empty becomes the body, i.e. two precedence rules over
one list. FR-012 collapses this to theme-resolved names.

Related measurement: `webware-htmx/templates/body/default.phtml` is the IMS application shell (Farmers
IMS branding, store list, Bootstrap navbar, `$this->navigation('main'|'admin'|'user')`) and calls
`$this->imsMessenger()`. That helper is **defined nowhere** — not in the application repository outside
`vendor`, and not in `webware/vendor` at all; the application's own copy of the file has the call
commented out. A package shipping a default template cannot call a helper nothing provides, so the shell
becomes the `ims` theme and the package ships a helper-clean `default` (US3).

## LCDD — no build step

"Least Common Denominator Development": the default is htmx plus plain CSS plus the latest Bootstrap CSS,
with Tailwind bridges later via asset helpers. Consequences that shaped the design:

- No Sass/PostCSS pipeline, so per-theme Bootstrap theming uses `data-bs-theme` and the `--bs-*` custom
  properties rather than compiled variables.
- A theme is markup plus assets. Nothing is compiled, published or watched.
- Per-package defaults plus per-theme overrides mean an application must be able to have *two* sets of
  markup on disk at once, which is why the default theme is a directory rather than a fallback file.

## Decisions taken after the prototype (2026-10-01)

Measured or settled once the resolver existed; the IDs point into `decisions.md`.

- **Asset layout `/theme/<theme>/{css,js,img,fonts}` (D-007).** `public/.htaccess` serves an existing
  file, link or directory before it rewrites to `index.php`. A theme directory at the web root would
  therefore shadow a route of the same name, and `/admin` or `/user` are routes. One fixed prefix also
  gives the web server a single place for cache headers.
- **The helper is a factory override (D-008).** `AssetFactory::__invoke()` reads
  `view_helper_config.asset.resource_map` and returns `new Asset($map)`; the class has no setter and no
  theme concept. Replacing the factory and handing it a merged map keeps the stock helper, its
  `InvalidArgumentException` on unknown names, and its caller-facing API.
- **Component JavaScript cannot live in a theme (D-010).** The ACL page's behaviour is the "ACL Wizard
  controller" in the IMS app's `public/assets/js/app.js`: lines 301 to 704 of a 704-line file. It selects
  by `ims-acl-*` ids and classes (`#ims-acl-route-search`, `.ims-acl-route-entry`,
  `.ims-acl-grant-card`, `#ims-acl-wiz-next`, `#ims-acl-rule-offcanvas`), reads `data-acl-filter`,
  `data-wizard-action`, `data-rules-panel` and `data-acl-step-grant`, and builds markup strings that
  contain `ims-acl-*` classes. The component templates have no inline script that selects by those
  names (checked with `querySelector`, `classList`, `closest` and `getElementById`).
- **The styling is 84 rules in IMS `public/assets/css/custom.css`** (739 lines) for the `ims-acl-*`,
  `ims-widget-*`, `ims-badge-*`, `ims-col-*` and `ims-rules-*` families. Several hard-code Bootstrap's
  RGB values (the method pills use Bootstrap's success green and blue). They become palette tokens in
  the default theme; the HTTP-method and privilege colours stay semantic (D-016).
- **Name collision (D-013).** Stripping `ims-` from `ims-col-name` and friends in the usermanager list
  would produce `col-name`, which collides with Bootstrap's `.col-*` grid classes. Use `list-col-*`.
- **Counts in the three components' templates.** webware-acl: 8 templates, `ims-` hooks concentrated in
  `admin-acl.phtml` (about 37) and `partials/protect-route-wizard.phtml` (about 30), plus 6 in
  `admin-widget.phtml`. webware-admin: 1 template, none. webware-usermanager: 8 templates, five hooks
  each in `admin-widget.phtml` and `list-users.phtml`; the auth pages are already neutral (PR #69).
- **The IMS home page is not worth porting.** `ims/app/home-page.phtml` is the Mezzio skeleton welcome
  page (Pico-style `article` and `grid` markup, Font Awesome icons). The part of IMS worth porting is the
  shell, `ims/body/default.phtml` (156 lines): navbar, the `main`/`admin`/`user` navigation containers
  and the messenger.
- **The home page's intended model** is webware-admin's: `DashboardMiddleware` dispatches a mutable
  `RegisterWidgetEvent`, listeners add `WidgetInterface` objects (`title`, `resourceId`, `privilege`,
  `template`, `order`), `AclWidgetFilterIterator` filters them by the user's roles, the handler renders
  `admin::dashboard`, which loops `$this->partial($widget->template, $widget)`. A public page needs a
  `region` the admin widget does not have (D-018).
- **The default theme's colours (D-015).** Decoded from the org avatar: purple `#7e5ae0`, green
  `#05a578`. Contrast: white on purple 4.74:1; purple text on Bootstrap's dark body 3.25:1 (so dark-mode
  text uses `#b29cec` at 6.50:1 and dark-mode outline buttons `#987be6` at 4.63:1); white on green
  3.15:1 (so green buttons use black text at 6.66:1 and light-mode green text `#048460` at 4.69:1).
  Bootstrap 5.3.8 compiled components hard-code their blue, so tokens are restated per component in
  `default-theme/css/theme.css`.

## Open items (revised 2026-10-01)

- Cross-module order for a shared namespace is **resolved** (D-002): theme maps are config and the
  config merge decides, later wins.
- Map entries are trusted and not stat-checked (**decided**, D-017; this was T008).
- The `ims` theme extraction is **partly done** in `webinertia/webware` (layout, body, home page, error
  pages and the four user pages are in `src/App/templates/ims/`); the admin pages are not, so SC-005
  (byte-identical markup) is untested for them (T058).
