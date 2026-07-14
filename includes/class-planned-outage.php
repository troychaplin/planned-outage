<?php
/**
 * Main plugin class for Planned Outage for Block Themes.
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
	 * Known full-page cache plugins and their flush functions.
	 *
	 * @var array
	 */
	private $cache_plugins = array(
		'surge'            => array(
			'option' => 'surge_installed',
			'label'  => 'Surge',
		),
		'wp-super-cache'   => array(
			'function' => 'wp_cache_clear_cache',
			'label'    => 'WP Super Cache',
		),
		'w3-total-cache'   => array(
			'function' => 'w3tc_flush_all',
			'label'    => 'W3 Total Cache',
		),
		'wp-fastest-cache' => array(
			'class_method' => array( 'WpFastestCache', 'deleteCache' ),
			'label'        => 'WP Fastest Cache',
		),
		'litespeed-cache'  => array(
			'class_method' => array( 'LiteSpeed_Cache_API', 'purge_all' ),
			'label'        => 'LiteSpeed Cache',
		),
		'wp-rocket'        => array(
			'function' => 'rocket_clean_domain',
			'label'    => 'WP Rocket',
		),
	);

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
	 * Scheduled outage window handler.
	 *
	 * @var Pobt_Schedule
	 */
	private $schedule;

	/**
	 * Constructor. Registers hooks for admin menu, settings, template, and admin bar.
	 */
	public function __construct() {
		$this->schedule = new Pobt_Schedule();

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'template_include', array( $this, 'maybe_show_maintenance' ), 99 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_notice' ), 100 );
		add_action( 'admin_notices', array( $this, 'duration_warning' ) );
		add_action( 'pobt_flush_caches_event', array( $this, 'flush_caches_event' ) );
	}

	/**
	 * Cron callback: flushes full-page caches at scheduled window boundaries.
	 *
	 * Best-effort only — maintenance activation itself is evaluated lazily on
	 * each request and never depends on WP-Cron firing on time.
	 */
	public function flush_caches_event() {
		$this->flush_caches();
	}

	/**
	 * Determines whether maintenance mode is currently active.
	 *
	 * Driven by pobt_mode: 'enabled' is always on, 'scheduled' activates during
	 * the configured window, 'disabled' is always off.
	 *
	 * Note: wp_is_maintenance_mode() is intentionally absent — during a real
	 * .maintenance window the request dies in wp_maintenance() at wp-settings.php:79,
	 * before any plugin code runs, so that function can never return true here.
	 *
	 * @return bool
	 */
	private function is_maintenance_active() {
		switch ( get_option( 'pobt_mode', 'disabled' ) ) {
			case 'enabled':
				return true;
			case 'scheduled':
				return $this->schedule->is_window_active();
			default:
				return false;
		}
	}

	/**
	 * Adds the settings page under Settings menu.
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Planned Outage', 'planned-outage' ),
			__( 'Planned Outage', 'planned-outage' ),
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
			if ( 'enabled' === get_option( 'pobt_mode', 'disabled' ) ) {
				update_option( 'pobt_enabled_at', time() );
			} else {
				delete_option( 'pobt_enabled_at' );
			}
			add_settings_error( 'pobt_settings', 'tracking_reset', __( 'Duration tracking has been reset.', 'planned-outage' ), 'success' );
		}

		// Handle regenerate bypass link action.
		if ( isset( $_POST['pobt_regenerate_bypass'] ) && check_admin_referer( 'pobt_regenerate_bypass_action' ) ) {
			update_option( 'pobt_bypass_key', wp_generate_password( 32, false ) );
			add_settings_error( 'pobt_settings', 'bypass_regenerated', __( 'Bypass link has been regenerated. The previous link will no longer work.', 'planned-outage' ), 'success' );
		}
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
		register_setting(
			'pobt_settings',
			'pobt_schedule_start',
			array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => array( $this, 'sanitize_schedule_start' ),
			)
		);
		register_setting(
			'pobt_settings',
			'pobt_schedule_end',
			array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => array( $this, 'sanitize_schedule_end' ),
			)
		);
		// Register pobt_mode last so schedule options are already saved when its
		// sanitize callback runs (needed for mode-specific window validation).
		register_setting(
			'pobt_settings',
			'pobt_mode',
			array(
				'type'              => 'string',
				'default'           => 'disabled',
				'sanitize_callback' => array( $this, 'sanitize_mode' ),
			)
		);
	}

	/**
	 * Sanitizes the mode selector value.
	 *
	 * @param mixed $value Posted value.
	 * @return string One of 'disabled', 'enabled', or 'scheduled'.
	 */
	public function sanitize_mode( $value ) {
		$allowed = array( 'disabled', 'enabled', 'scheduled' );

		return in_array( $value, $allowed, true ) ? $value : 'disabled';
	}

	/**
	 * Sanitizes the scheduled window start datetime.
	 *
	 * @param mixed $value Posted datetime-local value.
	 * @return int UTC timestamp, or 0 for empty/invalid.
	 */
	public function sanitize_schedule_start( $value ) {
		return $this->schedule->sanitize_datetime_local( $value );
	}

	/**
	 * Sanitizes and validates the scheduled window end datetime.
	 *
	 * Cross-field validation lives here because the end field is the last of
	 * the pair to be saved. Invalid pairs are stored as 0 so a broken window
	 * can never activate.
	 *
	 * @param mixed $value Posted datetime-local value.
	 * @return int UTC timestamp, or 0 for empty/invalid/rejected.
	 */
	public function sanitize_schedule_end( $value ) {
		$end = $this->schedule->sanitize_datetime_local( $value );

		// options.php has already verified the settings nonce for this request.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$posted_mode = isset( $_POST['pobt_mode'] ) ? sanitize_key( $_POST['pobt_mode'] ) : 'disabled';

		// Cross-field errors are only relevant when the user is saving scheduled mode.
		if ( 'scheduled' !== $posted_mode ) {
			return $end;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$start_raw = isset( $_POST['pobt_schedule_start'] ) ? sanitize_text_field( wp_unslash( $_POST['pobt_schedule_start'] ) ) : '';
		$start     = $this->schedule->sanitize_datetime_local( $start_raw );

		if ( ! $end ) {
			if ( $start ) {
				add_settings_error(
					'pobt_settings',
					'schedule_incomplete',
					__( 'Scheduled outage not saved: both a start and an end time are required.', 'planned-outage' )
				);
			}
			return 0;
		}

		// An unchanged expired window is kept for status display and reuse.
		if ( $end <= time() && $end === absint( get_option( 'pobt_schedule_end', 0 ) ) ) {
			return $end;
		}

		if ( ! $start ) {
			add_settings_error(
				'pobt_settings',
				'schedule_incomplete',
				__( 'Scheduled outage not saved: both a start and an end time are required.', 'planned-outage' )
			);
			return 0;
		}

		if ( $end <= $start ) {
			add_settings_error(
				'pobt_settings',
				'schedule_invalid_order',
				__( 'Scheduled outage not saved: the end time must be after the start time.', 'planned-outage' )
			);
			return 0;
		}

		if ( $end <= time() ) {
			add_settings_error(
				'pobt_settings',
				'schedule_in_past',
				__( 'Scheduled outage not saved: the end time is in the past.', 'planned-outage' )
			);
			return 0;
		}

		return $end;
	}

	/**
	 * Clears and re-registers the cache-flush cron events at the scheduled
	 * window boundaries.
	 *
	 * Best-effort cache purging only; window activation is evaluated lazily
	 * per request and does not depend on these events.
	 */
	private function reschedule_flush_events() {
		wp_clear_scheduled_hook( 'pobt_flush_caches_event' );

		$start = $this->schedule->get_start();
		$end   = $this->schedule->get_end();

		if ( ! $start || ! $end ) {
			return;
		}

		$now = time();

		if ( $start > $now ) {
			wp_schedule_single_event( $start, 'pobt_flush_caches_event' );
		}

		if ( $end > $now ) {
			wp_schedule_single_event( $end, 'pobt_flush_caches_event' );
		}
	}

	/**
	 * Renders the settings page HTML.
	 */
	public function settings_page() {
		// One-time migration from pobt_enabled (boolean) to pobt_mode (string).
		if ( false === get_option( 'pobt_mode' ) ) {
			$old_enabled  = get_option( 'pobt_enabled', false );
			$has_schedule = $this->schedule->get_start() && $this->schedule->get_end();
			if ( $old_enabled ) {
				update_option( 'pobt_mode', 'enabled' );
			} elseif ( $has_schedule ) {
				update_option( 'pobt_mode', 'scheduled' );
			} else {
				update_option( 'pobt_mode', 'disabled' );
			}
			delete_option( 'pobt_enabled' );
		}

		$mode            = get_option( 'pobt_mode', 'disabled' );
		$retry_after     = absint( get_option( 'pobt_retry_after', 3600 ) );
		$allow_bots      = get_option( 'pobt_allow_bots', false );
		$bypass_enabled  = get_option( 'pobt_bypass_enabled', false );
		$template        = $this->get_maintenance_template();
		$schedule_status = $this->schedule->get_status();

		// Track when manual maintenance was enabled (skip for pre-launch mode).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking if settings were updated, not processing form data.
		if ( isset( $_GET['settings-updated'] ) && 'enabled' === $mode && 0 !== $retry_after && ! get_option( 'pobt_enabled_at' ) ) {
			update_option( 'pobt_enabled_at', time() );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking if settings were updated, not processing form data.
		} elseif ( isset( $_GET['settings-updated'] ) && ( 'enabled' !== $mode || 0 === $retry_after ) ) {
			delete_option( 'pobt_enabled_at' );
		}

		// Flush full-page caches when settings are saved, and re-register the
		// best-effort cache-flush events at the scheduled window boundaries.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking if settings were updated, not processing form data.
		if ( isset( $_GET['settings-updated'] ) ) {
			$this->flush_caches();
			$this->reschedule_flush_events();
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
			<h1><?php esc_html_e( 'Planned Outage for Block Themes', 'planned-outage' ); ?></h1>

				<?php
				$detected_caches = $this->detect_cache_plugins();
				if ( $this->is_maintenance_active() && ! empty( $detected_caches ) ) :
					?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Cache Detected:', 'planned-outage' ); ?></strong>
						<?php
						printf(
							/* translators: %s: comma-separated list of detected cache plugins. */
							esc_html__( '%s is active. Full-page caching can prevent the maintenance page from displaying to visitors. If maintenance mode is not working, flush your cache or temporarily deactivate your caching plugin. Caches are automatically flushed when these settings are saved.', 'planned-outage' ),
							esc_html( implode( ', ', $detected_caches ) )
						);
						?>
					</p>
				</div>
				<?php endif; ?>

			<?php if ( ! $template ) : ?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'No maintenance template found. Create one by either:', 'planned-outage' ); ?></p>
					<ul style="list-style: disc; margin-left: 20px;">
						<li>
							<?php
							printf(
								/* translators: %s: template name. */
								esc_html__( 'Creating a template named %s in the Site Editor (Appearance → Editor → Templates)', 'planned-outage' ),
								'<strong>maintenance</strong>'
							);
							?>
						</li>
						<li>
							<?php
							printf(
								/* translators: 1: file name, 2: directory path. */
								esc_html__( 'Adding a %1$s file to your theme\'s %2$s folder', 'planned-outage' ),
								'<code>maintenance.html</code>',
								'<code>/templates/</code>'
							);
							?>
						</li>
					</ul>
					<?php if ( in_array( $schedule_status, array( Pobt_Schedule::STATUS_UPCOMING, Pobt_Schedule::STATUS_ACTIVE ), true ) ) : ?>
						<p>
							<strong><?php esc_html_e( 'A maintenance window is scheduled, but it will have no effect until a maintenance template exists.', 'planned-outage' ); ?></strong>
						</p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="notice notice-info">
					<p>
						<?php
						printf(
							/* translators: %s: template source. */
							esc_html__( 'Using maintenance template from: %s', 'planned-outage' ),
							'<strong>' . esc_html( 'custom' === $template->source ? __( 'Site Editor (database)', 'planned-outage' ) : __( 'Theme file', 'planned-outage' ) ) . '</strong>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'pobt_settings' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Maintenance Mode', 'planned-outage' ); ?></th>
						<td>
							<select id="pobt_mode" name="pobt_mode" <?php disabled( ! $template ); ?>>
								<option value="disabled" <?php selected( $mode, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'planned-outage' ); ?></option>
								<option value="enabled" <?php selected( $mode, 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'planned-outage' ); ?></option>
								<option value="scheduled" <?php selected( $mode, 'scheduled' ); ?>><?php esc_html_e( 'Scheduled', 'planned-outage' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Disabled: site is live. Enabled: maintenance is on now for all logged-out visitors. Scheduled: maintenance activates automatically during the configured window.', 'planned-outage' ); ?></p>
						</td>
					</tr>
					<tr class="pobt-schedule-row">
						<th scope="row"><?php esc_html_e( 'Outage Window', 'planned-outage' ); ?></th>
						<td>
							<div style="margin-bottom: 8px;">
								<label for="pobt_schedule_start" style="display: inline-block; min-width: 3.5em;"><?php esc_html_e( 'Start', 'planned-outage' ); ?></label>
								<input type="datetime-local" id="pobt_schedule_start" name="pobt_schedule_start" value="<?php echo esc_attr( $this->schedule->format_for_input( $this->schedule->get_start() ) ); ?>">
							</div>
							<div>
								<label for="pobt_schedule_end" style="display: inline-block; min-width: 3.5em;"><?php esc_html_e( 'End', 'planned-outage' ); ?></label>
								<input type="datetime-local" id="pobt_schedule_end" name="pobt_schedule_end" value="<?php echo esc_attr( $this->schedule->format_for_input( $this->schedule->get_end() ) ); ?>">
							</div>
							<p class="description">
								<?php
								printf(
									/* translators: %s: site timezone name. */
									esc_html__( 'Times are in the site timezone (%s). Clear both fields to remove the schedule.', 'planned-outage' ),
									esc_html( wp_timezone_string() )
								);
								?>
							</p>
							<?php if ( Pobt_Schedule::STATUS_UPCOMING === $schedule_status ) : ?>
								<p>
									<strong>
										<?php
										printf(
											/* translators: 1: start date/time, 2: end date/time. */
											esc_html__( 'Scheduled: starts %1$s, ends %2$s.', 'planned-outage' ),
											esc_html( $this->schedule->format_for_display( $this->schedule->get_start() ) ),
											esc_html( $this->schedule->format_for_display( $this->schedule->get_end() ) )
										);
										?>
									</strong>
								</p>
							<?php elseif ( Pobt_Schedule::STATUS_ACTIVE === $schedule_status ) : ?>
								<p>
									<strong>
										<?php
										printf(
											/* translators: %s: end date/time. */
											esc_html__( 'Outage is active now, until %s.', 'planned-outage' ),
											esc_html( $this->schedule->format_for_display( $this->schedule->get_end() ) )
										);
										?>
									</strong>
								</p>
							<?php elseif ( Pobt_Schedule::STATUS_PAST === $schedule_status ) : ?>
								<p>
									<?php
									printf(
										/* translators: %s: end date/time. */
										esc_html__( 'Past window ended %s — set new dates to schedule another outage.', 'planned-outage' ),
										esc_html( $this->schedule->format_for_display( $this->schedule->get_end() ) )
									);
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr class="pobt-manual-row">
						<th scope="row"><?php esc_html_e( 'Expected Duration', 'planned-outage' ); ?></th>
						<td>
							<select name="pobt_retry_after">
								<option value="0" <?php selected( $retry_after, 0 ); ?>><?php esc_html_e( 'Pre-Launch (indefinite)', 'planned-outage' ); ?></option>
								<option value="1800" <?php selected( $retry_after, 1800 ); ?>><?php esc_html_e( '30 minutes', 'planned-outage' ); ?></option>
								<option value="3600" <?php selected( $retry_after, 3600 ); ?>><?php esc_html_e( '1 hour', 'planned-outage' ); ?></option>
								<option value="7200" <?php selected( $retry_after, 7200 ); ?>><?php esc_html_e( '2 hours', 'planned-outage' ); ?></option>
								<option value="14400" <?php selected( $retry_after, 14400 ); ?>><?php esc_html_e( '4 hours', 'planned-outage' ); ?></option>
								<option value="28800" <?php selected( $retry_after, 28800 ); ?>><?php esc_html_e( '8 hours', 'planned-outage' ); ?></option>
								<option value="43200" <?php selected( $retry_after, 43200 ); ?>><?php esc_html_e( '12 hours', 'planned-outage' ); ?></option>
								<option value="86400" <?php selected( $retry_after, 86400 ); ?>><?php esc_html_e( '1 day (maximum recommended)', 'planned-outage' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Tells search engines when to check back. Applies to the manual toggle; during a scheduled window the remaining time is used automatically. Select Pre-Launch for sites that aren\'t live yet. For maintenance longer than 1 day, enable search engine access below.', 'planned-outage' ); ?></p>
						</td>
					</tr>
					<tr class="pobt-shared-row">
						<th scope="row"><?php esc_html_e( 'Search Engine Access', 'planned-outage' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="pobt_allow_bots" value="1" <?php checked( $allow_bots, 1 ); ?>>
								<?php esc_html_e( 'Allow search engine bots to bypass maintenance mode', 'planned-outage' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Recommended for maintenance lasting more than a few hours. Lets search engines continue crawling your site normally while visitors see the maintenance page.', 'planned-outage' ); ?></p>
						</td>
					</tr>
					<tr class="pobt-shared-row">
						<th scope="row"><?php esc_html_e( 'Bypass Link', 'planned-outage' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="pobt_bypass_enabled" value="1" <?php checked( $bypass_enabled, 1 ); ?>>
								<?php esc_html_e( 'Allow non-logged-in users to bypass maintenance mode via a secret link', 'planned-outage' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'When enabled, a unique URL is generated that lets anyone with the link browse the site normally during maintenance.', 'planned-outage' ); ?></p>
							<?php
							$bypass_key = get_option( 'pobt_bypass_key' );
							if ( $bypass_enabled && $bypass_key ) :
								$bypass_url = add_query_arg( 'pobt_bypass', $bypass_key, home_url( '/' ) );
								?>
								<div style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1;">
									<p style="margin: 0 0 6px 0;"><strong><?php esc_html_e( 'Bypass URL:', 'planned-outage' ); ?></strong></p>
									<code style="display: block; padding: 6px 8px; background: #fff; word-break: break-all;"><?php echo esc_url( $bypass_url ); ?></code>
									<p class="description" style="margin-top: 6px;"><?php esc_html_e( 'Share this link with anyone who needs to view the site during maintenance. The link sets a cookie so they can navigate freely for 12 hours.', 'planned-outage' ); ?></p>
								</div>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
			<script>
			(function() {
				var sel = document.getElementById( 'pobt_mode' );
				if ( ! sel ) { return; }
				function toggle() {
					var mode = sel.value;
					document.querySelectorAll( '.pobt-manual-row' ).forEach( function( r ) {
						r.style.display = ( 'enabled' === mode ) ? '' : 'none';
					} );
					document.querySelectorAll( '.pobt-schedule-row' ).forEach( function( r ) {
						r.style.display = ( 'scheduled' === mode ) ? '' : 'none';
					} );
					document.querySelectorAll( '.pobt-shared-row' ).forEach( function( r ) {
						r.style.display = ( 'disabled' !== mode ) ? '' : 'none';
					} );
				}
				sel.addEventListener( 'change', toggle );
				toggle();
			}());
			</script>

			<?php if ( $bypass_enabled && get_option( 'pobt_bypass_key' ) ) : ?>
				<form method="post" style="margin-top: 10px;">
					<?php wp_nonce_field( 'pobt_regenerate_bypass_action' ); ?>
					<input type="hidden" name="pobt_regenerate_bypass" value="1">
					<?php submit_button( __( 'Regenerate Bypass Link', 'planned-outage' ), 'secondary', 'submit', false ); ?>
					<p class="description" style="margin-top: 6px;"><?php esc_html_e( 'Generate a new bypass link. The previous link will stop working immediately.', 'planned-outage' ); ?></p>
				</form>
			<?php endif; ?>

			<?php if ( 0 !== $retry_after ) : ?>
				<?php $enabled_at = get_option( 'pobt_enabled_at', 0 ); ?>
				<?php if ( $enabled_at ) : ?>
					<hr style="margin: 30px 0;">
					<h2><?php esc_html_e( 'Duration Tracking', 'planned-outage' ); ?></h2>
					<p>
						<?php
						printf(
							/* translators: %s: date and time maintenance mode was enabled. */
							esc_html__( 'Maintenance mode was enabled on: %s', 'planned-outage' ),
							'<strong>' . esc_html( wp_date( __( 'F j, Y \a\t g:i a', 'planned-outage' ), $enabled_at ) ) . '</strong>'
						);
						?>
					</p>
					<p class="description"><?php esc_html_e( 'If this date is incorrect (e.g., from a previous maintenance period), you can reset it.', 'planned-outage' ); ?></p>
					<form method="post" style="margin-top: 10px;">
						<?php wp_nonce_field( 'pobt_reset_tracking_action' ); ?>
						<input type="hidden" name="pobt_reset_tracking" value="1">
						<?php submit_button( __( 'Reset Duration Tracking', 'planned-outage' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php endif; ?>

				<div class="card" style="max-width: 600px; margin-top: 20px; padding: 16px 20px;">
					<h3 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600;"><?php esc_html_e( 'SEO Recommendations', 'planned-outage' ); ?></h3>
					<ul style="list-style: disc; margin: 0 0 0 20px; padding: 0; line-height: 1.8;">
						<li><strong><?php esc_html_e( 'Under 2 hours:', 'planned-outage' ); ?></strong> <?php esc_html_e( 'Default settings are fine.', 'planned-outage' ); ?></li>
						<li><strong><?php esc_html_e( '2-24 hours:', 'planned-outage' ); ?></strong> <?php esc_html_e( 'Consider enabling search engine access.', 'planned-outage' ); ?></li>
						<li><strong><?php esc_html_e( 'Over 1 day:', 'planned-outage' ); ?></strong> <?php esc_html_e( 'Always enable search engine access. Extended 503 responses can cause pages to be removed from search indexes.', 'planned-outage' ); ?></li>
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
	 * Conditionally shows the maintenance template to logged-out users.
	 *
	 * @param string $template The path to the template file.
	 * @return string The template path to use.
	 */
	public function maybe_show_maintenance( $template ) {
		if ( ! $this->is_maintenance_active() ) {
			return $template;
		}

		$maintenance_template = $this->get_maintenance_template();

		if ( ! $maintenance_template ) {
			return $template;
		}

		if ( is_user_logged_in() || is_login() ) {
			nocache_headers();
			return $template;
		}

		// Allow bypass via secret link if enabled.
		if ( get_option( 'pobt_bypass_enabled', false ) && $this->has_bypass_access() ) {
			nocache_headers();
			return $template;
		}

		// Allow search engine bots through if enabled.
		if ( get_option( 'pobt_allow_bots', false ) && $this->is_search_engine_bot() ) {
			nocache_headers();
			return $template;
		}

		// Redirect to homepage if not already there.
		if ( ! is_front_page() && ! is_home() ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		// When active via scheduled mode, the window end is known, so
		// Retry-After can be computed exactly from the remaining time.
		if ( 'scheduled' === get_option( 'pobt_mode', 'disabled' ) ) {
			$retry_after = max( 60, $this->schedule->seconds_until_end() );
		} else {
			$retry_after = absint( get_option( 'pobt_retry_after', 3600 ) );
		}

		nocache_headers();
		status_header( 503 );

		if ( $retry_after > 0 ) {
			header( 'Retry-After: ' . $retry_after );
		}

		// Override the block template globals so WordPress renders the maintenance
		// template content instead of the resolved homepage template.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WordPress core global.
		global $_wp_current_template_content, $_wp_current_template_id, $wp_query;
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WordPress core global.
		$_wp_current_template_content = $maintenance_template->content;
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WordPress core global.
		$_wp_current_template_id = $maintenance_template->id;

		// Reset query flags so get_the_block_template_html() does not enter the
		// singular post loop which would set up the homepage post data.
		$wp_query->is_singular   = false;
		$wp_query->is_page       = false;
		$wp_query->is_home       = false;
		$wp_query->is_front_page = false;

		return ABSPATH . WPINC . '/template-canvas.php';
	}

	/**
	 * Adds an admin bar notice when maintenance mode is active.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The admin bar instance.
	 */
	public function admin_bar_notice( $wp_admin_bar ) {
		if ( $this->is_maintenance_active() ) {
			if ( ! get_option( 'pobt_enabled', false ) && $this->schedule->is_window_active() ) {
				$title = sprintf(
					/* translators: %s: scheduled window end date/time. */
					__( '🚧 Maintenance Mode Active (until %s)', 'planned-outage' ),
					$this->schedule->format_for_display( $this->schedule->get_end() )
				);
			} else {
				$title = __( '🚧 Maintenance Mode Active', 'planned-outage' );
			}

			$wp_admin_bar->add_node(
				array(
					'id'    => 'pobt-notice',
					'title' => $title,
					'href'  => admin_url( 'options-general.php?page=pobt-maintenance' ),
				)
			);
			return;
		}

		if ( 'scheduled' === get_option( 'pobt_mode', 'disabled' ) && Pobt_Schedule::STATUS_UPCOMING === $this->schedule->get_status() ) {
			$wp_admin_bar->add_node(
				array(
					'id'    => 'pobt-notice',
					'title' => sprintf(
						/* translators: %s: scheduled window start date/time. */
						__( '🕒 Maintenance scheduled for %s', 'planned-outage' ),
						$this->schedule->format_for_display( $this->schedule->get_start() )
					),
					'href'  => admin_url( 'options-general.php?page=pobt-maintenance' ),
				)
			);
		}
	}

	/**
	 * Displays an admin warning when maintenance mode has been active for too long.
	 */
	public function duration_warning() {
		if ( 'enabled' !== get_option( 'pobt_mode', 'disabled' ) ) {
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
					<strong><?php esc_html_e( 'Maintenance Mode Warning:', 'planned-outage' ); ?></strong>
					<?php
					printf(
						/* translators: %d: number of days maintenance mode has been active. */
						esc_html( _n( 'Your site has been in maintenance mode for %d day.', 'Your site has been in maintenance mode for %d days.', $days_active, 'planned-outage' ) ),
						absint( $days_active )
					);
					?>
					<?php if ( ! $allow_bots ) : ?>
						<?php
						printf(
							/* translators: %s: settings page URL. */
							wp_kses_post( __( 'Consider <a href="%s">enabling search engine access</a> to protect your SEO.', 'planned-outage' ) ),
							esc_url( admin_url( 'options-general.php?page=pobt-maintenance' ) )
						);
						?>
					<?php else : ?>
						<?php esc_html_e( 'Extended maintenance periods can still affect your search rankings.', 'planned-outage' ); ?>
					<?php endif; ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Detects active full-page cache plugins.
	 *
	 * @return array List of detected cache plugin labels.
	 */
	private function detect_cache_plugins() {
		$detected = array();

		foreach ( $this->cache_plugins as $plugin ) {
			if ( isset( $plugin['option'] ) && get_option( $plugin['option'] ) ) {
				$detected[] = $plugin['label'];
			} elseif ( isset( $plugin['function'] ) && function_exists( $plugin['function'] ) ) {
				$detected[] = $plugin['label'];
			} elseif ( isset( $plugin['class_method'] ) && is_callable( $plugin['class_method'] ) ) {
				$detected[] = $plugin['label'];
			}
		}

		// Fallback detection when no specific plugin was identified.
		if ( empty( $detected ) ) {
			// Check for the advanced-cache.php dropin registered with WordPress.
			$dropins = get_dropins();
			if ( isset( $dropins['advanced-cache.php'] ) ) {
				$detected[] = 'Full-page cache dropin (advanced-cache.php)';
			}

			// Check for cache files in common cache directory.
			if ( empty( $detected ) ) {
				$cache_dir = WP_CONTENT_DIR . '/cache';
				if ( is_dir( $cache_dir ) ) {
					$contents = scandir( $cache_dir );
					// Filter out dot entries and index files.
					$cache_contents = array_filter(
						$contents,
						function ( $item ) {
							return ! in_array( $item, array( '.', '..', 'index.php', 'index.html', '.htaccess' ), true );
						}
					);
					if ( ! empty( $cache_contents ) ) {
						$detected[] = 'Page cache (wp-content/cache/ is not empty)';
					}
				}
			}
		}

		return $detected;
	}

	/**
	 * Attempts to flush known full-page caches.
	 */
	private function flush_caches() {
		// WordPress cache flush.
		wp_cache_flush();

		foreach ( $this->cache_plugins as $plugin ) {
			if ( isset( $plugin['function'] ) && function_exists( $plugin['function'] ) ) {
				call_user_func( $plugin['function'] );
			} elseif ( isset( $plugin['class_method'] ) && is_callable( $plugin['class_method'] ) ) {
				call_user_func( $plugin['class_method'] );
			}
		}

		// Surge: delete cache directory contents if available.
		$surge_cache_dir = WP_CONTENT_DIR . '/cache/surge';
		if ( get_option( 'surge_installed' ) && is_dir( $surge_cache_dir ) ) {
			$this->delete_directory_contents( $surge_cache_dir );
		}
	}

	/**
	 * Recursively deletes directory contents without removing the directory itself.
	 *
	 * @param string $dir Path to the directory.
	 */
	private function delete_directory_contents( $dir ) {
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $files as $file ) {
			if ( $file->isDir() ) {
				rmdir( $file->getRealPath() );
			} else {
				unlink( $file->getRealPath() );
			}
		}
	}

	/**
	 * Clears duration tracking on plugin deactivation.
	 */
	public function pobt_deactivate() {
		delete_option( 'pobt_mode' );
		delete_option( 'pobt_enabled' ); // legacy option, removed during migration.
		delete_option( 'pobt_enabled_at' );
		delete_option( 'pobt_bypass_key' );
		delete_option( 'pobt_schedule_start' );
		delete_option( 'pobt_schedule_end' );
		wp_clear_scheduled_hook( 'pobt_flush_caches_event' );
	}

	/**
	 * Removes all plugin options on uninstall.
	 */
	public static function pobt_uninstall() {
		delete_option( 'pobt_mode' );
		delete_option( 'pobt_enabled' ); // legacy option, removed during migration.
		delete_option( 'pobt_enabled_at' );
		delete_option( 'pobt_bypass_key' );
		delete_option( 'pobt_retry_after' );
		delete_option( 'pobt_allow_bots' );
		delete_option( 'pobt_bypass_enabled' );
		delete_option( 'pobt_schedule_start' );
		delete_option( 'pobt_schedule_end' );
		wp_clear_scheduled_hook( 'pobt_flush_caches_event' );
	}
}
