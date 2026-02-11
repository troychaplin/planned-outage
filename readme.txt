=== Planned Outage for Block Themes ===

Contributors: areziaal
Tags: maintenance, maintenance mode, block theme, coming soon
Requires at least: 6.3
Tested up to: 6.9
Stable tag: 1.1.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Simple maintenance mode for block themes. Shows a maintenance template to logged-out visitors.

== Description ==

Planned Outage for Block Themes is a lightweight plugin that enables maintenance mode for WordPress block themes. When enabled, logged-out visitors see your custom maintenance template while logged-in users can browse the site normally.

**Features:**

* Uses native block theme templates
* Create maintenance pages in the Site Editor or as theme files
* Logged-in users bypass maintenance mode
* Configurable expected duration (Retry-After header)
* Pre-launch mode for sites that aren't live yet
* Optional search engine bot access during maintenance
* Bypass link to let non-logged-in users preview the site during maintenance
* Admin bar indicator when maintenance mode is active
* Duration warning after 3 days of maintenance (except in pre-launch mode)
* Returns proper 503 status code for SEO
* Cache plugin detection with admin warning and automatic cache flushing

**Requirements:**

* WordPress 6.3 or higher
* A block theme (like Twenty Twenty-Five)

== Installation ==

1. Upload the plugin folder to your /wp-content/plugins/ folder.
2. Go to the **Plugins** page and activate the plugin.
3. Create a maintenance template (see FAQ below).
4. Go to **Settings > Planned Outage** and enable it.

== Frequently Asked Questions ==

= How do I create a maintenance template? =

You have two options:

1. **Site Editor:** Go to Appearance > Editor > Templates, create a new template named "maintenance"
2. **Theme file:** Add a `maintenance.html` file to your theme's `/templates/` folder

= Who can see the site when maintenance mode is enabled? =

All logged-in users can browse the site normally. Only logged-out visitors see the maintenance template. You can also enable search engine bots to bypass maintenance mode, or generate a bypass link for non-logged-in users.

= What is the Expected Duration setting? =

This sets the Retry-After HTTP header, which tells search engines how long to wait before checking your site again. Options range from 30 minutes to 1 day. You can also select "Pre-Launch (indefinite)" for sites that aren't live yet, which disables duration tracking and admin warnings.

= What is the Bypass Link? =

When enabled, the plugin generates a secret URL you can share with anyone who needs to view the site during maintenance without logging in. A 12-hour cookie is set on first visit so they can navigate freely. You can regenerate the link at any time to invalidate the previous one.

= Should I enable Search Engine Access? =

For short maintenance periods (under 2 hours), the default settings are fine. For longer maintenance (over 2 hours), consider enabling search engine access. For maintenance lasting more than a day, always enable it to prevent pages from being removed from search indexes.

= What status code is returned? =

The plugin returns a 503 (Service Unavailable) status with a Retry-After header, which tells search engines the site is temporarily unavailable.

= Will this work with caching plugins? =

The plugin detects popular full-page cache plugins (Surge, WP Super Cache, W3 Total Cache, WP Fastest Cache, LiteSpeed Cache, WP Rocket) and displays a warning on the settings page when one is active. Caches are automatically flushed when settings are saved to ensure the maintenance page is served immediately.

Server-level caches (Nginx FastCGI cache, Varnish, Cloudflare, etc.) cannot be detected or flushed by the plugin. If maintenance mode is not working and you use server-level caching, flush that cache manually.

= How to uninstall the plugin? =

Simply deactivate and delete the plugin. The plugin stores options prefixed with `pobt_` which are removed when you deactivate the plugin.

== Changelog ==

= Unreleased =
* Added cache plugin detection with admin warning when maintenance mode is active
* Added automatic cache flushing when plugin settings are saved
* Added support for detecting Surge, WP Super Cache, W3 Total Cache, WP Fastest Cache, LiteSpeed Cache, and WP Rocket
* Added fallback cache detection via advanced-cache.php dropin and wp-content/cache/ directory
* Added no-cache headers on all bypass responses to prevent reverse proxy cache poisoning
* Fixed bypass link, logged-in user, and bot responses poisoning server-level caches

= 1.1.0 =
* Added bypass link feature for sharing preview access with non-logged-in users
* Added pre-launch mode (indefinite duration) that disables time tracking and admin warnings
* Bypass link sets a 12-hour cookie for seamless navigation
* Regenerate bypass link to invalidate previous links

= 1.0.0 =
* Initial release
