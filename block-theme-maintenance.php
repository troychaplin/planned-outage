<?php
/**
 * Plugin Name:       Block Theme Maintenance
 * Description:       Simple maintenance mode for block themes. Create a templates/maintenance.html in your theme.
 * Requires at least: 6.3
 * Requires PHP:      7.0
 * Version:           1.0.0
 * Author:            Troy Chaplin
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       block-theme-maintenance
 *
 * @package Block_Theme_Maintenance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class for Block Theme Maintenance.
 */
class Block_Theme_Maintenance_Mode {

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
	}

	/**
	 * Adds the settings page under Settings menu.
	 */
	public function add_admin_menu() {
		add_options_page(
			'Maintenance Mode',
			'Maintenance Mode',
			'manage_options',
			'block-theme-maintenance',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Registers the plugin settings.
	 */
	public function register_settings() {
		register_setting( 'block_theme_maintenance', 'btmm_enabled' );
		register_setting(
			'block_theme_maintenance',
			'btmm_retry_after',
			array(
				'type'              => 'integer',
				'default'           => 3600,
				'sanitize_callback' => 'absint',
			)
		);
		register_setting( 'block_theme_maintenance', 'btmm_allow_bots' );
		register_setting(
			'block_theme_maintenance',
			'btmm_enabled_at',
			array(
				'type' => 'integer',
			)
		);
	}

	/**
	 * Renders the settings page HTML.
	 */
	public function settings_page() {
		$enabled     = get_option( 'btmm_enabled', false );
		$retry_after = get_option( 'btmm_retry_after', 3600 );
		$allow_bots  = get_option( 'btmm_allow_bots', false );
		$template    = $this->get_maintenance_template();

		// Track when maintenance was enabled.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking if settings were updated, not processing form data.
		if ( isset( $_GET['settings-updated'] ) && $enabled && ! get_option( 'btmm_enabled_at' ) ) {
			update_option( 'btmm_enabled_at', time() );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking if settings were updated, not processing form data.
		} elseif ( isset( $_GET['settings-updated'] ) && ! $enabled ) {
			delete_option( 'btmm_enabled_at' );
		}
		?>
		<div class="wrap">
			<h1>Maintenance Mode</h1>

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
				<?php settings_fields( 'block_theme_maintenance' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row">Enable Maintenance Mode</th>
						<td>
							<label>
								<input type="checkbox" name="btmm_enabled" value="1" <?php checked( $enabled, 1 ); ?> <?php disabled( ! $template ); ?>>
								Activate maintenance mode for logged-out visitors
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Expected Duration</th>
						<td>
							<select name="btmm_retry_after">
								<option value="1800" <?php selected( $retry_after, 1800 ); ?>>30 minutes</option>
								<option value="3600" <?php selected( $retry_after, 3600 ); ?>>1 hour</option>
								<option value="7200" <?php selected( $retry_after, 7200 ); ?>>2 hours</option>
								<option value="14400" <?php selected( $retry_after, 14400 ); ?>>4 hours</option>
								<option value="28800" <?php selected( $retry_after, 28800 ); ?>>8 hours</option>
								<option value="43200" <?php selected( $retry_after, 43200 ); ?>>12 hours</option>
								<option value="86400" <?php selected( $retry_after, 86400 ); ?>>1 day (maximum recommended)</option>
							</select>
							<p class="description">Tells search engines when to check back. For maintenance longer than 1 day, enable search engine access below.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Search Engine Access</th>
						<td>
							<label>
								<input type="checkbox" name="btmm_allow_bots" value="1" <?php checked( $allow_bots, 1 ); ?>>
								Allow search engine bots to bypass maintenance mode
							</label>
							<p class="description">Recommended for maintenance lasting more than a few hours. Lets search engines continue crawling your site normally while visitors see the maintenance page.</p>
						</td>
					</tr>
				</table>

				<div class="card" style="max-width: 600px; margin-top: 20px; padding: 16px 20px;">
					<h3 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600;">SEO Recommendations</h3>
					<ul style="list-style: disc; margin: 0 0 0 20px; padding: 0; line-height: 1.8;">
						<li><strong>Under 2 hours:</strong> Default settings are fine.</li>
						<li><strong>2-24 hours:</strong> Consider enabling search engine access.</li>
						<li><strong>Over 1 day:</strong> Always enable search engine access. Extended 503 responses can cause pages to be removed from search indexes.</li>
					</ul>
				</div>

				<?php submit_button(); ?>
			</form>
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
		if ( ! get_option( 'btmm_enabled', false ) ) {
			return $template;
		}

		$maintenance_template = $this->get_maintenance_template();

		if ( ! $maintenance_template ) {
			return $template;
		}

		if ( is_user_logged_in() || is_login() ) {
			return $template;
		}

		// Allow search engine bots through if enabled.
		if ( get_option( 'btmm_allow_bots', false ) && $this->is_search_engine_bot() ) {
			return $template;
		}

		// Redirect to homepage if not already there.
		if ( ! $this->is_homepage() ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		nocache_headers();
		status_header( 503 );
		header( 'Retry-After: ' . absint( get_option( 'btmm_retry_after', 3600 ) ) );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WordPress core global.
		global $_wp_current_template_content;
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WordPress core global.
		$_wp_current_template_content = $maintenance_template->content;

		return ABSPATH . WPINC . '/template-canvas.php';
	}

	/**
	 * Adds an admin bar notice when maintenance mode is active.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The admin bar instance.
	 */
	public function admin_bar_notice( $wp_admin_bar ) {
		if ( ! get_option( 'btmm_enabled', false ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'btmm-notice',
				'title' => '🚧 Maintenance Mode Active',
				'href'  => admin_url( 'options-general.php?page=block-theme-maintenance' ),
			)
		);
	}

	/**
	 * Displays an admin warning when maintenance mode has been active for too long.
	 */
	public function duration_warning() {
		if ( ! get_option( 'btmm_enabled', false ) ) {
			return;
		}

		$enabled_at = get_option( 'btmm_enabled_at', 0 );

		if ( ! $enabled_at ) {
			return;
		}

		$days_active = floor( ( time() - $enabled_at ) / DAY_IN_SECONDS );

		if ( $days_active >= 3 ) {
			$allow_bots = get_option( 'btmm_allow_bots', false );
			?>
			<div class="notice notice-warning">
				<p>
					<strong>Maintenance Mode Warning:</strong>
					Your site has been in maintenance mode for <?php echo absint( $days_active ); ?> days.
					<?php if ( ! $allow_bots ) : ?>
						Consider <a href="<?php echo esc_url( admin_url( 'options-general.php?page=block-theme-maintenance' ) ); ?>">enabling search engine access</a> to protect your SEO.
					<?php else : ?>
						Extended maintenance periods can still affect your search rankings.
					<?php endif; ?>
				</p>
			</div>
			<?php
		}
	}
}

new Block_Theme_Maintenance_Mode();
