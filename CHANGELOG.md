# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

**Types of changes**

- Added for new features.
- Changed for changes in existing functionality.
- Deprecated for soon-to-be removed features.
- Removed for now removed features.
- Fixed for any bug fixes.
- Security in case of vulnerabilities.

## [Unreleased]

## [1.4.0]

### Added

- Scheduled outage windows: set a start and end date/time (entered in the site timezone, stored as UTC) and maintenance mode activates and deactivates automatically
- Window boundaries are evaluated lazily on every request, so activation is exact and never depends on WP-Cron firing on time
- Dynamic Retry-After header during scheduled windows, computed from the time remaining until the window ends
- Schedule status display on the settings page and in the admin bar (upcoming, active until, past window)
- Best-effort cache flushing at scheduled window boundaries via single WP-Cron events
- Validation for schedule input: incomplete pairs, end before start, and windows entirely in the past are rejected with a settings error and never activate

### Changed

- Plugin restructured from a single file into a bootstrap (`planned-outage.php`) plus `includes/class-planned-outage.php` and `includes/class-pobt-schedule.php`
- All admin-facing strings are now translatable with the `planned-outage` text domain

### Fixed

- `readme.txt` requirements now match the plugin header (WordPress 6.6, PHP 7.3); readme changelog backfilled with the missing 1.2.x and 1.3.0 entries

## [1.3.0]

### Added

- Uninstall hook that removes all plugin options from the database when the plugin is deleted

### Changed

- Minimum PHP version bumped from 7.0 to 7.3, matching the actual requirement of the array-syntax `setcookie()` call used by the bypass link feature
- Minimum WordPress version bumped from 6.3 to 6.6, aligning the plugin header with the existing phpcs.xml target
- Homepage detection now uses WordPress conditionals `is_front_page()` and `is_home()` instead of raw URL string parsing, improving reliability on subdirectory installs and multisite
- Template canvas path now uses the `WPINC` constant instead of the hardcoded string `wp-includes`, matching WordPress core convention

## [1.2.1]

### Fixed

- Maintenance template now renders correctly when a static front page is set in Settings > Reading, by properly overriding the block template ID and resetting query flags to prevent the homepage post loop from interfering

## [1.2.0]

### Added

- Cache plugin detection with admin warning when maintenance mode is enabled and a full-page cache plugin is active
- Automatic cache flushing when plugin settings are saved
- Support for detecting Surge, WP Super Cache, W3 Total Cache, WP Fastest Cache, LiteSpeed Cache, and WP Rocket
- Fallback cache detection via advanced-cache.php dropin and wp-content/cache/ directory
- No-cache headers on all bypass responses (logged-in users, bypass link, search engine bots) to prevent reverse proxies from caching the normal page and serving it to all visitors

### Fixed

- Bypass link, logged-in user, and bot responses no longer poison server-level caches (Nginx, Varnish) by sending no-cache headers on all responses served while maintenance mode is active
- Maintenance template now renders correctly when a static front page is set in Settings > Reading, by properly overriding the block template ID and resetting query flags to prevent the homepage post loop from interfering

## [1.1.0]

### Added

- Bypass link feature for sharing preview access with non-logged-in users
- Pre-launch mode (indefinite duration) that disables time tracking and admin warnings
- Bypass link sets a 12-hour cookie for seamless navigation
- Regenerate bypass link to invalidate previous links

## [1.0.0]

### Added

- Initial release
- Maintenance mode for block themes using native block templates
- Configurable expected duration with Retry-After header
- Optional search engine bot access during maintenance
- Admin bar indicator when maintenance mode is active
- Duration warning after 3 days of maintenance
- 503 status code for proper SEO handling
