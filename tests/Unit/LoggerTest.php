<?php

use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    private string $tmpfile;
    private string|false $originalLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpfile    = tempnam(sys_get_temp_dir(), 'phpunit_cf7log_');
        $this->originalLog = ini_set('error_log', $this->tmpfile);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->originalLog);
        if (file_exists($this->tmpfile)) {
            unlink($this->tmpfile);
        }
        parent::tearDown();
    }

    private function logContent(): string
    {
        return file_get_contents($this->tmpfile) ?: '';
    }

    public function test_log_ecrit_dans_error_log_quand_wp_debug_actif(): void
    {
        CF7Mautic_Logger::log('message test', 'info');

        $this->assertStringContainsString('[CF7 to Mautic] [INFO] message test', $this->logContent());
    }

    public function test_log_normalise_le_niveau_en_majuscules(): void
    {
        CF7Mautic_Logger::log('erreur critique', 'error');

        $this->assertStringContainsString('[CF7 to Mautic] [ERROR] erreur critique', $this->logContent());
    }

    public function test_log_niveau_debug(): void
    {
        CF7Mautic_Logger::log('détail interne', 'debug');

        $this->assertStringContainsString('[CF7 to Mautic] [DEBUG] détail interne', $this->logContent());
    }
}
