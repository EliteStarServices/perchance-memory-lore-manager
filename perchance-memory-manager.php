<?php
/**
 * Plugin Name: Perchance
 * Plugin URI: https://example.com/
 * Description: Upload, clean, reorganize, and export Perchance AI chat memory files as Markdown or text.
 * Version: 0.1.0
 * Author: Your Name
 * Author URI: https://example.com/
 * License: GPL2+
 * Text Domain: perchance-memory-manager
 */

if (!defined('ABSPATH')) {
	exit;
}

define('PMM_VERSION', '0.1.0');
define('PMM_PLUGIN_FILE', __FILE__);
define('PMM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PMM_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once PMM_PLUGIN_DIR . 'includes/class-pmm-utils.php';
require_once PMM_PLUGIN_DIR . 'includes/class-pmm-parser.php';
require_once PMM_PLUGIN_DIR . 'includes/class-pmm-dedupe.php';
require_once PMM_PLUGIN_DIR . 'includes/class-pmm-renderer.php';
require_once PMM_PLUGIN_DIR . 'includes/class-pmm-admin.php';
require_once PMM_PLUGIN_DIR . 'includes/class-pmm-plugin.php';

function pmm_boot_plugin() {
	$plugin = new PMM_Plugin();
	$plugin->init();
}
pmm_boot_plugin();