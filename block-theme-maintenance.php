<?php
/**
 * Plugin Name:       Block Theme Maintenance Mode
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
 * Main plugin class for Block Theme Maintenance Mode.
 */
class Block_Theme_Maintenance_Mode {

	/**
	 * Constructor. Registers hooks for admin menu, settings, template, and admin bar.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'template_include', array( $this, 'maybe_show_maintenance' ), 99 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_notice' ), 100 );
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
		register_setting(
			'block_theme_maintenance',
			'btmm_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
	}

	/**
	 * Renders the settings page HTML.
	 */
	public function settings_page() {
		$enabled  = get_option( 'btmm_enabled', false );
		$template = $this->get_maintenance_template();
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
					<p>Using maintenance template from: <strong><?php echo esc_html( $template->source === 'custom' ? 'Site Editor (database)' : 'Theme file' ); ?></strong></p>
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
				</table>
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

		if ( ! is_front_page() ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		nocache_headers();
		status_header( 503 );
		header( 'Retry-After: 3600' );

		// Set the template content for the block template canvas.
		global $_wp_current_template_content;
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WordPress core global.
		$_wp_current_template_content = $maintenance_template->content;

		// Return the template canvas which renders block content.
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
				'title' => '🚧 Maintenance Mode',
				'href'  => admin_url( 'options-general.php?page=block-theme-maintenance' ),
			)
		);
	}
}

new Block_Theme_Maintenance_Mode();