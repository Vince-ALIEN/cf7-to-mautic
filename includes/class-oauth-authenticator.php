<?php
defined('ABSPATH') || exit;

class CF7Mautic_OAuthAuthenticator {
    const TOKEN_TRANSIENT_KEY = 'mautic_oauth_token';

    private $url;
    private $client_id;
    private $client_secret;

    public function __construct($url, $client_id, $client_secret) {
        $this->url = $url;
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
    }

    public function get_access_token() {
        $cached = get_transient(self::TOKEN_TRANSIENT_KEY);
        if ($cached) {
            return $cached;
        }

        return $this->authenticate();
    }

    public function refresh() {
        delete_transient(self::TOKEN_TRANSIENT_KEY);
        return $this->authenticate();
    }

    private function authenticate() {
        $token_url = "https://{$this->url}/oauth/v2/token";

        $response = wp_remote_post($token_url, array(
            'timeout' => 30,
            'body' => array(
                'grant_type' => 'client_credentials',
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
            ),
        ));

        if (is_wp_error($response)) {
            CF7Mautic_Logger::log('Erreur OAuth2: ' . $response->get_error_message(), 'error');
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || !isset($body['access_token'])) {
            $msg = isset($body['error_description']) ? $body['error_description'] : 'Token non reçu (HTTP ' . $code . ')';
            CF7Mautic_Logger::log('Erreur OAuth2: ' . $msg, 'error');
            return null;
        }

        $expires_in = isset($body['expires_in']) ? intval($body['expires_in']) - 300 : 3300;
        set_transient(self::TOKEN_TRANSIENT_KEY, $body['access_token'], max($expires_in, 60));

        CF7Mautic_Logger::log('Token OAuth2 obtenu avec succès', 'info');
        return $body['access_token'];
    }
}
