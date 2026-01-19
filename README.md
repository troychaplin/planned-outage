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
3. Enable maintenance mode

When enabled:
- Logged-out visitors see the maintenance template with a 503 status
- Logged-in users can browse the site normally
- An admin bar notice indicates maintenance mode is active

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
