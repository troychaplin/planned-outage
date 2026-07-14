<?php
/**
 * Plugin Name:       Planned Outage for Block Themes
 * Description:       Simple maintenance mode for block themes. Activate, create a templates/maintenance.html and style to match your brand.
 * Requires at least: 6.6
 * Requires PHP:      7.3
 * Version:           1.4.0
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

require_once __DIR__ . '/includes/class-pobt-schedule.php';
require_once __DIR__ . '/includes/class-planned-outage.php';

$pobt_planned_outage = new Planned_Outage();

register_deactivation_hook( __FILE__, array( $pobt_planned_outage, 'pobt_deactivate' ) );
register_uninstall_hook( __FILE__, array( 'Planned_Outage', 'pobt_uninstall' ) );
