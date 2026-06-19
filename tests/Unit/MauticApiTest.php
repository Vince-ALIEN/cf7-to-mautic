<?php

use Brain\Monkey\Functions;
use CF7Mautic\Tests\TestCase;

class MauticApiTest extends TestCase
{
    private CF7Mautic_MauticApi $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->api = new CF7Mautic_MauticApi([
            'url'           => 'mautic.example.com',
            'client_id'     => 'client-id',
            'client_secret' => 'client-secret',
        ]);
        Functions\when('get_transient')->justReturn('fake-token');
        Functions\when('is_email')->justReturn(true);
        Functions\when('error_log')->justReturn(null);
    }

    private function stubGet(array $body, int $code = 200): void
    {
        Functions\when('wp_remote_get')->justReturn(['raw' => true]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode($body));
        Functions\when('wp_remote_retrieve_response_code')->justReturn($code);
    }

    private function stubPost(array $body, int $code = 200): void
    {
        Functions\when('wp_remote_post')->justReturn(['raw' => true]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode($body));
        Functions\when('wp_remote_retrieve_response_code')->justReturn($code);
    }

    // --- find_contact_by_email ---

    public function test_find_contact_by_email_retourne_null_si_aucun_contact(): void
    {
        $this->stubGet(['total' => 0, 'contacts' => []]);

        $result = $this->api->find_contact_by_email('inconnu@example.com');

        $this->assertNull($result);
    }

    public function test_find_contact_by_email_retourne_contact_si_un_resultat(): void
    {
        // Premier GET : recherche (total=1)
        // Deuxième GET : get_contact(42)
        Functions\when('wp_remote_get')->alias(function () {
            static $calls = 0;
            $calls++;
            return match ($calls) {
                1 => ['raw' => 'search'],
                default => ['raw' => 'contact'],
            };
        });
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->alias(function () {
            static $calls = 0;
            $calls++;
            return match ($calls) {
                1 => json_encode(['total' => 1, 'contacts' => [42 => ['id' => 42]]]),
                default => json_encode(['contact' => ['id' => 42, 'email' => 'test@example.com']]),
            };
        });

        $result = $this->api->find_contact_by_email('test@example.com');

        $this->assertSame(42, $result['contact']['id']);
    }

    public function test_find_contact_by_email_retourne_premier_si_plusieurs_resultats(): void
    {
        Functions\when('wp_remote_get')->alias(function () {
            static $calls = 0;
            $calls++;
            return ['raw' => $calls];
        });
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->alias(function () {
            static $calls = 0;
            $calls++;
            return match ($calls) {
                1 => json_encode(['total' => 2, 'contacts' => [10 => ['id' => 10], 11 => ['id' => 11]]]),
                default => json_encode(['contact' => ['id' => 10]]),
            };
        });

        $result = $this->api->find_contact_by_email('doublon@example.com');

        $this->assertSame(10, $result['contact']['id']);
    }

    public function test_find_contact_by_email_retourne_erreur_si_email_invalide(): void
    {
        Functions\when('is_email')->justReturn(false);

        $result = $this->api->find_contact_by_email('pas-un-email');

        $this->assertArrayHasKey('error', $result);
    }

    // --- find_or_create_contact ---

    public function test_find_or_create_contact_filtre_les_champs_internes_a_la_creation(): void
    {
        // find_contact_by_email retourne null → création
        $this->stubGet(['total' => 0, 'contacts' => []]);

        $body_envoye = null;
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$body_envoye) {
            $body_envoye = json_decode($args['body'], true);
            return ['raw' => true];
        });
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['contact' => ['id' => 99]]));
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

        $data = [
            'email'         => 'nouveau@example.com',
            'firstname'     => 'Alice',
            'segment'       => 'newsletter',
            'formId'        => '2',
            'ip'            => '1.2.3.4',
            '_retry_count'  => 1,
        ];

        $this->api->find_or_create_contact($data);

        $this->assertArrayNotHasKey('segment', $body_envoye ?? []);
        $this->assertArrayNotHasKey('formId', $body_envoye ?? []);
        $this->assertArrayNotHasKey('ip', $body_envoye ?? []);
        $this->assertArrayNotHasKey('_retry_count', $body_envoye ?? []);
        $this->assertArrayHasKey('email', $body_envoye ?? []);
        $this->assertArrayHasKey('firstname', $body_envoye ?? []);
    }

    public function test_find_or_create_contact_retourne_erreur_si_email_manquant(): void
    {
        $result = $this->api->find_or_create_contact(['firstname' => 'Alice']);

        $this->assertArrayHasKey('error', $result);
    }

    // --- find_segment_by_name ---

    public function test_find_segment_by_name_retourne_null_si_aucun_segment(): void
    {
        $this->stubGet(['total' => 0, 'lists' => []]);

        $result = $this->api->find_segment_by_name('inexistant');

        $this->assertNull($result);
    }

    public function test_find_segment_by_name_retourne_id_si_segment_trouve(): void
    {
        $this->stubGet(['total' => 1, 'lists' => [5 => ['id' => 5, 'name' => 'newsletter']]]);

        $result = $this->api->find_segment_by_name('newsletter');

        $this->assertSame(5, $result);
    }

    public function test_find_segment_by_name_retourne_premier_si_plusieurs_resultats(): void
    {
        $this->stubGet(['total' => 2, 'lists' => [3 => ['id' => 3], 4 => ['id' => 4]]]);

        $result = $this->api->find_segment_by_name('newsletter');

        $this->assertSame(3, $result);
    }

    // --- create_segment ---

    public function test_create_segment_retourne_id_cree(): void
    {
        $this->stubPost(['list' => ['id' => 7]]);

        $result = $this->api->create_segment('nouveau-segment');

        $this->assertSame(7, $result);
    }

    public function test_create_segment_retourne_null_si_echec(): void
    {
        $this->stubPost(['error' => 'forbidden'], 403);

        $result = $this->api->create_segment('segment-interdit');

        $this->assertNull($result);
    }

    // --- submit_form ---

    public function test_submit_form_retourne_erreur_si_formid_absent(): void
    {
        $result = $this->api->submit_form(['email' => 'test@example.com']);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('formId manquant', $result['error']);
    }

    public function test_submit_form_filtre_retry_count_des_donnees_envoyees(): void
    {
        $body_envoye = null;
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$body_envoye) {
            parse_str($args['body'], $parsed);
            $body_envoye = $parsed['mauticform'] ?? [];
            return ['raw' => true];
        });
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

        $this->api->submit_form([
            'formId'        => '2',
            'email'         => 'test@example.com',
            '_retry_count'  => 2,
        ]);

        $this->assertArrayNotHasKey('_retry_count', $body_envoye ?? []);
        $this->assertArrayHasKey('email', $body_envoye ?? []);
    }

    // --- test_connection ---

    public function test_test_connection_retourne_succes_si_api_accessible(): void
    {
        $this->stubGet(['total' => 5, 'contacts' => []]);

        $result = $this->api->test_connection();

        $this->assertTrue($result['success']);
    }

    public function test_test_connection_retourne_echec_si_reponse_inattendue(): void
    {
        $this->stubGet(['inattendu' => true]);

        $result = $this->api->test_connection();

        $this->assertFalse($result['success']);
    }

    public function test_test_connection_retourne_echec_si_erreur_oauth(): void
    {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('wp_remote_post')->justReturn(new WP_Error('http_error', 'Connexion impossible'));
        Functions\when('is_wp_error')->justReturn(true);

        $result = $this->api->test_connection();

        $this->assertFalse($result['success']);
    }
}
