<?php

use Brain\Monkey\Functions;
use CF7Mautic\Tests\TestCase;

class SubmissionHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('error_log')->justReturn(null);
    }

    private function callPrivate(string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod(CF7Mautic_SubmissionHandler::class, $method);
        $ref->setAccessible(true);
        return $ref->invoke(null, ...$args);
    }

    // --- extract ---

    public function test_extract_supprime_le_prefixe_your(): void
    {
        Functions\when('sanitize_text_field')->returnArg();

        $data = $this->callPrivate('extract', [
            'segment'        => 'newsletter',
            'your-email'     => 'test@example.com',
            'your-firstname' => 'Alice',
        ]);

        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('firstname', $data);
        $this->assertArrayNotHasKey('your-email', $data);
    }

    public function test_extract_conserve_la_casse_des_cles(): void
    {
        Functions\when('sanitize_text_field')->returnArg();

        $data = $this->callPrivate('extract', [
            'segment' => 'newsletter',
            'formId'  => '2',
        ]);

        $this->assertArrayHasKey('formId', $data);
    }

    public function test_extract_filtre_les_champs_systeme_cf7(): void
    {
        Functions\when('sanitize_text_field')->returnArg();

        $posted = [
            'segment'               => 'newsletter',
            'your-email'            => 'test@example.com',
            '_wpcf7'                => '123',
            '_wpcf7_version'        => '5.8',
            '_wpcf7_locale'         => 'fr_FR',
            '_wpcf7_unit_tag'       => 'wpcf7-f1',
            '_wpcf7_container_post' => '0',
        ];

        $data = $this->callPrivate('extract', $posted);

        foreach (['_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag', '_wpcf7_container_post'] as $key) {
            $this->assertArrayNotHasKey($key, $data);
        }
    }

    public function test_extract_conserve_segment(): void
    {
        Functions\when('sanitize_text_field')->returnArg();

        $data = $this->callPrivate('extract', ['segment' => 'ma-liste']);

        $this->assertSame('ma-liste', $data['segment']);
    }

    // --- sanitize ---

    public function test_sanitize_applique_sanitize_email_sur_champ_email(): void
    {
        $captured = null;
        Functions\when('sanitize_email')->alias(function ($v) use (&$captured) {
            $captured = $v;
            return 'test@example.com';
        });

        $result = $this->callPrivate('sanitize', ['email' => ' Test@Example.COM ']);

        $this->assertSame(' Test@Example.COM ', $captured);
        $this->assertSame('test@example.com', $result['email']);
    }

    public function test_sanitize_applique_sanitize_text_field_sur_champs_texte(): void
    {
        $captured = null;
        Functions\when('sanitize_text_field')->alias(function ($v) use (&$captured) {
            $captured = $v;
            return 'alert1';
        });

        $result = $this->callPrivate('sanitize', ['firstname' => '<script>alert(1)</script>']);

        $this->assertSame('<script>alert(1)</script>', $captured);
        $this->assertSame('alert1', $result['firstname']);
    }

    public function test_sanitize_supprime_caracteres_speciaux_dans_les_cles(): void
    {
        Functions\when('sanitize_text_field')->returnArg();

        $result = $this->callPrivate('sanitize', ['champ bizarre!' => 'valeur']);

        $this->assertArrayHasKey('champbizarre', $result);
        $this->assertArrayNotHasKey('champ bizarre!', $result);
    }

    public function test_sanitize_preserve_les_cles_camelcase(): void
    {
        Functions\when('sanitize_text_field')->returnArg();

        $result = $this->callPrivate('sanitize', ['formId' => '2']);

        $this->assertArrayHasKey('formId', $result);
    }

    public function test_sanitize_traite_les_tableaux_recursivement(): void
    {
        Functions\when('sanitize_text_field')->returnArg();

        $result = $this->callPrivate('sanitize', ['choix' => ['option1', 'option2']]);

        $this->assertIsArray($result['choix']);
        $this->assertCount(2, $result['choix']);
    }

    // --- validate ---

    public function test_validate_retourne_false_si_email_manquant(): void
    {
        Functions\when('is_email')->justReturn(false);

        $result = $this->callPrivate('validate', ['segment' => 'newsletter']);

        $this->assertFalse($result);
    }

    public function test_validate_retourne_false_si_email_invalide(): void
    {
        Functions\when('is_email')->justReturn(false);

        $result = $this->callPrivate('validate', ['email' => 'pas-un-email', 'segment' => 'newsletter']);

        $this->assertFalse($result);
    }

    public function test_validate_retourne_false_si_segment_manquant(): void
    {
        Functions\when('is_email')->justReturn(true);

        $result = $this->callPrivate('validate', ['email' => 'test@example.com']);

        $this->assertFalse($result);
    }

    public function test_validate_retourne_true_si_email_et_segment_valides(): void
    {
        Functions\when('is_email')->justReturn(true);

        $result = $this->callPrivate('validate', [
            'email'   => 'test@example.com',
            'segment' => 'newsletter',
        ]);

        $this->assertTrue($result);
    }

    // --- get_client_ip ---

    public function test_get_client_ip_lit_x_forwarded_for_en_priorite(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.42, 10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $ip = $this->callPrivate('get_client_ip');

        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);

        $this->assertSame('203.0.113.42', $ip);
    }

    public function test_get_client_ip_fallback_sur_remote_addr(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $ip = $this->callPrivate('get_client_ip');

        unset($_SERVER['REMOTE_ADDR']);

        $this->assertSame('192.168.1.1', $ip);
    }

    public function test_get_client_ip_ignore_ip_invalide_dans_x_forwarded_for(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'pas-une-ip';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';

        $ip = $this->callPrivate('get_client_ip');

        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);

        $this->assertSame('10.0.0.5', $ip);
    }

    // --- handle ---

    public function test_handle_abandonne_si_configuration_incomplete(): void
    {
        Functions\when('get_option')->justReturn('');

        $form = new WPCF7_ContactForm(1);
        CF7Mautic_SubmissionHandler::handle($form);

        $this->assertTrue(true);
    }

    public function test_handle_abandonne_si_segment_absent(): void
    {
        Functions\when('get_option')->justReturn('valeur');
        WPCF7_Submission::set_mock(['your-email' => 'test@example.com']);

        Functions\expect('wp_schedule_single_event')->never();

        $form = new WPCF7_ContactForm(1);
        CF7Mautic_SubmissionHandler::handle($form);
    }

    public function test_handle_planifie_cron_si_donnees_valides(): void
    {
        Functions\when('get_option')->justReturn('valeur');
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('sanitize_email')->returnArg();
        Functions\when('is_email')->justReturn(true);

        WPCF7_Submission::set_mock([
            'your-email' => 'test@example.com',
            'segment'    => 'newsletter',
        ]);

        Functions\expect('wp_schedule_single_event')->once();
        Functions\expect('spawn_cron')->once();

        $form = new WPCF7_ContactForm(1);
        CF7Mautic_SubmissionHandler::handle($form);
    }
}
