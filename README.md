<img src="assets/banner-772x250.png" alt="Planned Outage for Block Themes plugin banner" style="width: 100%; height: auto;">

# Planned Outage for Block Themes

Simple maintenance mode for block themes. Shows a maintenance template to logged-out visitors while allowing logged-in users to browse normally.

## Requirements

- WordPress 6.6+
- PHP 7.3+
- A block theme

## Installation

1. Upload the plugin files to `/wp-content/plugins/planned-outage`
2. Activate the plugin through the Plugins screen in WordPress

## Usage

1. Create a maintenance template using one of these methods:
   - In the Site Editor (Appearance > Editor > Templates), create a new template named "maintenance"
   - Add a `maintenance.html` file to your theme's `/templates/` folder
2. Go to Settings > Maintenance Mode
3. Configure your options and enable maintenance mode

### Options

- **Enable Maintenance Mode** - Activate maintenance mode for logged-out visitors
- **Scheduled Outage Window** - Set a start and end date/time (in the site timezone) and maintenance mode activates and deactivates automatically; works alongside the manual toggle
- **Expected Duration** - Set the Retry-After header value (30 minutes to 1 day) to tell search engines when to check back, or select Pre-Launch for sites that aren't live yet; during a scheduled window the header automatically reflects the time remaining
- **Search Engine Access** - Allow search engine bots to bypass maintenance mode and continue crawling your site
- **Bypass Link** - Generate a secret URL that lets non-logged-in users browse the site during maintenance

### SEO Recommendations

- **Under 2 hours:** Default settings are fine
- **2-24 hours:** Consider enabling search engine access
- **Over 1 day:** Always enable search engine access to prevent pages from being removed from search indexes

### Pre-Launch Mode

Select "Pre-Launch (indefinite)" from the Expected Duration dropdown when your site isn't live yet. This disables duration tracking, admin duration warnings, the Retry-After header, and the SEO recommendations card.

### Bypass Link

When enabled, a unique URL with a secret token is generated (e.g. `https://yoursite.com/?pobt_bypass=<token>`). Share this link with anyone who needs to preview the site during maintenance. A 12-hour cookie is set on first visit so they can navigate freely without needing the token on every page.

You can regenerate the link at any time to invalidate the previous one.

### When Enabled

- Logged-out visitors see the maintenance template with a 503 status
- Logged-in users can browse the site normally
- Search engine bots can optionally bypass maintenance mode
- Non-logged-in users with a valid bypass link can browse the site normally
- An admin bar notice indicates maintenance mode is active
- A warning appears after 3 days if maintenance mode is still enabled (except in pre-launch mode)

### Scheduled Outage Windows

Set a start and end date/time on the settings page to schedule maintenance in advance. Times are entered in the site timezone and stored as UTC. The window is checked on every request, so activation and deactivation are exact and never depend on WP-Cron — cron is used only for best-effort cache flushing at the window boundaries. Expired windows remain visible on the settings page for reference and can be overwritten with new dates.

### WordPress Core Updates

The maintenance template cannot be shown during real WordPress core/plugin/theme updates. WordPress creates a `.maintenance` file and ends those requests before any plugin code loads, showing its built-in maintenance screen instead. This is a WordPress load-order constraint, not a plugin limitation — no plugin can render a block template at that point. Those windows last at most 10 minutes.

### Cache Compatibility

Full-page caching plugins can interfere with maintenance mode by serving cached pages to visitors instead of the maintenance template. The plugin includes built-in detection and handling for this:

- **Automatic detection** of popular cache plugins (Surge, WP Super Cache, W3 Total Cache, WP Fastest Cache, LiteSpeed Cache, WP Rocket) with a fallback that detects any active `advanced-cache.php` dropin or populated `wp-content/cache/` directory
- **Admin warning** displayed on the settings page when a cache plugin is detected and maintenance mode is enabled
- **Automatic cache flushing** when settings are saved, ensuring the maintenance page is served immediately
- **No-cache headers** sent on all bypass responses (logged-in users, bypass link, search engine bots) to prevent reverse proxies from caching a normal page and serving it to all visitors

**Note:** Server-level caches (Nginx FastCGI cache, Varnish, Cloudflare, etc.) operate outside of WordPress and cannot be detected or flushed by the plugin. The no-cache headers should prevent most reverse proxies from caching bypass responses, but if maintenance mode stops working, you may need to flush your server-level cache manually.

## Development

### Prerequisites

- Docker Desktop (for wp-env)
- Node.js
- Composer

### Setup

```bash
npm install
composer install
```

### Local Environment

This plugin uses [@wordpress/env](https://github.com/WordPress/gutenberg/tree/HEAD/packages/env#readme) for local development.

```bash
# Start WordPress
npx wp-env start

# Stop WordPress
npx wp-env stop
```

Local site: http://localhost:8888
Username: `admin`
Password: `password`

### Linting

```bash
# Check for coding standards issues
composer lint

# Auto-fix coding standards issues
composer format
```
