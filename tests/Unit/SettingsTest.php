<?php

use Brain\Monkey\Functions;
use CF7Mautic\Tests\TestCase;

class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('sanitize_text_field')->returnArg();
    }

    // --- sanitize_url ---

    public function test_sanitize_url_supprime_https(): void
    {
        $this->assertSame('mautic.example.com', CF7Mautic_Settings::sanitize_url('https://mautic.example.com'));
    }

    public function test_sanitize_url_supprime_http(): void
    {
        $this->assertSame('mautic.example.com', CF7Mautic_Settings::sanitize_url('http://mautic.example.com'));
    }

    public function test_sanitize_url_supprime_slash_final(): void
    {
        $this->assertSame('mautic.example.com', CF7Mautic_Settings::sanitize_url('mautic.example.com/'));
    }

    public function test_sanitize_url_supprime_https_et_slash_final(): void
    {
        $this->assertSame('mautic.example.com', CF7Mautic_Settings::sanitize_url('https://mautic.example.com/'));
    }

    public function test_sanitize_url_retourne_url_propre_inchangee(): void
    {
        $this->assertSame('mautic.example.com', CF7Mautic_Settings::sanitize_url('mautic.example.com'));
    }

    // --- is_complete ---

    public function test_is_complete_retourne_false_si_url_vide(): void
    {
        Functions\when('get_option')->alias(fn($key) => match ($key) {
            'mautic_url' => '',
            default => 'valeur',
        });

        $this->assertFalse(CF7Mautic_Settings::is_complete());
    }

    public function test_is_complete_retourne_false_si_client_id_vide(): void
    {
        Functions\when('get_option')->alias(fn($key) => match ($key) {
            'mautic_client_id' => '',
            default => 'valeur',
        });

        $this->assertFalse(CF7Mautic_Settings::is_complete());
    }

    public function test_is_complete_retourne_false_si_client_secret_vide(): void
    {
        Functions\when('get_option')->alias(fn($key) => match ($key) {
            'mautic_client_secret' => '',
            default => 'valeur',
        });

        $this->assertFalse(CF7Mautic_Settings::is_complete());
    }

    public function test_is_complete_retourne_true_si_tous_champs_remplis(): void
    {
        Functions\when('get_option')->justReturn('valeur');

        $this->assertTrue(CF7Mautic_Settings::is_complete());
    }

    // --- get ---

    public function test_get_retourne_tableau_avec_trois_cles(): void
    {
        Functions\when('get_option')->alias(fn($key) => match ($key) {
            'mautic_url'           => 'mautic.example.com',
            'mautic_client_id'     => 'client-id',
            'mautic_client_secret' => 'client-secret',
            default                => '',
        });

        $config = CF7Mautic_Settings::get();

        $this->assertSame('mautic.example.com', $config['url']);
        $this->assertSame('client-id', $config['client_id']);
        $this->assertSame('client-secret', $config['client_secret']);
    }
}
