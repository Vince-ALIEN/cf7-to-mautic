<?php
defined('ABSPATH') || exit;

class CF7Mautic_MauticApi {
    private $http;
    private $oauth;
    private $url;
    private static $internal_fields = ['segment', 'formId', 'ip', '_retry_count'];

    public function __construct($config) {
        $this->url = $config['url'];
        $this->http = new CF7Mautic_HttpClient();
        $this->oauth = new CF7Mautic_OAuthAuthenticator(
            $config['url'],
            $config['client_id'],
            $config['client_secret']
        );
    }

    private function request($method, $endpoint, $body = null) {
        $token = $this->oauth->get_access_token();
        if (!$token) {
            return array('error' => 'Authentification OAuth2 échouée');
        }

        $headers = array('Authorization' => 'Bearer ' . $token);
        $url = "https://{$this->url}/" . ltrim($endpoint, '/');

        $result = ($method === 'POST')
            ? $this->http->post($url, $body, $headers, 'json')
            : $this->http->get($url, $headers);

        if (isset($result['_http_code']) && $result['_http_code'] === 401) {
            CF7Mautic_Logger::log('Token expiré, tentative de renouvellement', 'info');
            $token = $this->oauth->refresh();
            if (!$token) {
                return array('error' => 'Refresh token échoué');
            }
            $headers['Authorization'] = 'Bearer ' . $token;
            $result = ($method === 'POST')
                ? $this->http->post($url, $body, $headers, 'json')
                : $this->http->get($url, $headers);
        }

        unset($result['_http_code']);
        return $result;
    }

    public function find_contact_by_email($email) {
        if (!is_email($email)) {
            return array('error' => 'Email invalide');
        }

        $result = $this->request('GET', '/api/contacts?search=email:' . urlencode($email));
        if (isset($result['error'])) {
            return $result;
        }

        if (!isset($result['total']) || intval($result['total']) === 0) {
            return null;
        }

        $contact_id = key($result['contacts']);
        return $this->get_contact($contact_id);
    }

    public function create_contact($data) {
        return $this->request('POST', '/api/contacts/new', $data);
    }

    public function get_contact($id) {
        return $this->request('GET', '/api/contacts/' . intval($id));
    }

    public function find_or_create_contact($data) {
        if (!isset($data['email'])) {
            return array('error' => 'Email manquant');
        }

        $contact = $this->find_contact_by_email($data['email']);

        if ($contact && !isset($contact['error'])) {
            return $contact;
        }

        if ($contact === null) {
            $contact_data = array_diff_key($data, array_flip(self::$internal_fields));
            $created = $this->create_contact($contact_data);
            if (isset($created['contact']['id'])) {
                return $this->get_contact($created['contact']['id']);
            }
            return array('error' => 'Création contact échouée');
        }

        return $contact;
    }

    public function find_segment_by_name($name) {
        $result = $this->request('GET', '/api/segments?search=name:' . urlencode($name));
        if (isset($result['error'])) {
            return null;
        }

        if (!isset($result['total']) || intval($result['total']) === 0) {
            return null;
        }

        $lists = $result['lists'];
        return key($lists);
    }

    public function create_segment($name) {
        $alias = preg_replace('/[^a-zA-Z0-9\-]/', '', $name);
        $result = $this->request('POST', '/api/segments/new', array(
            'name' => $name,
            'alias' => $alias,
            'description' => $name,
            'isPublished' => 1,
            'isGlobal' => 1,
        ));

        if (isset($result['list']['id'])) {
            return $result['list']['id'];
        }

        return null;
    }

    public function find_or_create_segment($name) {
        $id = $this->find_segment_by_name($name);
        return $id !== null ? $id : $this->create_segment($name);
    }

    public function add_contact_to_segment($contact_id, $segment_id) {
        $url = "/api/segments/" . intval($segment_id) . "/contact/" . intval($contact_id) . "/add";
        return $this->request('POST', $url);
    }

    public function remove_contact_from_segment($contact_id, $segment_id) {
        $url = "/api/segments/" . intval($segment_id) . "/contact/" . intval($contact_id) . "/remove";
        return $this->request('POST', $url);
    }

    public function find_contacts_in_segment($email, $segment) {
        $search = urlencode("email:{$email} segment:{$segment}");
        return $this->request('GET', "/api/contacts?search={$search}");
    }

    public function submit_form($data) {
        if (!isset($data['formId'])) {
            return array('error' => 'formId manquant');
        }

        $url = "https://{$this->url}/form/submit?formId=" . intval($data['formId']);
        $form_fields = array_diff_key($data, array_flip(['_retry_count']));
        $form_data = http_build_query(array('mauticform' => $form_fields));
        $token = $this->oauth->get_access_token();
        $headers = $token ? array('Authorization' => 'Bearer ' . $token) : array();

        $result = $this->http->post_raw($url, $form_data, $headers);
        if (isset($result['error'])) {
            CF7Mautic_Logger::log('Erreur envoi formulaire Mautic: ' . $result['error'], 'error');
        }
        return $result;
    }

    public function remove_contact_from_segment_by_email($data) {
        $contact = $this->find_contact_by_email($data['email']);
        if (isset($contact['error'])) {
            return false;
        }

        $segment_id = $this->find_segment_by_name($data['segment']);
        if ($segment_id === null) {
            return false;
        }

        $this->remove_contact_from_segment($contact['contact']['id'], $segment_id);
        return true;
    }

    public function test_connection() {
        $result = $this->request('GET', '/api/contacts?limit=1');
        if (isset($result['error'])) {
            return array('success' => false, 'message' => $result['error']);
        }
        if (isset($result['contacts']) || isset($result['total'])) {
            return array('success' => true, 'message' => 'Connexion réussie');
        }
        return array('success' => false, 'message' => 'Réponse inattendue de l\'API');
    }
}
