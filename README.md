# Space Core

**Version:** 2.0.0  
**Author:** Ahmed Safaa / [Space Zone](https://sz4h.com)  
**Requires WordPress:** 6.0+  
**Requires PHP:** 8.0+  
**License:** GPL-2.0-or-later  

A comprehensive, modular core WordPress plugin for Space Zone websites.  
Each feature is independently togglable from the admin panel.

---

## Features

| Module | Description |
|---|---|
| **Custom Post Types** | Register CPTs and define parent→child relationships via JSON |
| **Custom Taxonomies** | Register custom taxonomies and attach them to any post type; configure hierarchical, public, REST, and admin-column visibility |
| **Custom Fields** | Add meta fields (text, textarea, select, checkbox, date, image) to any post type |
| **WooCommerce Checkout Fields** | Add, edit, reorder, or remove billing/shipping/order fields with per-field column width (full, left, right) for 1- or 2-column layouts |
| **Safe SVG Upload** | Allow SVG uploads with DOMDocument-based sanitization (strips scripts & on* attrs) |
| **WhatsApp Float** | Floating WhatsApp button with color picker, load/hover animations, LTR/RTL positioning |
| **PWA** | Web app manifest at `/manifest.json`, service worker at `/sw.js`, configurable cache strategy |
| **Custom Code** | Inject custom CSS/JS to frontend, admin, or both |
| **Stock Notifier** | Notify customers via email, SMS (SMSBox.com), or WhatsApp (Evolution API) when OOS products return to stock |
| **Admin Menu** | Organize admin menu: drag to reorder, hide, rename items, promote submenus to top-level, add custom links, JS link rewrites, per-role visibility |
| **Admin Widgets** | Hide unwanted WordPress dashboard widgets; on-demand snapshot refresh to detect newly added widgets |
| **Stats** | Dashboard stats page with sortable widget cards: revenue chart, order status, visitor countries, avg order value, top-selling products, recent orders, low-stock products |
| **Fixed Shipping by City** | WooCommerce shipping based on city/area DB tables linked to ISO2 country code; falls back to WC's standard country/state dropdowns for countries without configured cities |
| **Guest Orders** | Paginated table of WooCommerce guest orders grouped by billing phone, with CSV export |
| **Store Notices** | Fixed bottom frontend notices with multilingual text (EN/AR), scheduling, Select2 AJAX page targeting, live preview, mobile-optimized layout |
| **Print Orders** | Print orders as A4 or 80mm thermal receipt — single, from order detail, or bulk (one order per page); dashicon button on orders list; no admin notices in print dialog |
| **Admin Nav** | Configurable mobile-only fixed bottom navigation bar with Material Symbols Outlined (Google CDN) and active-tab highlighting |
| **Gift Wrap** | Add a gift wrap option at WooCommerce checkout with multilingual labels (EN/AR) |
| **Main Config** | Site-wide configuration: logo, colors, contact info, footer URL, remove comments from admin bar/menu |
| **Order Statuses** | Register custom WooCommerce order statuses |
| **Multi-Currency** | Display prices and accept orders in multiple currencies; GeoIP auto-detection, rate API sync via cron, payment gateway filtering, [sc_currency_switcher] shortcode |
| **Admin Bar Manager** | Control WordPress admin bar visibility — hide it by role or remove specific toolbar nodes |
| **Media Offload** | Offload media uploads to external object storage (BunnyCDN / DigitalOcean Spaces / Cloudflare R2) with bulk offload, thumbnail regeneration, and URL migration/restore/fix tools |
| **Translation** | REST API (`space-core/v1`) endpoints for bulk fetching and filling missing translations across posts, pages, CPTs, terms, and menus (Polylang / WPML); schema endpoint and Postman collection export |
| **WPML Translate** | Read-only WPML translation-job discovery and XLIFF payload extraction, exposed via REST and exportable as a Postman collection |
| **Bulk Manage Content** | Inline bulk-edit posts and taxonomy terms with custom meta fields, conditional row display, and multilingual support |

---

## Installation

### Development (without Composer)
The plugin includes a fallback PSR-4 autoloader — you can activate it immediately without running `composer install`.

### Production
```bash
cd wp-content/plugins/space-core
composer install --no-dev --optimize-autoloader
```

---

## Admin Navigation

Each enabled module with settings appears as a **sidebar submenu** under **Space Core** in the WordPress admin. There are no horizontal tabs. Self-managed modules (Stats, Fixed Shipping by City, Guest Orders, Admin Menu) register their own menu pages independently.

---

## Shared SettingsAPI + View Renderer

Space Core includes a reusable admin rendering layer for WordPress module development.

- `Space\Core\Abstracts\AbstractModule::view()` renders module templates from `views/admin/...` and `views/front/...`
- `Space\Core\Admin\SettingsAPI` renders shared admin field markup from `src/Admin/views/...`
- Module classes keep business logic, sanitization, hooks, AJAX handlers, and data preparation
- Templates keep presentation and reusable markup partials

This makes the plugin easier to maintain across WordPress projects because common settings fields can be reused without duplicating HTML in every module class.

### Shared field helpers

- `SettingsAPI::text()`
- `SettingsAPI::textarea()`
- `SettingsAPI::select()`
- `SettingsAPI::checkbox()`
- `SettingsAPI::color()`
- `SettingsAPI::number()`
- `SettingsAPI::url()`
- `SettingsAPI::hidden()`
- `SettingsAPI::multiselect()`
- `SettingsAPI::open_form()`
- `SettingsAPI::close_form()`

Each field helper accepts optional HTML attributes as the last argument so module admin views can preserve existing IDs, classes, `dir`, `style`, and JS selectors.

### Example

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

### Recommended structure

```text
src/Admin/views/
└── fields/

src/Modules/MyModule/views/
├── admin/
└── front/
```

Theme overrides for module views follow:

```text
your-theme/space-core/{module-slug}/admin/...
your-theme/space-core/{module-slug}/front/...
```

---

## Module Details

### Stats
- Accessible to `manage_woocommerce` capability (Admins + Shop Managers)
- Located under **Dashboard → Store Stats** (`index.php?page=sc-stats`)
- **Sortable widget cards** — drag to reorder; order saved in `localStorage`
- **Revenue chart** (line) — month/year selector, daily data points, AJAX-loaded, max 3 years back
- **Order status chart** (pie) — processing, completed, refunded, on-hold, cancelled
- **Visitor countries chart** (horizontal bar) — top 10 countries by unique visitor count
- **Average Order Value** — displayed alongside total orders and revenue
- **Top 10 Best-Selling Products** — by quantity sold across completed/processing orders
- **Recent Orders** — last 10 orders with customer, status badge, and total
- **Low Stock Products** — products with stock ≤ 5; highlights critical (≤ 2) in red
- Chart.js is bundled locally at `assets/js/chart.min.js` (no CDN)

### Guest Orders
- Table of WooCommerce guest orders (customer_id = 0) grouped by billing phone
- **Columns:** Name, Phone, Email, # Orders, Total Spent, View Orders
- **HPOS-safe query path** uses WooCommerce order APIs instead of direct `wp_posts` / `wp_postmeta` SQL
- **View Orders** links to the WooCommerce orders list pre-filtered by phone, using the correct orders screen when HPOS is enabled
- Search by billing phone (partial match) with optional date range filter
- Paginated (20 per page)
- **Export CSV** includes: Name, Phone, Email, Number of Orders, Total Spent

### Store Notices
- Notices are stored in a dedicated DB table `{prefix}sc_store_notices`
- **Multilingual:** Title and Message stored as JSON with `en`/`ar` keys; displayed per current site locale with fallback to `en`
- **Scheduling:** `start_at` / `end_at` datetime fields
- **Page targeting (OR logic across dimensions) via Select2 AJAX search:**
  - `pages` — specific WP pages or all pages (`["*"]`)
  - `posts` — specific posts or any singular (`["*"]`)
  - `products` — specific products or any product page (`["*"]`)
  - `categories` — specific blog categories
  - `product_cat` — specific WooCommerce product categories
  - If all dimensions are null (unset) — notice shows everywhere
- **Live preview** in settings card — updates icon, title, and message in real time as you type
- **Mobile-optimized** — responsive layout with proper flex wrapping and font scaling
- Icon rendered using Material Symbols Outlined (Google CDN)
- Dismissible with `sessionStorage` persistence

### Print Orders
- Row action buttons on the WC orders list show a **dashicon printer icon** (not text labels)
- Compatible with both legacy and HPOS (High-Performance Order Storage) order screens
- Print page opens in a **new browser tab** with no WP sidebar, no admin notices
- **Bulk print** available from orders list (both legacy and HPOS); wraps each order in its own page block (`page-break-after: always`; last order uses `avoid`)
- Print styles in dedicated `assets/css/admin-print.css`
- **Settings page** (configurable via Space Core → Print Orders):
  - Shop name override
  - Logo (WP Media picker)
  - Store address (shown on A4 header)
  - Custom footer message
  - Default format (A4 / Thermal 80mm)

### Admin Menu
- Accessible at **Space Core → Admin Menu** (`sc-admin-menu`)
- **Drag to reorder** — live drag-and-drop for top-level menu items
- **Hide items** — remove unwanted top-level or submenu entries per role
- **Rename items** — override display label for any menu or submenu item
- **Promote submenu** — move a submenu item to appear as a top-level sidebar link
- **Custom links** — add new top-level admin links with custom URL, label, and dashicon
- **Link rewrites** — JS-level URL replacements (selector → URL, optionally open in new tab)
- **Role-based visibility** — show/hide items per user role
- Snapshot captured at `admin_menu` priority 998; customisations applied at 999

### Admin Widgets
- Accessible at **Space Core → Admin Widgets** (`sc-admin-widgets`)
- Lists all registered WordPress dashboard widgets with toggle to hide each one
- **Refresh Widget List** button — calls `wp_dashboard_setup()` on-demand to capture any new widgets without visiting the dashboard page
- Hidden widget IDs saved to `space_core_admin_widgets` option

### Custom Taxonomies
- Register custom WordPress taxonomies via a table-based admin UI
- Per-taxonomy settings: slug, singular name, plural name, attached post types (multiselect), hierarchical, public, show in REST, show admin column
- Taxonomies registered at `init` priority 6 (before post types at default 10)
- Definitions stored as JSON in `space_core_taxonomies` option

### Admin Nav
- Mobile-only fixed bottom navigation bar (`@media (max-width: 782px)`)
- Light theme: white background, rounded top corners (`border-radius: 16px 16px 0 0`), box shadow, 8px side margin
- **Home behavior:** resolves dynamically — `manage_woocommerce` users → Stats page; others → `/wp-admin/`
- Each nav item is fully configurable (label, URL, icon name, URL match fragment) via Settings
- Drag-to-reorder in settings; saved to `space_core_admin_nav` option
- Icons via **Material Symbols Outlined** from Google Fonts CDN

### Gift Wrap
- Adds a checkbox (and optional message field) at WooCommerce checkout
- **Multilingual labels:** separate EN and AR fields stored as `label_en`, `label_ar`, `message_label_en`, `message_label_ar`
- Label resolved at render time via `substr(get_locale(), 0, 2)` with fallback to EN

### Main Config
- **Logo:** replace WP logo in admin bar with a custom image; links to site URL
- **Footer text:** custom admin footer text with optional URL link
- **Colors:** primary/accent color pickers
- **Contact info:** address, phone, email displayed in admin footer
- **Remove comments:** hides Comments from admin menu, admin bar, and post list columns
- **Admin bar:** removes WP logo node; replaces with site logo

### Stock Notifier
- Frontend form auto-injects on out-of-stock product pages (configurable hook position)
- Shortcode: `[sc_stock_notifier]` or `[sc_stock_notifier product_id="123"]`
- Cron runs hourly via `sc_stock_notify` WP-Cron event
- Template tags: `{product_name}`, `{product_url}`, `{site_name}`
- **SMS**: SMSBox.com API — configure API key and sender ID
- **WhatsApp**: Evolution API — configure base URL, API key, and instance name

### Custom Post Types
```json
[
  {
    "slug": "portfolio",
    "name": "Portfolio",
    "plural": "Portfolios",
    "public": true,
    "has_archive": true,
    "menu_icon": "dashicons-portfolio",
    "show_in_rest": true,
    "supports": ["title", "editor", "thumbnail"]
  }
]
```

### PWA
- Manifest route: `yourdomain.com/manifest.json`
- Service Worker route: `yourdomain.com/sw.js`
- Service worker skips POST requests (avoids Cache API unsupported-method error)
- After saving settings, flush permalinks at **Settings → Permalinks** if routes return 404

### Media Offload
- Offloads media library uploads to external object storage; stored config in `space_core_media_offload` option
- **Pluggable storage adapters** behind `StorageAdapterInterface`: **BunnyCDN** (`BunnyAdapter`), **DigitalOcean Spaces** (`DOSpacesAdapter`), and **Cloudflare R2** (`CloudflareR2Adapter`, S3-compatible AWS SigV4 with `auto` region), sharing an `AbstractAdapter` base
- **Tools** (AJAX-driven, batched): test connection, offload existing media in batches, regenerate thumbnails, migrate URLs to the CDN, restore URLs back to local, fix broken URLs, and **transfer already-offloaded files between providers** (downloads from a source adapter and re-uploads to the active destination, preserving object keys, with optional delete-from-source and dry-run)
- Offload state tracked per-attachment via `_sc_media_offloaded`, `_sc_media_key`, and `_sc_media_files` meta
- **Theme Asset CDN:** rewrites active/parent theme CSS, JS, font, and image URLs to a configured CDN base on the frontend, hooking `style_loader_src`, `script_loader_src`, `stylesheet_directory_uri`, `template_directory_uri`, and `stylesheet_uri`. Source bases are read live from the theme directory URIs (no stored paths); fonts/images referenced relatively inside a stylesheet resolve against the CDN automatically once the stylesheet is served from it. Config keys: `theme_cdn_enabled`, `theme_cdn_active_base`, `theme_cdn_parent_base`

### Translation
- REST API namespace **`space-core/v1`** for bulk fetching and filling missing translations
- **Controllers:** Posts/Pages/CPTs (`PostsController`), terms (`TermsController`), menus (`MenusController`), and a schema endpoint (`SchemaController`)
- **Multilingual adapters** behind `AdapterInterface`: Polylang (`PolylangAdapter`) and WPML (`WpmlAdapter`), selected at runtime by `PluginDetector`
- **Meta filtering** via `MetaFilter` to control which custom fields are exposed/synced
- **Postman collection export** (`PostmanExporter`) — all routes use path variables for easy testing
- Admin config + API reference panels; settings stored in `space_core_translation`

### WPML Translate
- **Read-only** discovery of WPML translation jobs via REST (`space-core/v1`, `JobsController`)
- **XLIFF payload extraction** (`PayloadExtractor`) using WPML's `wpml_tm_get_job_xliff` helper, with graceful fallbacks when WPML TM is unavailable
- `Watcher` and `Repository` for job tracking; **Postman collection export** of the job endpoints

### Bulk Manage Content
- Inline bulk-edit UI for **posts and taxonomy terms** at a dedicated admin page
- Edit **custom meta fields** inline (integrates with the Custom Fields module), including image fields with lightbox preview
- **Conditional row display** — show rows only when a record has a given field filled
- **Multilingual support** via `MultilingualHelper` (Polylang / WPML), syncing field values across translations
- Config stored in `space_core_bulk_manage_content`

### Admin Bar Manager
- Hide the WordPress admin bar entirely **per user role**, or remove specific toolbar nodes
- Selected nodes saved to `space_core_admin_bar` / `space_core_admin_bar_nodes`

---

## Database Tables

| Table | Module | Purpose |
|---|---|---|
| `{prefix}sc_stock_subscribers` | Stock Notifier | Back-in-stock subscriber records |
| `{prefix}sc_ls_cities` | Fixed Shipping by City | Shipping city list with ISO2 country_code |
| `{prefix}sc_ls_areas` | Fixed Shipping by City | Shipping area list with rates |
| `{prefix}sc_visitors` | Stats | Visitor tracking with country resolution |
| `{prefix}sc_store_notices` | Store Notices | Notice records with JSON targeting columns |

All tables are dropped on plugin uninstall.

---

## Assets

| File | Purpose |
|---|---|
| `assets/css/admin.css` | Admin styles |
| `assets/css/admin-print.css` | Print-specific styles for Print Orders |
| `assets/css/front.css` | Frontend styles (WhatsApp button, stock notifier form, store notices) |
| `assets/js/admin.js` | Admin JS (color pickers, media uploader, module cards, admin menu UI) |
| `assets/js/front.js` | Frontend JS (stock notifier AJAX form) |
| `assets/js/chart.min.js` | Chart.js 4.4.4 — bundled locally, used by Stats |

Icons via **Material Symbols Outlined** loaded from Google Fonts CDN (no local font file).

---

## Translation

All strings use the `space-core` text domain.  
A `.pot` template is available at `languages/space-core.pot`.

```bash
wp i18n make-pot . languages/space-core.pot --domain=space-core
```

---

## File Structure

```
space-core/
├── space-core.php
├── composer.json
├── README.md
├── CLAUDE.md
├── languages/
│   └── space-core.pot
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   ├── admin-print.css
│   │   └── front.css
│   └── js/
│       ├── admin.js
│       ├── front.js
│       └── chart.min.js          ← Chart.js 4.4.4 (local)
└── src/
    ├── Plugin.php
    ├── ModuleManager.php
    ├── Contracts/ModuleInterface.php
    ├── Abstracts/AbstractModule.php
    ├── Admin/
    │   ├── views/
    │   │   ├── fields/
    │   │   └── form/
    │   ├── AdminMenu.php
    │   └── SettingsAPI.php
    └── Modules/
        ├── CustomPostTypes/Module.php
        ├── CustomTaxonomies/Module.php
        ├── CustomFields/Module.php
        ├── WooCheckoutFields/Module.php
        ├── SafeSVG/Module.php
        ├── WhatsAppFloat/Module.php
        ├── PWA/Module.php
        ├── CustomCode/Module.php
        ├── MainConfig/Module.php
        ├── GiftWrap/Module.php
        ├── OrderStatuses/Module.php
        ├── AdminMenu/Module.php
        ├── AdminWidgets/Module.php
        ├── AdminNav/Module.php
        ├── AdminBar/Module.php
        ├── PrintOrders/Module.php
        ├── StoreNotices/Module.php
        ├── GuestOrders/Module.php
        ├── MultiCurrency/Module.php
        ├── MediaOffload/
        │   ├── Module.php
        │   ├── StorageAdapterInterface.php
        │   ├── AbstractAdapter.php
        │   ├── BunnyAdapter.php
        │   ├── DOSpacesAdapter.php
        │   └── CloudflareR2Adapter.php
        ├── Translation/
        │   ├── Module.php
        │   ├── PostsController.php
        │   ├── TermsController.php
        │   ├── MenusController.php
        │   ├── SchemaController.php
        │   ├── AdapterInterface.php
        │   ├── PolylangAdapter.php
        │   ├── WpmlAdapter.php
        │   ├── PluginDetector.php
        │   ├── MetaFilter.php
        │   └── PostmanExporter.php
        ├── WPMLTranslate/
        │   ├── Module.php
        │   ├── JobsController.php
        │   ├── PayloadExtractor.php
        │   ├── Repository.php
        │   ├── Watcher.php
        │   └── PostmanExporter.php
        ├── BulkManageContent/
        │   ├── Module.php
        │   └── MultilingualHelper.php
        ├── Stats/
        │   ├── Module.php
        │   └── VisitorDB.php
        ├── LocalShipping/
        │   ├── Module.php
        │   ├── AreasDB.php
        │   └── Seeders/Kuwait.php
        └── StockNotifier/
            ├── Module.php
            ├── views/
            ├── SubscriberDB.php
            ├── NotifyJob.php
            └── Channels/
                ├── EmailChannel.php
                ├── SmsChannel.php
                └── WhatsappChannel.php
```

---

## Changelog

### 2.0.0
- **Media Offload:** offload uploads to BunnyCDN / DigitalOcean Spaces / Cloudflare R2 with pluggable storage adapters; batched offload, thumbnail regeneration, URL migrate/restore/fix tools, provider-to-provider transfer, and a Theme Asset CDN that rewrites active/parent theme CSS/JS/font/image URLs to a CDN base
- **Translation:** `space-core/v1` REST API for bulk fetching/filling missing translations across posts, pages, CPTs, terms, and menus (Polylang / WPML adapters), schema endpoint, meta filtering, and Postman collection export
- **WPML Translate:** read-only WPML job discovery and XLIFF payload extraction via REST, with Postman export
- **Bulk Manage Content:** inline bulk-edit of posts and taxonomy terms with custom meta fields, conditional row display, image lightbox, and multilingual sync
- **Admin Bar Manager:** hide the admin bar by role or remove specific toolbar nodes

### 1.0.0
- Initial release with all core modules
- **Shared SettingsAPI + view renderer:** reusable admin field templates in `src/Admin/views`, module-local `views/admin` / `views/front`, and `AbstractModule::view()` based rendering
- **Stats:** sortable widget cards + Chart.js charts (line/pie/bar); new widgets: avg order value, top-selling products, recent orders, low stock
- **Admin Menu:** drag-reorder, hide, rename, promote submenus, custom links, link rewrites, role-based visibility
- **Admin Widgets:** dashboard widget toggle with on-demand refresh
- **Custom Taxonomies:** register taxonomies with full WP settings
- **Store Notices:** DB table with multilingual JSON, Select2 AJAX targeting, live preview, mobile layout
- **Print Orders:** dashicon button, HPOS bulk print, no admin notices in print, dedicated print CSS
- **Fixed Shipping by City:** city/area tables with ISO2 country code, checkout country fallback
- **Admin Nav:** mobile rounded bottom bar with Material Symbols Outlined (CDN)
- **WooCommerce Checkout Fields:** per-field column width (full/left/right)
- **Main Config:** footer URL, comments admin bar removal
- **PWA:** service worker skips POST requests
