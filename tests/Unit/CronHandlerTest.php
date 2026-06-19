<?php

use Brain\Monkey\Functions;
use CF7Mautic\Tests\TestCase;

class CronHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('error_log')->justReturn(null);
    }

    private function callMaybeRetry(array $data, int $retry_count): void
    {
        $ref = new ReflectionMethod(CF7Mautic_CronHandler::class, 'maybe_retry');
        $ref->setAccessible(true);
        $ref->invoke(null, $data, $retry_count);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'email'   => 'test@example.com',
            'segment' => 'newsletter',
        ], $overrides);
    }

    // --- maybe_retry ---

    public function test_maybe_retry_planifie_apres_premiere_erreur(): void
    {
        Functions\expect('wp_schedule_single_event')
            ->once()
            ->with(\Mockery::type('int'), 'mautic_process_submission', \Mockery::type('array'));

        $this->callMaybeRetry($this->basePayload(), 0);
    }

    public function test_maybe_retry_planifie_apres_deuxieme_erreur(): void
    {
        Functions\expect('wp_schedule_single_event')->once();

        $this->callMaybeRetry($this->basePayload(['_retry_count' => 1]), 1);
    }

    public function test_maybe_retry_planifie_apres_troisieme_erreur(): void
    {
        Functions\expect('wp_schedule_single_event')->once();

        $this->callMaybeRetry($this->basePayload(['_retry_count' => 2]), 2);
    }

    public function test_maybe_retry_abandonne_apres_max_retries(): void
    {
        Functions\expect('wp_schedule_single_event')->never();

        $this->callMaybeRetry($this->basePayload(['_retry_count' => 3]), 3);
    }

    public function test_maybe_retry_incremente_le_compteur_dans_le_payload(): void
    {
        $payload_planifie = null;
        Functions\when('wp_schedule_single_event')->alias(function ($time, $hook, $args) use (&$payload_planifie) {
            $payload_planifie = $args[0];
        });

        $this->callMaybeRetry($this->basePayload(), 0);

        $this->assertSame(1, $payload_planifie['_retry_count']);
    }

    public function test_maybe_retry_applique_backoff_exponentiel(): void
    {
        $delai_capture = null;
        $now = time();
        Functions\when('wp_schedule_single_event')->alias(function ($time, $hook, $args) use (&$delai_capture, $now) {
            $delai_capture = $time - $now;
        });

        // retry_count=0 → délai = 2^0 * 60 = 60s
        $this->callMaybeRetry($this->basePayload(), 0);
        $this->assertEqualsWithDelta(60, $delai_capture, 2);
    }

    public function test_maybe_retry_backoff_deuxieme_tentative(): void
    {
        $delai_capture = null;
        $now = time();
        Functions\when('wp_schedule_single_event')->alias(function ($time, $hook, $args) use (&$delai_capture, $now) {
            $delai_capture = $time - $now;
        });

        // retry_count=1 → délai = 2^1 * 60 = 120s
        $this->callMaybeRetry($this->basePayload(['_retry_count' => 1]), 1);
        $this->assertEqualsWithDelta(120, $delai_capture, 2);
    }

    public function test_maybe_retry_backoff_troisieme_tentative(): void
    {
        $delai_capture = null;
        $now = time();
        Functions\when('wp_schedule_single_event')->alias(function ($time, $hook, $args) use (&$delai_capture, $now) {
            $delai_capture = $time - $now;
        });

        // retry_count=2 → délai = 2^2 * 60 = 240s
        $this->callMaybeRetry($this->basePayload(['_retry_count' => 2]), 2);
        $this->assertEqualsWithDelta(240, $delai_capture, 2);
    }

    // --- process (configuration manquante) ---

    public function test_process_abandonne_si_configuration_mautic_vide(): void
    {
        Functions\when('get_option')->justReturn('');
        Functions\expect('wp_schedule_single_event')->never();

        CF7Mautic_CronHandler::process($this->basePayload(['email' => 'test@example.com']));
    }
}
