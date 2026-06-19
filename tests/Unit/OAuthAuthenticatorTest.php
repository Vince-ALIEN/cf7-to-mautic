<?php

use Brain\Monkey\Functions;
use CF7Mautic\Tests\TestCase;

class OAuthAuthenticatorTest extends TestCase
{
    private CF7Mautic_OAuthAuthenticator $oauth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oauth = new CF7Mautic_OAuthAuthenticator('mautic.example.com', 'client-id', 'client-secret');
    }

    private function stubSuccessfulTokenResponse(string $token = 'access-token-abc', int $expires = 3600): void
    {
        $body = json_encode(['access_token' => $token, 'expires_in' => $expires]);
        Functions\when('wp_remote_post')->justReturn(['raw' => true]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn($body);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('error_log')->justReturn(null);
    }

    // --- get_access_token ---

    public function test_get_access_token_retourne_token_depuis_cache(): void
    {
        Functions\when('get_transient')->justReturn('token-en-cache');

        $token = $this->oauth->get_access_token();

        $this->assertSame('token-en-cache', $token);
    }

    public function test_get_access_token_appelle_authenticate_si_pas_de_cache(): void
    {
        Functions\when('get_transient')->justReturn(false);
        $this->stubSuccessfulTokenResponse('nouveau-token');

        $token = $this->oauth->get_access_token();

        $this->assertSame('nouveau-token', $token);
    }

    // --- authenticate (via get_access_token) ---

    public function test_authenticate_stocke_token_dans_transient(): void
    {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('wp_remote_post')->justReturn(['raw' => true]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([
            'access_token' => 'mon-token',
            'expires_in'   => 3600,
        ]));
        Functions\when('error_log')->justReturn(null);

        Functions\expect('set_transient')
            ->once()
            ->with(CF7Mautic_OAuthAuthenticator::TOKEN_TRANSIENT_KEY, 'mon-token', \Mockery::type('int'));

        $this->oauth->get_access_token();
    }

    public function test_authenticate_retourne_null_si_reponse_http_non_200(): void
    {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('wp_remote_post')->justReturn(['raw' => true]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(401);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['error_description' => 'Identifiants invalides']));
        Functions\when('error_log')->justReturn(null);

        $token = $this->oauth->get_access_token();

        $this->assertNull($token);
    }

    public function test_authenticate_retourne_null_si_wp_error(): void
    {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('wp_remote_post')->justReturn(new WP_Error('http_request_failed', 'cURL error'));
        Functions\when('is_wp_error')->justReturn(true);
        Functions\when('error_log')->justReturn(null);

        $token = $this->oauth->get_access_token();

        $this->assertNull($token);
    }

    public function test_authenticate_calcule_expiration_avec_marge_de_300s(): void
    {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('wp_remote_post')->justReturn(['raw' => true]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([
            'access_token' => 'token',
            'expires_in'   => 3600,
        ]));
        Functions\when('error_log')->justReturn(null);

        // expires_in 3600 - 300 = 3300
        Functions\expect('set_transient')
            ->once()
            ->with(\Mockery::any(), \Mockery::any(), 3300);

        $this->oauth->get_access_token();
    }

    // --- refresh ---

    public function test_refresh_supprime_le_cache_et_reautentifie(): void
    {
        Functions\expect('delete_transient')
            ->once()
            ->with(CF7Mautic_OAuthAuthenticator::TOKEN_TRANSIENT_KEY);

        Functions\when('get_transient')->justReturn(false);
        $this->stubSuccessfulTokenResponse('nouveau-token-apres-refresh');

        $token = $this->oauth->refresh();

        $this->assertSame('nouveau-token-apres-refresh', $token);
    }
}
