<?php
/**
 * Profitable Plugins Voicemail Drops
 *
 *
 * @package   Profitable Plugins Voicemail Drops
 * @author    Profitable Plugins
 * @license   GPL-3.0
 * @link      https://profitableplugins.com
 * @copyright 2020 Chatbot Media LLC
 *
 * @wordpress-plugin
 * Plugin Name:       Profitable Plugins Voicemail Drops
 * Plugin URI:        https://profitableplugins.com
 * Description:       Create voicemail drops via your own twilio account
 * Version:           1.0.13
 * Author:            Profitable Plugins
 * Author URI:        https://profitableplugins.com
 * Text Domain:       profitable-plugins-vmd
 * Domain Path:       /languages
 */


namespace ProfitablePlugins\VMD;
require_once( 'vendor/autoload.php' );

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'PROFITABLE_PLUGINS_VMD_VERSION', '1.0.13' );


/**
 * Autoloader
 *
 * @param string $class The fully-qualified class name.
 * @return void
 *
 *  * @since 1.0.0
 */
spl_autoload_register(function ($class) {

    // project-specific namespace prefix
    $prefix = __NAMESPACE__;

    // base directory for the namespace prefix
    $base_dir = __DIR__ . '/includes/';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Initialize Plugin
 *
 * @since 1.0.0
 */
function init() {
	$wpr = Plugin::get_instance();
	$wpr_shortcode = Shortcode::get_instance();
	$wpr_admin = Admin::get_instance();
    $vmd_rest = Endpoint\Twilio::get_instance();
    $vmd_run = Endpoint\RunCampaigns::get_instance();

    $updater = Updater::get_instance();
    $updater->set_file(__FILE__);
    $updater->initialize();
}
add_action( 'plugins_loaded', 'ProfitablePlugins\\VMD\\init' );



/**
 * Register the widget
 *
 * @since 1.0.0
 */
function widget_init() {
	return register_widget( new Widget );
}
add_action( 'widgets_init', 'ProfitablePlugins\\VMD\\widget_init' );

// function run_campaigns() {
//     die("testing!");
// }
// add_action( 'wp_ajax_ppvmd_run', 'ProfitablePlugins\\VMD\\run_campaigns');

/**
 * Register activation and deactivation hooks
 */
register_activation_hook( __FILE__, array( 'ProfitablePlugins\\VMD\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ProfitablePlugins\\VMD\\Plugin', 'deactivate' ) );

