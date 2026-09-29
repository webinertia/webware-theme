# webware/webware-theme

Theme support for Webware applications: a theme is a directory under a component's
`templates/`, resolved by name through laminas-view, with per-template fallback to the
`default` theme that every component ships.

[![PHP Version](https://img.shields.io/packagist/php-v/webware/webware-theme)](https://packagist.org/packages/webware/webware-theme)
[![Latest Version](https://img.shields.io/packagist/v/webware/webware-theme)](https://packagist.org/packages/webware/webware-theme)
[![License](https://img.shields.io/github/license/webinertia/webware-theme)](LICENSE)
[![Required CI](https://github.com/webinertia/webware-theme/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml/badge.svg)](https://github.com/webinertia/webware-theme/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml)
[![codecov](https://codecov.io/gh/webinertia/webware-theme/graph/badge.svg)](https://codecov.io/gh/webinertia/webware-theme)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fwebinertia%2Fwebware-theme%2F1.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/webinertia/webware-theme/1.0.x)

## What ships here

Everything in this repository is either a **package of record** consumed from
`webware/webware-tools`, or the **thin per-repo wiring** that cannot live in a shared
config:

| Path | Role |
|---|---|
| `mago.toml` | Extends the centre (`vendor/webware/webware-tools/mago.toml`) and overrides `php-version` only. Never re-add general rules locally. |
| `webware-ci.json` | The required CI workflow's parameter contract — read from the repository root by `webinertia/.github`. |
| `phpunit.xml.dist` | PHPUnit 13 strict mode: `requireCoverageMetadata`, `failOnNotice`, `failOnWarning`, `failOnDeprecation`. |
| `compose.yml` / `Dockerfile` / `.devcontainer/` | The containerized toolchain (Composer, PHPUnit, Mago, Infection, PHPBench, roave BC-check). |
| `src/ConfigProvider.php` | The package wiring entry point, declared under `extra.laminas.config-provider`. |

`mago.toml`, `phpunit.xml.dist`, `.gitattributes`, `codecov.yml`, `Dockerfile`,
`.dockerignore`, `infection.json5.dist`, `phpbench.json.dist` and devcontainer config are
byte-identical to the canonical artifacts in
`webware-tools/presets/webware-alignment/artifacts/` — copy updates from there rather than
editing them here.

## Quality gates

Both MSI gates are set to **95** — the ecosystem standard, not a starting point. Lower them
only with a deliberate decision, and never silently:

```json
"min_msi": "95",
"min_covered_msi": "95"
```

Four Mago gates run in CI and must be clean: `format --check`, `lint`, `analyze`, `guard`.
Run `mago fmt` first when making changes, and fix findings at source rather than adding
`@mago-expect` — a suppression needs to be a decision, not a reflex.

## Development

The toolchain runs in a container, so the host needs no PHP install. With VS Code, reopen in
the container; without it:

```shell
docker compose up -d
docker compose exec tooling composer install
docker compose exec tooling composer test
docker compose exec tooling composer test-integration
docker compose exec tooling mago lint
docker compose down
```

Packages whose tests need MySQL uncomment the `mysql` service in `compose.yml`, mirroring the
`db_image` / `db_env_json` / `db_port` values they declare in `webware-ci.json`.
