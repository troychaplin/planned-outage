<?php
/**
 * Plugin Name:       Planned Outage for Block Themes
 * Description:       Simple maintenance mode for block themes. Activate, create a templates/maintenance.html and style to match your brand.
 * Requires at least: 6.3
 * Requires PHP:      7.0
 * Version:           1.0.0
 * Author:            Troy Chaplin
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       planned-outage
 *
 * @package Planned_Outage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class for Planned Outage for Block Themes.
 */
class Planned_Outage {

	/**
	 * List of search engine bot user agent strings to detect.
	 *
	 * @var array
	 */
	private $search_engine_bots = array(
		'googlebot',
		'bingbot',
		'slurp',
		'duckduckbot',
		'baiduspider',
		'yandexbot',
		'facebot',
		'applebot',
	);

	/**
	 * Constructor. Registers hooks for admin menu, settings, template, and admin bar.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'template_include', array( $this, 'maybe_show_maintenance' ), 99 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_notice' ), 100 );
		add_action( 'admin_notices', array( $this, 'duration_warning' ) );
		register_deactivation_hook( __FILE__, array( $this, 'pobt_deactivate' ) );
	}

	/**
	 * Adds the settings page under Settings menu.
	 */
	public function add_admin_menu() {
		add_options_page(
			'Planned Outage',
			'Planned Outage',
			'manage_options',
			'pobt-maintenance',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Registers the plugin settings.
	 */
	public function register_settings() {
		// Handle reset tracking action.
		if ( isset( $_POST['pobt_reset_tracking'] ) && check_admin_referer( 'pobt_reset_tracking_action' ) ) {
			if ( get_option( 'pobt_enabled', false ) ) {
				update_option( 'pobt_enabled_at', time() );
			} else {
				delete_option( 'pobt_enabled_at' );
			}
			add_settings_error( 'pobt_settings', 'tracking_reset', 'Duration tracking has been reset.', 'success' );
		}

		// Handle regenerate bypass link action.
		if ( isset( $_POST['pobt_regenerate_bypass'] ) && check_admin_referer( 'pobt_regenerate_bypass_action' ) ) {
			update_option( 'pobt_bypass_key', wp_generate_password( 32, false ) );
			add_settings_error( 'pobt_settings', 'bypass_regenerated', 'Bypass link has been regenerated. The previous link will no longer work.', 'success' );
		}
		register_setting(
			'pobt_settings',
			'pobt_enabled',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		register_setting(
			'pobt_settings',
			'pobt_retry_after',
			array(
				'type'              => 'integer',
				'default'           => 3600,
				'sanitize_callback' => 'absint',
			)
		);
		register_setting(
			'pobt_settings',
			'pobt_allow_bots',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		register_setting(
			'pobt_settings',
			'pobt_enabled_at',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			)
		);
		register_setting(
			'pobt_settings',
			'pobt_bypass_enabled',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
	}

	/**
	 * Renders the settings page HTML.
	 */
	public function settings_page() {
		$enabled        = get_option( 'pobt_enabled', false );
		$retry_after    = absint( get_option( 'pobt_retry_after', 3600 ) );
		$allow_bots     = get_option( 'pobt_allow_bots', false );
		$bypass_enabled = get_option( 'pobt_bypass_enabled', false );
		$template       = $this->get_maintenance_template();

		// Track when maintenance was enabled (skip for pre-launch mode).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking if settings were updated, not processing form data.
		if ( isset( $_GET['settings-updated'] ) && $enabled && 0 !== $retry_after && ! get_option( 'pobt_enabled_at' ) ) {
			update_option( 'pobt_enabled_at', time() );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking if settings were updated, not processing form data.
		} elseif ( isset( $_GET['settings-updated'] ) && ( ! $enabled || 0 === $retry_after ) ) {
			delete_option( 'pobt_enabled_at' );
		}

		// Generate or remove bypass key based on setting.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking if settings were updated, not processing form data.
		if ( isset( $_GET['settings-updated'] ) && $bypass_enabled && ! get_option( 'pobt_bypass_key' ) ) {
			update_option( 'pobt_bypass_key', wp_generate_password( 32, false ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking if settings were updated, not processing form data.
		} elseif ( isset( $_GET['settings-updated'] ) && ! $bypass_enabled ) {
			delete_option( 'pobt_bypass_key' );
		}
		?>
		<div class="wrap">
			<h1>Planned Outage for Block Themes</h1>

			<?php if ( ! $template ) : ?>
				<div class="notice notice-error">
					<p>No maintenance template found. Create one by either:</p>
					<ul style="list-style: disc; margin-left: 20px;">
						<li>Creating a template named <strong>maintenance</strong> in the Site Editor (Appearance → Editor → Templates)</li>
						<li>Adding a <code>maintenance.html</code> file to your theme's <code>/templates/</code> folder</li>
					</ul>
				</div>
			<?php else : ?>
				<div class="notice notice-info">
					<p>Using maintenance template from: <strong><?php echo esc_html( 'custom' === $template->source ? 'Site Editor (database)' : 'Theme file' ); ?></strong></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'pobt_settings' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row">Enable Maintenance Mode</th>
						<td>
							<label>
								<input type="checkbox" name="pobt_enabled" value="1" <?php checked( $enabled, 1 ); ?> <?php disabled( ! $template ); ?>>
								Activate maintenance mode for logged-out visitors
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Expected Duration</th>
						<td>
							<select name="pobt_retry_after">
								<option value="0" <?php selected( $retry_after, 0 ); ?>>Pre-Launch (indefinite)</option>
								<option value="1800" <?php selected( $retry_after, 1800 ); ?>>30 minutes</option>
								<option value="3600" <?php selected( $retry_after, 3600 ); ?>>1 hour</option>
								<option value="7200" <?php selected( $retry_after, 7200 ); ?>>2 hours</option>
								<option value="14400" <?php selected( $retry_after, 14400 ); ?>>4 hours</option>
								<option value="28800" <?php selected( $retry_after, 28800 ); ?>>8 hours</option>
								<option value="43200" <?php selected( $retry_after, 43200 ); ?>>12 hours</option>
								<option value="86400" <?php selected( $retry_after, 86400 ); ?>>1 day (maximum recommended)</option>
							</select>
							<p class="description">Tells search engines when to check back. Select Pre-Launch for sites that aren't live yet. For maintenance longer than 1 day, enable search engine access below.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Search Engine Access</th>
						<td>
							<label>
								<input type="checkbox" name="pobt_allow_bots" value="1" <?php checked( $allow_bots, 1 ); ?>>
								Allow search engine bots to bypass maintenance mode
							</label>
							<p class="description">Recommended for maintenance lasting more than a few hours. Lets search engines continue crawling your site normally while visitors see the maintenance page.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Bypass Link</th>
						<td>
							<label>
								<input type="checkbox" name="pobt_bypass_enabled" value="1" <?php checked( $bypass_enabled, 1 ); ?>>
								Allow non-logged-in users to bypass maintenance mode via a secret link
							</label>
							<p class="description">When enabled, a unique URL is generated that lets anyone with the link browse the site normally during maintenance.</p>
							<?php
							$bypass_key = get_option( 'pobt_bypass_key' );
							if ( $bypass_enabled && $bypass_key ) :
								$bypass_url = add_query_arg( 'pobt_bypass', $bypass_key, home_url( '/' ) );
								?>
								<div style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1;">
									<p style="margin: 0 0 6px 0;"><strong>Bypass URL:</strong></p>
									<code style="display: block; padding: 6px 8px; background: #fff; word-break: break-all;"><?php echo esc_url( $bypass_url ); ?></code>
									<p class="description" style="margin-top: 6px;">Share this link with anyone who needs to view the site during maintenance. The link sets a cookie so they can navigate freely for 12 hours.</p>
								</div>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<?php if ( $bypass_enabled && get_option( 'pobt_bypass_key' ) ) : ?>
				<form method="post" style="margin-top: 10px;">
					<?php wp_nonce_field( 'pobt_regenerate_bypass_action' ); ?>
					<input type="hidden" name="pobt_regenerate_bypass" value="1">
					<?php submit_button( 'Regenerate Bypass Link', 'secondary', 'submit', false ); ?>
					<p class="description" style="margin-top: 6px;">Generate a new bypass link. The previous link will stop working immediately.</p>
				</form>
			<?php endif; ?>

			<?php if ( 0 !== $retry_after ) : ?>
				<?php $enabled_at = get_option( 'pobt_enabled_at', 0 ); ?>
				<?php if ( $enabled_at ) : ?>
					<hr style="margin: 30px 0;">
					<h2>Duration Tracking</h2>
					<p>Maintenance mode was enabled on: <strong><?php echo esc_html( wp_date( 'F j, Y \a\t g:i a', $enabled_at ) ); ?></strong></p>
					<p class="description">If this date is incorrect (e.g., from a previous maintenance period), you can reset it.</p>
					<form method="post" style="margin-top: 10px;">
						<?php wp_nonce_field( 'pobt_reset_tracking_action' ); ?>
						<input type="hidden" name="pobt_reset_tracking" value="1">
						<?php submit_button( 'Reset Duration Tracking', 'secondary', 'submit', false ); ?>
					</form>
				<?php endif; ?>

				<div class="card" style="max-width: 600px; margin-top: 20px; padding: 16px 20px;">
					<h3 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600;">SEO Recommendations</h3>
					<ul style="list-style: disc; margin: 0 0 0 20px; padding: 0; line-height: 1.8;">
						<li><strong>Under 2 hours:</strong> Default settings are fine.</li>
						<li><strong>2-24 hours:</strong> Consider enabling search engine access.</li>
						<li><strong>Over 1 day:</strong> Always enable search engine access. Extended 503 responses can cause pages to be removed from search indexes.</li>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Retrieves the maintenance template if it exists.
	 *
	 * @return WP_Block_Template|false The maintenance template or false if not found.
	 */
	private function get_maintenance_template() {
		$templates = get_block_templates( array( 'slug__in' => array( 'maintenance' ) ) );

		return ! empty( $templates ) ? $templates[0] : false;
	}

	/**
	 * Checks if the current request is from a search engine bot.
	 *
	 * @return bool True if the request is from a known search engine bot.
	 */
	private function is_search_engine_bot() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}

		$user_agent = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) );

		foreach ( $this->search_engine_bots as $bot ) {
			if ( strpos( $user_agent, $bot ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the current visitor has bypass access via query param or cookie.
	 *
	 * Sets a cookie on first valid access so subsequent page loads don't need the query param.
	 *
	 * @return bool True if the visitor has a valid bypass token.
	 */
	private function has_bypass_access() {
		$bypass_key = get_option( 'pobt_bypass_key' );

		if ( ! $bypass_key ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a public-facing bypass token, not a form submission.
		$query_token  = isset( $_GET['pobt_bypass'] ) ? sanitize_text_field( wp_unslash( $_GET['pobt_bypass'] ) ) : '';
		$cookie_token = isset( $_COOKIE['pobt_bypass'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['pobt_bypass'] ) ) : '';

		if ( hash_equals( $bypass_key, $query_token ) || hash_equals( $bypass_key, $cookie_token ) ) {
			// Set cookie if not already present or if arriving via query param.
			if ( $query_token && ( ! $cookie_token || ! hash_equals( $bypass_key, $cookie_token ) ) ) {
				setcookie(
					'pobt_bypass',
					$bypass_key,
					array(
						'expires'  => time() + ( 12 * HOUR_IN_SECONDS ),
						'path'     => '/',
						'secure'   => is_ssl(),
						'httponly' => true,
						'samesite' => 'Lax',
					)
				);
			}

			return true;
		}

		return false;
	}

	/**
	 * Checks if the current request is for the homepage.
	 *
	 * @return bool True if the current request is for the homepage.
	 */
	private function is_homepage() {
		$request_path = isset( $_SERVER['REQUEST_URI'] ) ? rtrim( strtok( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '?' ), '/' ) : '';
		$home_url     = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path    = rtrim( $home_url ? $home_url : '', '/' );

		return $request_path === $home_path;
	}

	/**
	 * Conditionally shows the maintenance template to logged-out users.
	 *
	 * @param string $template The path to the template file.
	 * @return string The template path to use.
	 */
	public function maybe_show_maintenance( $template ) {
		if ( ! get_option( 'pobt_enabled', false ) ) {
			return $template;
		}

		$maintenance_template = $this->get_maintenance_template();

		if ( ! $maintenance_template ) {
			return $template;
		}

		if ( is_user_logged_in() || is_login() ) {
			return $template;
		}

		// Allow bypass via secret link if enabled.
		if ( get_option( 'pobt_bypass_enabled', false ) && $this->has_bypass_access() ) {
			return $template;
		}

		// Allow search engine bots through if enabled.
		if ( get_option( 'pobt_allow_bots', false ) && $this->is_search_engine_bot() ) {
			return $template;
		}

		// Redirect to homepage if not already there.
		if ( ! $this->is_homepage() ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$retry_after = absint( get_option( 'pobt_retry_after', 3600 ) );

		nocache_headers();
		status_header( 503 );

		if ( $retry_after > 0 ) {
			header( 'Retry-After: ' . $retry_after );
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WordPress core global.
		global $_wp_current_template_content;
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WordPress core global.
		$_wp_current_template_content = $maintenance_template->content;

		return wp_normalize_path( ABSPATH . 'wp-includes/template-canvas.php' );
	}

	/**
	 * Adds an admin bar notice when maintenance mode is active.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The admin bar instance.
	 */
	public function admin_bar_notice( $wp_admin_bar ) {
		if ( ! get_option( 'pobt_enabled', false ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'pobt-notice',
				'title' => '🚧 Maintenance Mode Active',
				'href'  => admin_url( 'options-general.php?page=pobt-maintenance' ),
			)
		);
	}

	/**
	 * Displays an admin warning when maintenance mode has been active for too long.
	 */
	public function duration_warning() {
		if ( ! get_option( 'pobt_enabled', false ) ) {
			return;
		}

		// Skip duration warnings in pre-launch mode.
		if ( 0 === absint( get_option( 'pobt_retry_after', 3600 ) ) ) {
			return;
		}

		$enabled_at = get_option( 'pobt_enabled_at', 0 );

		if ( ! $enabled_at ) {
			return;
		}

		$days_active = floor( ( time() - $enabled_at ) / DAY_IN_SECONDS );

		if ( $days_active >= 3 ) {
			$allow_bots = get_option( 'pobt_allow_bots', false );
			?>
			<div class="notice notice-warning">
				<p>
					<strong>Maintenance Mode Warning:</strong>
					Your site has been in maintenance mode for <?php echo absint( $days_active ); ?> days.
					<?php if ( ! $allow_bots ) : ?>
						Consider <a href="<?php echo esc_url( admin_url( 'options-general.php?page=pobt-maintenance' ) ); ?>">enabling search engine access</a> to protect your SEO.
					<?php else : ?>
						Extended maintenance periods can still affect your search rankings.
					<?php endif; ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Clears duration tracking on plugin deactivation.
	 */
	public function pobt_deactivate() {
		delete_option( 'pobt_enabled' );
		delete_option( 'pobt_enabled_at' );
		delete_option( 'pobt_bypass_key' );
	}
}

new Planned_Outage();
