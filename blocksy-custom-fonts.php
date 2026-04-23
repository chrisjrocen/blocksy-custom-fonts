<?php
/**
 * Plugin Name: Blocksy Custom Fonts
 * Description: Upload and register custom font families that appear in Blocksy's Customizer typography picker.
 * Version:     1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author:      TWR
 * License:     GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BCF_DIR', plugin_dir_path( __FILE__ ) );

require_once BCF_DIR . 'includes/fonts.php';
require_once BCF_DIR . 'includes/admin.php';
