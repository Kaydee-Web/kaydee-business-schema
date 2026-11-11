<?php
/**
 * Plugin Name:       Kaydee Business Schema
 * Description:       One place for business details. Outputs LocalBusiness structured data (extending Yoast, Rank Math or AIOSEO), provides shortcodes for the visible site, and exposes a WordPress Ability so details can be set programmatically.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Kaydee
 * License:           GPL-2.0-or-later
 * Text Domain:       kaydee-business-schema
 */

defined( 'ABSPATH' ) || exit;

define( 'KBS_VERSION', '0.1.0' );
define( 'KBS_FILE', __FILE__ );
define( 'KBS_DIR', plugin_dir_path( __FILE__ ) );
define( 'KBS_OPTION', 'kbs_details' );

require_once KBS_DIR . 'includes/class-kbs-types.php';
require_once KBS_DIR . 'includes/class-kbs-settings.php';
require_once KBS_DIR . 'includes/class-kbs-schema.php';
require_once KBS_DIR . 'includes/class-kbs-shortcodes.php';
require_once KBS_DIR . 'includes/class-kbs-admin.php';
require_once KBS_DIR . 'includes/class-kbs-ability.php';

add_action( 'plugins_loaded', function () {
	Kbs_Schema::init();
	Kbs_Shortcodes::init();
	Kbs_Admin::init();
	Kbs_Ability::init();
} );

/**
 * Optional GitHub updates. Bundle YahnisElsts/plugin-update-checker in
 * vendor/plugin-update-checker and define KBS_UPDATE_REPO (e.g. in wp-config.php
 * or via the 'kbs_update_repo' filter) to enable. See README.
 */
add_action( 'plugins_loaded', function () {
	$repo = apply_filters( 'kbs_update_repo', defined( 'KBS_UPDATE_REPO' ) ? KBS_UPDATE_REPO : '' );
	$lib  = KBS_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
	if ( ! $repo || ! file_exists( $lib ) ) {
		return;
	}
	require_once $lib;
	if ( class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( $repo, KBS_FILE, 'kaydee-business-schema' );
		$api     = $checker->getVcsApi();
		if ( $api && method_exists( $api, 'enableReleaseAssets' ) ) {
			$api->enableReleaseAssets();
		}
	}
}, 20 );
