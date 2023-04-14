<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/twomice/fpptarolesync
 * @since             1.0.0
 * @package           Fpptarolesync
 *
 * @wordpress-plugin
 * Plugin Name:       CiviCRM Membership Role Sync for FPPTA
 * Plugin URI:        https://github.com/joineryhq/fpptarolesync
 * Description:       Provides synchronization of a single WordPress role based on CiviCRM data related to Memberships, Relationships, and Contributions, per the unique needs of FPPTA.
 * Version:           1.0.2
 * Author:            Allen Shaw, Joinery
 * Author URI:        https://joineryhq.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       fpptarolesync
 */
// If this file is called directly, abort.
if (!defined('WPINC')) {
  die;
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function fpptarolesync_run() {
  // Include important class files.
  require plugin_dir_path(__FILE__) . 'includes/class-plugin.php';
  require plugin_dir_path(__FILE__) . 'includes/class-util.php';

  $plugin = new FpptarolesyncPlugin();
  $plugin->run();
}

// Invoke the plugin.
fpptarolesync_run();
