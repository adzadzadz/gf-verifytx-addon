<?php
/**
 * Plugin Name: GF VerifyTX Addon
 * Plugin URI: https://adzbyte.com/gf-verifytx-addon
 * Description: Integrates VerifyTX insurance verification service with Gravity Forms for automatic insurance eligibility verification.
 * Version: 0.1.0
 * Author: Adrian T. Saycon <adzbite@gmail.com>
 * Author URI: https://adzbyte.com
 * Text Domain: gf-verifytx
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package GF_VerifyTX
 */

defined( 'ABSPATH' ) || exit;

define( 'GF_VERIFYTX_VERSION', '1.0.0' );
define( 'GF_VERIFYTX_MIN_GF_VERSION', '2.5' );
define( 'GF_VERIFYTX_PLUGIN_FILE', __FILE__ );
define( 'GF_VERIFYTX_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'GF_VERIFYTX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GF_VERIFYTX_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the addon framework.
 */
add_action( 'gform_loaded', array( 'GF_VerifyTX_Bootstrap', 'load' ), 5 );

class GF_VerifyTX_Bootstrap {

	/**
	 * Load the required files.
	 *
	 * @access public
	 * @static
	 */
	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
			return;
		}

		require_once( GF_VERIFYTX_PLUGIN_PATH . 'class-gf-verifytx.php' );

		GFAddOn::register( 'GF_VerifyTX' );
	}
}

/**
 * Returns the main instance of GF_VerifyTX.
 *
 * @return GF_VerifyTX|false The main instance or false if Gravity Forms is not loaded.
 */
function gf_verifytx() {
	if ( class_exists( 'GF_VerifyTX' ) ) {
		return GF_VerifyTX::get_instance();
	}
	return false;
}

/**
 * Plugin activation hook.
 */
register_activation_hook( __FILE__, 'gf_verifytx_activation' );
function gf_verifytx_activation() {

	if ( ! class_exists( 'GFForms' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'Please install and activate Gravity Forms before activating this plugin.', 'gf-verifytx' ),
			esc_html__( 'Plugin Activation Error', 'gf-verifytx' ),
			array( 'back_link' => true )
		);
	}

	$min_gf_version = defined( 'GF_VERIFYTX_MIN_GF_VERSION' ) ? GF_VERIFYTX_MIN_GF_VERSION : '2.5';
	if ( ! version_compare( GFForms::$version, $min_gf_version, '>=' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			sprintf(
				esc_html__( 'GF VerifyTX Addon requires Gravity Forms %s or later. Please update Gravity Forms.', 'gf-verifytx' ),
				$min_gf_version
			),
			esc_html__( 'Plugin Activation Error', 'gf-verifytx' ),
			array( 'back_link' => true )
		);
	}

	gf_verifytx_create_database_tables();

	flush_rewrite_rules();
}

/**
 * Plugin deactivation hook.
 */
register_deactivation_hook( __FILE__, 'gf_verifytx_deactivation' );
function gf_verifytx_deactivation() {
	flush_rewrite_rules();

	if ( class_exists( 'GF_VerifyTX' ) ) {
		GF_VerifyTX::get_instance()->maybe_clear_cache();
	}
}

/**
 * Create custom database tables.
 */
function gf_verifytx_create_database_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	$verifications_table = $wpdb->prefix . 'gf_verifytx_verifications';
	$cache_table = $wpdb->prefix . 'gf_verifytx_cache';

	$sql = "CREATE TABLE IF NOT EXISTS $verifications_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		entry_id bigint(20) unsigned NOT NULL,
		form_id bigint(20) unsigned NOT NULL,
		verification_date datetime DEFAULT NULL,
		request_data longtext DEFAULT NULL,
		response_data longtext DEFAULT NULL,
		status varchar(50) DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY entry_id (entry_id),
		KEY form_id (form_id),
		KEY status (status),
		KEY verification_date (verification_date)
	) $charset_collate;";

	$sql .= "CREATE TABLE IF NOT EXISTS $cache_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		cache_key varchar(255) NOT NULL,
		cache_data longtext DEFAULT NULL,
		expiration datetime DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY cache_key (cache_key),
		KEY expiration (expiration)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

/**
 * Load plugin textdomain.
 */
add_action( 'init', 'gf_verifytx_load_textdomain' );
function gf_verifytx_load_textdomain() {
	load_plugin_textdomain( 'gf-verifytx', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}