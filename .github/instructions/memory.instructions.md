---
description: Session handoff for webware-theme — theme resolver state, spec-kit location, and where the decision log and standing rules live, as of 2026-10-01.
applyTo: '**/*'
---

# Webware Theme Memory

Tagline: Theme resolution by lookup, not by path stacking. The resolver is built; the asset helper, the
default theme and the template port are open. Everything needed to continue is in `specs/001-theme-resolution/`.

## Start here

1. `specs/001-theme-resolution/decisions.md` — current state, the owner's standing rules, the decision log
   (D-001 onwards) with statuses, next actions, gotchas. **Read it first.**
2. `specs/001-theme-resolution/tasks.md` — what is done and what is left; the next free task ID is stated at
   the top.
3. `spec.md` (requirements), `plan.md` (design, with a built-versus-planned table), `data-model.md`
   (configuration shape), `research.md` (measurements), `quickstart.md`.

Spec-kit, `.specify/` and `specs/` are tracked in this repository on purpose; do not propose removing them.

## What exists

- `Webware\Theme\Resolver\ThemeResolver` (final, `ResolverInterface`): `maps[active][name] ?? maps['default'][name]
  ?? false`, no filesystem call. `ThemeResolverFactory` reads `theme.themes` and `theme.active`.
  `AggregateResolverFactory` attaches theme at 100, `TemplateMapResolver` at 50, the namespaced path stack at 1.
  `ConfigProvider` must be merged after `Mezzio\LaminasView\ConfigProvider`.
- The asset helper key is `view_helper_config.asset.resource_map` (not `view_manager.asset`).
- Assets are served from `/theme/<theme>/{css,js,img,fonts}`, never from a bare `/<theme>/` (`.htaccess` would let a
  directory shadow a route).

## Rules that bite

- Every change goes through a pull request; never push to `1.0.x`. Releases and tags belong to the owner.
- Never delete, rename or move a file without approval. Never edit the IMS repository (read-only).
- Named arguments always; never change form field names.
- Never run application code while a debug session may be attached (`ss -ltnp | grep -E ':(9000|9003)'`).
- Check Mago gates by exit code, not by the last line of output.
- Commits: `git commit -s` as `Joey Smith <jsmith@webinertia.net>`; merge commits by default.

The full list is `decisions.md` section 2 and `vendor/webware/webware-tools/agent-working-agreements.md`.
