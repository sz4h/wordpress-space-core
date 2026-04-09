# Space Core

**Version:** 1.0.0  
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
| **Custom Fields** | Add meta fields (text, textarea, select, checkbox, date, image) to any post type |
| **WooCommerce Checkout Fields** | Add, edit, reorder, or remove billing/shipping/order fields |
| **Safe SVG Upload** | Allow SVG uploads with DOMDocument-based sanitization (strips scripts & on* attrs) |
| **WhatsApp Float** | Floating WhatsApp button with color picker, load/hover animations, LTR/RTL positioning |
| **PWA** | Web app manifest at `/manifest.json`, service worker at `/sw.js`, configurable cache strategy |
| **Custom Code** | Inject custom CSS/JS to frontend, admin, or both |
| **Stock Notifier** | Notify customers via email, SMS (SMSBox.com), or WhatsApp (Evolution API) when OOS products return to stock |
| **Admin Cleaner** | Two-page tool: hide dashboard widgets; reorder, hide, or promote admin menu items |
| **Stats** | Dashboard stats page with sortable widget cards and Chart.js charts (revenue line, status pie, country bar) |
| **Local Shipping** | WooCommerce shipping zones based on city/area DB tables |
| **Guest Orders** | Paginated table of WooCommerce guest orders grouped by billing phone, with CSV export |
| **Store Notices** | DB-backed frontend notices with multilingual text (EN/AR), scheduling, and granular page targeting |
| **Print Orders** | Print orders as A4 or 80mm thermal receipt — single, from order detail, or bulk (one order per page) |
| **Admin Nav** | Configurable mobile-only fixed bottom navigation bar with Material Icons and active-tab highlighting |
| **Gift Wrap** | Add a gift wrap option at WooCommerce checkout with multilingual labels (EN/AR) |
| **Main Config** | Site-wide configuration shortcuts (logo, colors, contact info) |
| **Order Statuses** | Register custom WooCommerce order statuses |

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

Each enabled module with settings appears as a **sidebar submenu** under **Space Core** in the WordPress admin. There are no horizontal tabs. Self-managed modules (Stats, Local Shipping, Guest Orders, Admin Cleaner) register their own menu pages independently.

---

## Module Details

### Stats
- Accessible to `manage_woocommerce` capability (Admins + Shop Managers)
- Located under **Dashboard → Store Stats** (`index.php?page=sc-stats`)
- **Sortable widget cards** — drag to reorder; order saved in `localStorage`
- **Revenue chart** (line) — month/year selector, daily data points, AJAX-loaded, max 3 years back
- **Order status chart** (pie) — processing, completed, refunded, on-hold, cancelled
- **Visitor countries chart** (horizontal bar) — top 10 countries by unique visitor count
- Chart.js is bundled locally at `assets/js/chart.min.js` (no CDN)

### Guest Orders
- Table of WooCommerce guest orders (customer_id = 0) grouped by billing phone
- **Columns:** Name, Phone, Email, # Orders, Total Spent, View Orders
- **View Orders** links to the WC orders list pre-filtered by phone (`?s={phone}&post_type=shop_order`)
- Search by billing phone (partial match) with optional date range filter
- Paginated (20 per page)
- **Export CSV** includes: Name, Phone, Email, Number of Orders, Total Spent

### Store Notices
- Notices are stored in a dedicated DB table `{prefix}sc_store_notices`
- **Multilingual:** Title and Message stored as JSON with `en`/`ar` keys; displayed per current site locale with fallback to `en`
- **Scheduling:** `start_at` / `end_at` datetime fields
- **Page targeting (OR logic across dimensions):**
  - `pages` — specific WP pages or all pages (`["*"]`)
  - `posts` — specific posts or any singular (`["*"]`)
  - `products` — specific products or any product page (`["*"]`)
  - `categories` — specific blog categories
  - `product_cat` — specific WooCommerce product categories
  - If all dimensions are null (unset) — notice shows everywhere
- Material Icons font served locally (`assets/fonts/MaterialIcons-Regular.woff2`)
- Dismissible with `sessionStorage` persistence

### Print Orders
- Row action links on the WC orders list show **"A4"** and **"80mm"** text labels
- Print page opens in a **new browser tab** with no WP sidebar
- **Bulk print** wraps each order in its own page block (`page-break-after: always`; last order uses `avoid`)
- **Settings page** (configurable via Space Core → Print Orders):
  - Shop name override
  - Logo (WP Media picker)
  - Store address (shown on A4 header)
  - Custom footer message
  - Default format (A4 / Thermal 80mm)

### Admin Cleaner
Registered as two separate admin pages under WooCommerce:
- **Admin Widgets** (`sc-admin-widgets`) — toggle dashboard widget visibility
- **Admin Menu** (`sc-admin-menu`) — drag to reorder, hide menu items, promote submenus to top-level links

