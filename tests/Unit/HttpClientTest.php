<?php

use Brain\Monkey\Functions;
use CF7Mautic\Tests\TestCase;

class HttpClientTest extends TestCase
{
    private CF7Mautic_HttpClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new CF7Mautic_HttpClient();
    }

    private function stubResponse(array $body, int $code = 200): array
    {
        $response = ['raw' => true];
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode($body));
        Functions\when('wp_remote_retrieve_response_code')->justReturn($code);
        return $response;
    }

    // --- GET ---

    public function test_get_retourne_donnees_decodees(): void
    {
        $response = $this->stubResponse(['total' => 1, 'contacts' => []]);
        Functions\when('wp_remote_get')->justReturn($response);

        $result = $this->client->get('https://mautic.example.com/api/contacts');

        $this->assertSame(1, $result['total']);
        $this->assertSame(200, $result['_http_code']);
    }

    public function test_get_retourne_erreur_si_wp_error(): void
    {
        Functions\when('wp_remote_get')->justReturn(new WP_Error('http_error', 'Connexion refusée'));
        Functions\when('is_wp_error')->justReturn(true);

        $result = $this->client->get('https://mautic.example.com/api/contacts');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Connexion refusée', $result['error']);
    }

    public function test_get_retourne_erreur_si_reponse_non_json(): void
    {
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_body')->justReturn('<html>erreur</html>');
        Functions\when('wp_remote_retrieve_response_code')->justReturn(500);
        Functions\when('wp_remote_get')->justReturn([]);

        $result = $this->client->get('https://mautic.example.com/api/contacts');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('JSON decode error', $result['error']);
    }

    // --- POST ---

    public function test_post_envoie_corps_json_et_retourne_donnees(): void
    {
        $response = $this->stubResponse(['contact' => ['id' => 42]]);
        Functions\when('wp_remote_post')->justReturn($response);

        $result = $this->client->post('https://mautic.example.com/api/contacts/new', ['email' => 'test@example.com']);

        $this->assertSame(42, $result['contact']['id']);
    }

    public function test_post_retourne_erreur_si_wp_error(): void
    {
        Functions\when('wp_remote_post')->justReturn(new WP_Error('http_error', 'Timeout'));
        Functions\when('is_wp_error')->justReturn(true);

        $result = $this->client->post('https://mautic.example.com/api/contacts/new', []);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Timeout', $result['error']);
    }

    // --- POST RAW ---

    public function test_post_raw_retourne_code_http_sans_decoder_json(): void
    {
        Functions\when('wp_remote_post')->justReturn(['raw' => true]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

        $result = $this->client->post_raw('https://mautic.example.com/form/submit', 'mauticform[email]=test@example.com');

        $this->assertSame(200, $result['code']);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function test_post_raw_retourne_erreur_si_wp_error(): void
    {
        Functions\when('wp_remote_post')->justReturn(new WP_Error('http_error', 'Timeout'));
        Functions\when('is_wp_error')->justReturn(true);

        $result = $this->client->post_raw('https://mautic.example.com/form/submit', '');

        $this->assertArrayHasKey('error', $result);
    }
}
