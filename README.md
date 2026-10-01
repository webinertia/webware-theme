# webware/webware-theme

Theme support for Webware applications. A theme is a set of template and asset overrides; the active theme
is consulted first and `default` answers for everything it does not override. Template lookups are a
single array read, with no filesystem walk.

[![PHP Version](https://img.shields.io/packagist/php-v/webware/webware-theme)](https://packagist.org/packages/webware/webware-theme)
[![Latest Version](https://img.shields.io/packagist/v/webware/webware-theme)](https://packagist.org/packages/webware/webware-theme)
[![License](https://img.shields.io/github/license/webinertia/webware-theme)](LICENSE)
[![Required CI](https://github.com/webinertia/webware-theme/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml/badge.svg)](https://github.com/webinertia/webware-theme/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml)
[![codecov](https://codecov.io/gh/webinertia/webware-theme/graph/badge.svg)](https://codecov.io/gh/webinertia/webware-theme)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fwebinertia%2Fwebware-theme%2F1.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/webinertia/webware-theme/1.0.x)

## Quickstart

1. Install it and register the provider **after** `Mezzio\LaminasView\ConfigProvider`:

```bash
composer require webware/webware-theme
```

```php
Mezzio\LaminasView\ConfigProvider::class,
Webware\Theme\ConfigProvider::class,
```

2. Describe a theme in any autoloaded config file, for example `config/autoload/theme.acme.global.php`:

```php
use Webware\Theme\ConfigProvider;

return [
    ConfigProvider::THEME => [
        ConfigProvider::ACTIVE => 'acme',
        ConfigProvider::THEMES => [
            'acme' => [
                // only the addresses this theme overrides
                'layout::default' => __DIR__ . '/../../templates/acme/layout/default.phtml',
            ],
        ],
        ConfigProvider::ASSETS => [
            'acme' => ['theme.css' => 'css/acme.css'],
        ],
    ],
];
```

3. Put the asset file at `public/theme/acme/css/acme.css`, and use it in a template:

```php
<link rel="stylesheet" href="<?= $this->asset('theme.css') ?>">
```

`layout::default` now comes from the `acme` theme and every other address from `default`;
`asset('theme.css')` returns `/theme/acme/css/acme.css`, and a name `acme` does not define falls back to the
`default` theme's file. Anything not listed in a theme falls back to `default` per address and per name.
Addresses stay mezzio's namespaced ones (`<namespace>::<name>`) and never contain a theme name, so a
component's references to its own templates survive a theme change.

## Documentation

| Document | Contents |
|---|---|
| [Installation](docs/v1/installation.md) | Requirements, provider order, what the provider registers |
| [Configuration](docs/v1/configuration.md) | The `theme.active`, `theme.themes` and `theme.assets` keys and their rules |
| [Template resolution](docs/v1/template-resolution.md) | How `ThemeResolver` answers, and the resolver order |
| [Assets](docs/v1/assets.md) | The theme-aware `asset()` helper, URL layout, theme-name rules |

Not built yet: publishing assets into `public/`, the command that creates and switches themes, and the
admin widget. They are specified in `specs/001-theme-resolution/`.

## Design

The design and its measurements live with the component, in `specs/001-theme-resolution/`:

| Document | Contents |
|---|---|
| `spec.md` | Requirements, user stories, success criteria |
| `plan.md` | Approach, and the measurements it answers to |
| `research.md` | What was measured, and why the earlier renderer-modifying approach was dropped |
| `data-model.md` | Entities, the layout on disk, resolution, the configuration shape |
| `tasks.md` | The task list |
| `quickstart.md` | Using a theme, from either side |

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
