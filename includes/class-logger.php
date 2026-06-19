<?php
defined('ABSPATH') || exit;

class CF7Mautic_Logger {
    public static function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log('[CF7 to Mautic] [' . strtoupper($level) . '] ' . $message);
        }
    }
}
