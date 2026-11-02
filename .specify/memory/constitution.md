# Webware Theme Constitution

## Core Principles

### I. Library-First

Every capability ships as a self-contained, independently testable library component. Components
MUST have a clear, singular purpose; organizational-only or kitchen-sink packages are prohibited.

### II. Resolution by Lookup

Theme resolution is a lookup, never a path stack. The resolver reads the theme maps and returns a
template name or `false`; it performs no filesystem call:

```text
maps[active][name] ?? maps['default'][name] ?? false
```

- `ThemeResolver` is `final` and implements `ResolverInterface`.
- `ThemeResolverFactory` reads `theme.themes` and `theme.active`. The resolver never reads
  configuration directly.
- `AggregateResolverFactory` attaches the theme resolver at priority 100,
  `TemplateMapResolver` at 50, and the namespaced path stack at 1. The order is the contract: the
  theme wins over the module map, and both win over the path stack.
- `ConfigProvider` MUST be merged after `Mezzio\LaminasView\ConfigProvider`. Merging before it
  reorders the aggregate resolver chain and silently defeats it.

### III. Consumer-Agnostic

The package provides theming; it does not know the application. It MUST NOT hard-couple to a
specific application or component. Configuration is contributed through `ConfigProvider` and
merged by the host, which is how the skeleton binds components (see the ecosystem composition
doctrine).

### IV. Webware Quality Gates (NON-NEGOTIABLE)

Every change MUST pass the shared webware gates: Mago format, lint, analyze, and guard with no
silent suppression; PHPUnit 13 strict mode (coverage metadata, mock/stub split, `failOnNotice` /
`failOnDeprecation` / `failOnWarning`); and Infection mutation coverage at or above the configured
thresholds (MSI 95/95 from `webware-ci.json`). Test doubles follow PHPUnit 13 rules -
`createStub()` for value doubles, `createMock()` only with `expects()`.

Gates are checked by exit code, not by the last line of output.

### V. Naming & Compatibility

Namespace root is `Webware\Theme\`. Interface names end in `Interface`; trait names end in
`Trait`. A namespace segment never repeats its parent segment. Support only current supported PHP
versions (`~8.4.1 || ~8.5.0`). Named arguments are required in all code the package owns.

### VI. Asset Serving Contract

Assets are served from `/theme/<theme>/{css,js,img,fonts}`. They MUST NOT be served from a bare
`/<theme>/` path: `.htaccess` would let a directory shadow a route and the asset would become
unreachable.

The asset helper reads `view_helper_config.asset.resource_map` - not `view_manager.asset`. The
config key is part of the contract; consumers override it by key.

### VII. Cross-Platform Development Environment

Local tooling runs inside the persistent container (`docker compose exec tooling ...`) or the
VS Code Dev Container; the host is not required to carry a native PHP toolchain. `compose.yml` is
the single source of truth and the Dev Container is a thin wrapper over it. These files are kept in
sync with the central `webware/webware-tools` preset.

Agents working this repository run on the Windows host and drive the repository toolchain from
there; scripts invoked by tooling MUST be host-native.

### VIII. Reference Repositories Are Read-Only

`ims` (and any other repository named as reference-only) is READ-ONLY. It is a source of evidence
and original templates, never a place to write. Nothing in it is edited, moved, renamed, or
committed to, for any reason.

## Quality Gates

Every pull request passes, on all CI matrix legs:

- Mago format check, lint, analyze, guard
- Unit tests under lowest/locked/latest dependency strategies
- Integration tests where `run_integration` is set
- Codecov upload from the canonical coverage leg (report-only)
- Infection mutation score at or above the configured MSI thresholds

## Development Workflow

- Every change goes through a pull request. `1.0.x` is never pushed to directly. Releases and tags
  belong to the owner.
- Each spec-driven step (constitution, specify, plan, tasks, implement, converge) lands as its own
  branch and pull request.
- Commits use Conventional Commits and are signed off by the maintainer's real identity
  (`git commit -s`, `Joey Smith <jsmith@webinertia.net>`). Merge commits by default.
- No file is deleted, renamed, or moved without owner approval.
- Form field names are never changed.
- Application code is never run while a debug session may be attached
  (`ss -ltnp | grep -E ':(9000|9003)'`).

## Governance

- This constitution supersedes other practices; conflicts are resolved in its favor or the
  constitution is amended via pull request.
- Amendments require a pull request that updates the version and Last Amended date.
- Every pull request is reviewed for compliance with the Core Principles.
- `.github/instructions/memory.instructions.md` carries the session handoff and operational rules
  derived from this constitution. The full standing rules live in
  `specs/001-theme-resolution/decisions.md` section 2 and
  `vendor/webware/webware-tools/agent-working-agreements.md`.

**Version**: 1.0.0 | **Ratified**: 2026-10-10 | **Last Amended**: 2026-10-10