Menu order fix: saved order is merged with any new items not yet in the list, so newly installed plugins' menu items appear correctly.

Promote fix: promoted submenus are registered as real `add_menu_page()` entries with a redirect callback — they appear in the sidebar as direct links.

### Admin Nav
- Mobile-only fixed bottom navigation bar (`@media (max-width: 782px)`)
- Light theme: white background, rounded top corners (`border-radius: 16px 16px 0 0`), box shadow, 8px side margin
- **Home behavior:** resolves dynamically — `manage_woocommerce` users → Stats page; others → `/wp-admin/`
- Each nav item is fully configurable (label, URL, icon name, URL match fragment) via Settings
- Drag-to-reorder in settings; saved to `space_core_admin_nav` option
- Material Icons font served locally (no CDN)

### Gift Wrap
- Adds a checkbox (and optional message field) at WooCommerce checkout
- **Multilingual labels:** separate EN and AR fields stored as `label_en`, `label_ar`, `message_label_en`, `message_label_ar`
- Label resolved at render time via `substr(get_locale(), 0, 2)` with fallback to EN

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
- After saving settings, flush permalinks at **Settings → Permalinks** if routes return 404

---

## Database Tables

| Table | Module | Purpose |
|---|---|---|
| `{prefix}sc_stock_subscribers` | Stock Notifier | Back-in-stock subscriber records |
| `{prefix}sc_ls_cities` | Local Shipping | Shipping city list |
| `{prefix}sc_ls_areas` | Local Shipping | Shipping area list with rates |
| `{prefix}sc_visitors` | Stats | Visitor tracking with country resolution |
| `{prefix}sc_store_notices` | Store Notices | Notice records with JSON targeting columns |

All tables are dropped on plugin uninstall.

---

## Assets

| File | Purpose |
|---|---|
| `assets/css/admin.css` | Admin styles |
| `assets/css/front.css` | Frontend styles (WhatsApp button, stock notifier form) |
| `assets/js/admin.js` | Admin JS (color pickers, media uploader, module cards) |
| `assets/js/front.js` | Frontend JS (stock notifier AJAX form) |
| `assets/js/chart.min.js` | Chart.js 4.4.4 — bundled locally, used by Stats |
| `assets/fonts/MaterialIcons-Regular.woff2` | Google Material Icons font — bundled locally, used by Admin Nav and Store Notices |

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
│   │   └── front.css
│   ├── js/
│   │   ├── admin.js
│   │   ├── front.js
│   │   └── chart.min.js          ← Chart.js 4.4.4 (local)
│   └── fonts/
│       └── MaterialIcons-Regular.woff2  ← Material Icons (local)
└── src/
    ├── Plugin.php
    ├── ModuleManager.php
    ├── Contracts/ModuleInterface.php
    ├── Abstracts/AbstractModule.php
    ├── Admin/
    │   ├── AdminMenu.php
    │   └── SettingsAPI.php
    └── Modules/
        ├── CustomPostTypes/Module.php
        ├── CustomFields/Module.php
        ├── WooCheckoutFields/Module.php
        ├── SafeSVG/Module.php
        ├── WhatsAppFloat/Module.php
        ├── PWA/Module.php
        ├── CustomCode/Module.php
        ├── MainConfig/Module.php
        ├── GiftWrap/Module.php
        ├── OrderStatuses/Module.php
        ├── AdminNav/Module.php
        ├── PrintOrders/Module.php
        ├── StoreNotices/Module.php
        ├── GuestOrders/Module.php
        ├── AdminCleaner/Module.php
        ├── Stats/
        │   ├── Module.php
        │   └── VisitorDB.php
        ├── LocalShipping/Module.php
        └── StockNotifier/
            ├── Module.php
            ├── SubscriberDB.php
            ├── NotifyJob.php
            └── Channels/
                ├── EmailChannel.php
                ├── SmsChannel.php
                └── WhatsappChannel.php
```

---

## Changelog

### 1.0.0
- Initial release with all core modules
- Stats page with sortable widget cards and local Chart.js charts (line/pie/bar)
- Admin navigation sidebar submenus (replacing horizontal tabs)
- Guest Orders: grouped by billing phone, paginated, CSV export
- Store Notices: DB table with multilingual JSON, multi-dimension page targeting
- Print Orders: A4 / 80mm thermal, new-tab print, bulk page-break, configurable template
- Admin Nav: mobile-only rounded light bar, local Material Icons, configurable items
- Admin Cleaner: split into two pages (Widgets / Menu), fixed sorting merge, promote-to-top-level
- Gift Wrap: multilingual EN/AR checkbox and message labels
