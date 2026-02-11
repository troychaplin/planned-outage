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

### Added

- Cache plugin detection with admin warning when maintenance mode is enabled and a full-page cache plugin is active
- Automatic cache flushing when plugin settings are saved
- Support for detecting Surge, WP Super Cache, W3 Total Cache, WP Fastest Cache, LiteSpeed Cache, and WP Rocket
- Fallback cache detection via advanced-cache.php dropin and wp-content/cache/ directory
- No-cache headers on all bypass responses (logged-in users, bypass link, search engine bots) to prevent reverse proxies from caching the normal page and serving it to all visitors

### Fixed

- Bypass link, logged-in user, and bot responses no longer poison server-level caches (Nginx, Varnish) by sending no-cache headers on all responses served while maintenance mode is active

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
