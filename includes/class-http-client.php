<?php
defined('ABSPATH') || exit;

class CF7Mautic_HttpClient {
    private $timeout = 30;

    public function get($url, $headers = array()) {
        return $this->request($url, array(
            'method' => 'GET',
            'headers' => $headers,
        ));
    }

    public function post($url, $body, $headers = array(), $content_type = 'json') {
        $args = array(
            'method' => 'POST',
            'headers' => $headers,
        );

        if ($content_type === 'json') {
            $args['body'] = is_string($body) ? $body : json_encode($body);
            $args['headers']['Content-Type'] = 'application/json';
        } else {
            $args['body'] = $body;
        }

        return $this->request($url, $args);
    }

    public function post_raw($url, $body, $headers = array()) {
        $args = array(
            'timeout' => $this->timeout,
            'headers' => $headers,
            'body' => $body,
        );
        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        return array('code' => wp_remote_retrieve_response_code($response));
    }

    private function request($url, $args = array()) {
        $args['timeout'] = $this->timeout;

        $method = isset($args['method']) ? $args['method'] : 'GET';
        unset($args['method']);

        if ($method === 'POST') {
            $response = wp_remote_post($url, $args);
        } else {
            $response = wp_remote_get($url, $args);
        }

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('error' => 'JSON decode error', '_http_code' => $code);
        }

        $data['_http_code'] = $code;
        return $data;
    }
}
