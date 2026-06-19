<?php
defined('ABSPATH') || exit;

class CF7Mautic_Uninstall {
    public static function cleanup() {
        CF7Mautic_Settings::delete_all();
        delete_transient(CF7Mautic_OAuthAuthenticator::TOKEN_TRANSIENT_KEY);
    }
}
