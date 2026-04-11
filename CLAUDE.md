# CLAUDE.md — Space Core Plugin Context

This file helps Claude Code understand the project structure and conventions at the start of any session.

---

## Project Identity

- **Plugin name:** Space Core
- **Author:** Ahmed Safaa / Space Zone (https://sz4h.com)
- **Text domain:** `space-core`
- **Plugin slug / directory:** `space-core`
- **Version:** 1.0.0

---

## Architecture Pattern

### Namespace

```
Space\Core\
```

Follows PSR-4 autoloading via `composer.json`. Fallback manual autoloader in `space-core.php` for development without
`composer install`.

### Bootstrap Flow

```
space-core.php
  └── plugins_loaded
        └── Space\Core\Plugin::instance()
              └── ModuleManager::init()
                    ├── Admin\AdminMenu (always loaded)
                    └── foreach enabled module → Module::boot()
```

### Module Pattern

Every feature is a **module**:

- Located in `src/Modules/<FeatureName>/Module.php`
- Extends `Space\Core\Abstracts\AbstractModule`
- Implements `Space\Core\Contracts\ModuleInterface`
- Required methods: `boot()`, `get_label()`, `get_description()`
- Optional methods: `render_settings()`, `on_activate()`

To add a new module:

1. Create `src/Modules/MyFeature/Module.php`
2. Register it in `ModuleManager::$registry` with a snake_case slug key

### View Rendering Pattern

`Space\Core\Abstracts\AbstractModule` exposes:

```php
echo $this->view( 'admin/settings', [
    'options' => $options,
] );
```

Rules:

- `view()` always returns a rendered HTML string
- module classes should prepare data, then call `echo $this->view(...)`
- templates resolve in this order:
  1. theme override: `space-core/{module-slug}/{view}.php`
  2. module fallback: `src/Modules/<ModuleName>/views/{view}.php`
- admin templates live under `views/admin/...`
- frontend templates live under `views/front/...`
- keep hooks, sanitization, queries, and AJAX handlers in `Module.php`
- move markup into view files and partials
- extracted variables from `$data` are available inside the template, and `$this` still refers to the module instance

### Settings Storage

Each module uses a single WP option key:
| Module | Option key |
|---|---|
| Module toggles | `space_core_modules` |
| Custom Post Types | `space_core_cpts`, `space_core_cpt_relations` |
| Custom Fields | `space_core_custom_fields` |
| WooCommerce Checkout | `space_core_woo_checkout_fields` |
| WhatsApp Float | `space_core_whatsapp_float` |
| PWA | `space_core_pwa` |
| Custom Code | `space_core_custom_code` |
| Stock Notifier | `space_core_stock_notifier` |
| Admin Cleaner | `space_core_admin_cleaner` |
| Local Shipping | `space_core_local_shipping`, tables: `{prefix}sc_ls_cities`, `{prefix}sc_ls_areas` |

### Admin UI

- Single top-level menu page at slug `space-core`
- Tab-based navigation: first tab = Modules toggle grid, subsequent tabs = per-module settings
- Only **enabled** modules appear as tabs
- `Admin\SettingsAPI` provides static helpers backed by shared admin views in `src/Admin/views/...`
- Use `SettingsAPI` for generic reusable controls inside module admin templates
- Keep repeaters, sortable rows, custom tables, previews, and AJAX fragments as module-local view partials
- Current helpers:
  - `::text()`
  - `::textarea()`
  - `::select()`
  - `::checkbox()`
  - `::color()`
  - `::number()`
  - `::url()`
  - `::hidden()`
  - `::multiselect()`
  - `::open_form()`
  - `::close_form()`
- Field helpers accept optional HTML attributes as the last argument so extracted templates can preserve existing IDs, classes, `dir`, `style`, and JS selectors

---

## Key Files

| File                                | Role                                                             |
|-------------------------------------|------------------------------------------------------------------|
| `space-core.php`                    | Plugin header, constants, autoloader, lifecycle hooks            |
| `src/Plugin.php`                    | Singleton bootstrap, `activate()`, `deactivate()`, `uninstall()` |
| `src/ModuleManager.php`             | Registry, enable/disable logic                                   |
| `src/Admin/AdminMenu.php`           | WP menu, tabbed UI, module grid                                  |
| `src/Admin/SettingsAPI.php`         | Shared admin field/form renderer                                 |
| `src/Abstracts/AbstractModule.php`  | Base class for all modules, including `view()` renderer          |
| `src/Contracts/ModuleInterface.php` | Module contract                                                  |

---

## Assets

- `assets/css/admin.css` — Admin styles (module cards, toggle switch, color pickers, cleaner lists)
- `assets/css/front.css` — Frontend styles (WhatsApp float button, stock notifier form)
- `assets/js/admin.js` — Admin JS (wp-color-picker init, WP media uploader, module card highlight)
- `assets/js/front.js` — Frontend JS (stock notifier AJAX form submit)

Both front assets are only enqueued when a relevant module is active (e.g., `WhatsAppFloat\Module::enqueue_assets()`).

---

## Template Structure

Shared admin primitives:

```text
src/Admin/views/
├── form/
│   ├── open.php
│   └── close.php
└── fields/
    ├── text.php
    ├── textarea.php
    ├── select.php
    ├── checkbox.php
    ├── color.php
    ├── number.php
    ├── url.php
    ├── hidden.php
    └── multiselect.php
```

Module-local view structure:

```text
src/Modules/<ModuleName>/views/
├── admin/
└── front/
```

Example:

```text
src/Modules/MultiCurrency/views/admin/currencies/tab.php
src/Modules/MultiCurrency/views/admin/currencies/row.php
src/Modules/MultiCurrency/views/admin/settings/tab.php
src/Modules/MultiCurrency/views/front/switcher/dropdown.php
```

Example `SettingsAPI` usage inside a module admin view:

```php
use Space\Core\Admin\SettingsAPI;

SettingsAPI::text(
    'space_core_sn_group',
    'space_core_stock_notifier',
    'wa_evolution_key',
    $options['wa_evolution_key'],
    '',
    [ 'id' => 'sc-sn-wa-key' ]
);
```

---

## Database

The **Stock Notifier** module creates `{prefix}sc_stock_subscribers`:

```sql
id
, product_id, contact, channel (email|sms|whatsapp), lang, status (pending|notified), created_at
```

Created on plugin activation via `SubscriberDB::create_table()`.

---

## Cron

- Hook: `sc_stock_notify` (hourly)
- Scheduled on activation in `StockNotifier\Module::on_activate()`
- Unscheduled on `Plugin::deactivate()`
- Handler: `StockNotifier\NotifyJob::run()`

---

## Third-Party Integrations

| Service                  | Module        | Config option keys                                              |
|--------------------------|---------------|-----------------------------------------------------------------|
| SMSBox.com               | StockNotifier | `sms_api_key`, `sms_sender`                                     |
| Evolution API (WhatsApp) | StockNotifier | `wa_evolution_url`, `wa_evolution_key`, `wa_evolution_instance` |

---

## Coding Conventions

- PHP 8.0+ — use match, named args, union types, `str_starts_with()`, etc.
- Strict input sanitization via WP functions: `sanitize_text_field`, `sanitize_key`, `esc_url_raw`, `absint`, etc.
- All user-visible strings wrapped in `__()` / `_e()` / `esc_html__()` with domain `space-core`
- No external Composer dependencies — all code is self-contained
- `defined('ABSPATH') || exit;` at the top of every PHP file
- Settings group name pattern: `space_core_{slug}_group`
- Option name pattern: `space_core_{slug}`
- Always update POT file with new strings via `wp-cli` command: `wp i18n make-pot . languages/space-core.pot` when
  adding new strings
- Always update the README.md file with new features
- When there is a release on the github repo, Check the release version and consider these is new version number. Add
  the new feature to the README.md and update the version number in `README.md` and `composer.json`

---

## Adding a New Module (Checklist)

1. Create `src/Modules/MyFeature/Module.php` extending `AbstractModule`
2. Implement `boot()`, `get_label()`, `get_description()`
3. Implement `render_settings()` if the module has admin options
4. Register in `src/ModuleManager.php` under `$registry`
5. Add option key to the `uninstall()` cleanup list in `src/Plugin.php`
6. Add all new strings to `languages/space-core.pot`
