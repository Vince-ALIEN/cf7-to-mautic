<?php
defined('ABSPATH') || exit;

class CF7Mautic_Settings {
    const URL_KEY = 'mautic_url';
    const CLIENT_ID_KEY = 'mautic_client_id';
    const CLIENT_SECRET_KEY = 'mautic_client_secret';
    const SETTINGS_GROUP = 'mautic_settings_group';

    public static function register() {
        register_setting(self::SETTINGS_GROUP, self::URL_KEY, array(
            'type' => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_url'),
            'default' => '',
        ));
        register_setting(self::SETTINGS_GROUP, self::CLIENT_ID_KEY, array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));
        register_setting(self::SETTINGS_GROUP, self::CLIENT_SECRET_KEY, array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));
    }

    public static function sanitize_url($url) {
        $url = sanitize_text_field($url);
        $url = preg_replace('#^https?://#', '', $url);
        return rtrim($url, '/');
    }

    public static function get() {
        return array(
            'url' => get_option(self::URL_KEY, ''),
            'client_id' => get_option(self::CLIENT_ID_KEY, ''),
            'client_secret' => get_option(self::CLIENT_SECRET_KEY, ''),
        );
    }

    public static function is_complete() {
        $config = self::get();
        return !empty($config['url']) && !empty($config['client_id']) && !empty($config['client_secret']);
    }

    public static function delete_all() {
        delete_option(self::URL_KEY);
        delete_option(self::CLIENT_ID_KEY);
        delete_option(self::CLIENT_SECRET_KEY);
    }
}
