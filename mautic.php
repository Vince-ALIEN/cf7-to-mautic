<?php
defined('ABSPATH') || exit();

/**
 * Plugin Name:       CF7 to Mautic
 * Description:       The plugin sends CF7 submissions to Mautic.
 * Version:           2.0
 * Author:            Ufo Agency
 * Author URI:        https://www.ufo-agency.com/
 * Original Author:   Ulrich Eckardt
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cf72mautic
 */

define('MAUTIC_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('MAUTIC_PLUGIN_VERSION', '2.0');
define('MAUTIC_PLUGIN_DIR', plugin_dir_path(__FILE__));

$includes = array(
    'class-logger.php',
    'class-settings.php',
    'class-http-client.php',
    'class-oauth-authenticator.php',
    'class-mautic-api.php',
    'class-admin.php',
    'class-submission-handler.php',
    'class-cron-handler.php',
    'class-uninstall.php',
);

foreach ($includes as $file) {
    require_once MAUTIC_PLUGIN_DIR . 'includes/' . $file;
}

CF7Mautic_Admin::init();

add_action('wpcf7_mail_sent', array('CF7Mautic_SubmissionHandler', 'handle'));
add_action('mautic_process_submission', array('CF7Mautic_CronHandler', 'process'));

register_uninstall_hook(__FILE__, array('CF7Mautic_Uninstall', 'cleanup'));
