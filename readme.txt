=== Block Theme Maintenance Mode ===

Contributors: areziaal
Tags: maintenance, maintenance mode, block theme, coming soon
Requires at least: 6.3
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Simple maintenance mode for block themes. Shows a maintenance template to logged-out visitors.

== Description ==

Block Theme Maintenance Mode is a lightweight plugin that enables maintenance mode for WordPress block themes. When enabled, logged-out visitors see your custom maintenance template while logged-in users can browse the site normally.

**Features:**

* Uses native block theme templates
* Create maintenance pages in the Site Editor or as theme files
* Logged-in users bypass maintenance mode
* Admin bar indicator when maintenance mode is active
* Returns proper 503 status code for SEO

**Requirements:**

* WordPress 6.3 or higher
* A block theme (like Twenty Twenty-Five)

== Installation ==

1. Upload the plugin folder to your /wp-content/plugins/ folder.
2. Go to the **Plugins** page and activate the plugin.
3. Create a maintenance template (see FAQ below).
4. Go to **Settings > Maintenance Mode** and enable it.

== Frequently Asked Questions ==

= How do I create a maintenance template? =

You have two options:

1. **Site Editor:** Go to Appearance > Editor > Templates, create a new template named "maintenance"
2. **Theme file:** Add a `maintenance.html` file to your theme's `/templates/` folder

= Who can see the site when maintenance mode is enabled? =

All logged-in users can browse the site normally. Only logged-out visitors see the maintenance template.

= What status code is returned? =

The plugin returns a 503 (Service Unavailable) status with a Retry-After header, which tells search engines the site is temporarily unavailable.

= How to uninstall the plugin? =

Simply deactivate and delete the plugin. The plugin stores a single option (`btmm_enabled`) which is removed when you delete the plugin.

== Changelog ==

= 1.0.0 =
* Initial release
