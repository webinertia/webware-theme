# Configuration

Everything sits under one key. The keys have constants on `Webware\Theme\ConfigProvider`.

```php
use Webware\Theme\ConfigProvider;

return [
    ConfigProvider::THEME => [                 // 'theme'
        ConfigProvider::ACTIVE => 'acme',      // 'active'  the theme to use; absent or empty means 'default'
        ConfigProvider::THEMES => [            // 'themes'  template overrides per theme
            'acme' => [
                'layout::default'      => __DIR__ . '/../../templates/acme/layout/default.phtml',
                'app::home-page.phtml' => __DIR__ . '/../../templates/acme/app/home-page.phtml',
            ],
        ],
        ConfigProvider::ASSETS => [            // 'assets'  asset values per theme
            'default' => ['theme.css' => 'css/theme.css'],
            'acme'    => ['theme.css' => 'css/acme.css'],
        ],
    ],
];
```

| Key | Type | Meaning |
|---|---|---|
| `theme.active` | non-empty string | The active theme. A missing or empty value means `default`. |
| `theme.themes.<theme>.<address>` | absolute path | A template address the theme overrides. |
| `theme.assets.<theme>.<name>` | string | An asset value: a path relative to the theme's directory, or an absolute URL. |

## Rules

- Addresses are mezzio's namespaced ones (`namespace::name`) and never contain a theme name.
- Anything a theme does not list falls back to the `default` theme, address by address (templates) and
  name by name (assets).
- A path in `theme.themes` is trusted: it is used as written and not checked on disk.
- Configuration merges with the usual config aggregator rules: string keys are later-wins, so an
  application's autoload file overrides a package's defaults.
- The active theme is read once, when the factories run. Changing it means clearing the aggregated config
  cache.

## Where the keys are planned to live

Per the decisions log (`specs/001-theme-resolution/decisions.md`, D-012 and D-020): a future command will write
each theme's `theme.themes` map to `config/autoload/theme.{theme-name}.global.php` and `theme.active` to
`config/autoload/theme.settings.global.php`. Until then, write them by hand in any autoload file.
