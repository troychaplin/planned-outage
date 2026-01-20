<img src="assets/banner-772x250.png" alt="Block Theme Maintenance Plugin Banner" style="width: 100%; height: auto;">

# Block Theme Maintenance Mode

Simple maintenance mode for block themes. Shows a maintenance template to logged-out visitors while allowing logged-in users to browse normally.

## Requirements

- WordPress 6.3+
- PHP 7.0+
- A block theme

## Installation

1. Upload the plugin files to `/wp-content/plugins/block-theme-maintenance`
2. Activate the plugin through the Plugins screen in WordPress

## Usage

1. Create a maintenance template using one of these methods:
   - In the Site Editor (Appearance > Editor > Templates), create a new template named "maintenance"
   - Add a `maintenance.html` file to your theme's `/templates/` folder
2. Go to Settings > Maintenance Mode
3. Configure your options and enable maintenance mode

### Options

- **Enable Maintenance Mode** - Activate maintenance mode for logged-out visitors
- **Expected Duration** - Set the Retry-After header value (30 minutes to 1 day) to tell search engines when to check back
- **Search Engine Access** - Allow search engine bots to bypass maintenance mode and continue crawling your site

### SEO Recommendations

- **Under 2 hours:** Default settings are fine
- **2-24 hours:** Consider enabling search engine access
- **Over 1 day:** Always enable search engine access to prevent pages from being removed from search indexes

### When Enabled

- Logged-out visitors see the maintenance template with a 503 status
- Logged-in users can browse the site normally
- Search engine bots can optionally bypass maintenance mode
- An admin bar notice indicates maintenance mode is active
- A warning appears after 3 days if maintenance mode is still enabled

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
