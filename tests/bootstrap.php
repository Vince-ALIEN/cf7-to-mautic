<?php

define('ABSPATH', '/');
define('WP_DEBUG', true);
define('MAUTIC_PLUGIN_VERSION', '2.0');
define('MAUTIC_PLUGIN_DIR', dirname(__DIR__) . '/');
define('MAUTIC_PLUGIN_BASENAME', 'cf7-to-mautic/mautic.php');

require_once __DIR__ . '/../vendor/autoload.php';

// Stubs des classes WordPress/CF7 non disponibles hors WP
if (!class_exists('WP_Error')) {
    class WP_Error {
        private string $message;
        public function __construct(string $code = '', string $message = '') {
            $this->message = $message;
        }
        public function get_error_message(): string {
            return $this->message;
        }
    }
}

if (!class_exists('WPCF7_Submission')) {
    class WPCF7_Submission {
        private static ?self $instance = null;
        private array $data = [];

        public static function set_mock(array $data): void {
            self::$instance = new self();
            self::$instance->data = $data;
        }

        public static function get_instance(): ?self {
            return self::$instance;
        }

        public function get_posted_data(): array {
            return $this->data;
        }
    }
}

if (!class_exists('WPCF7_ContactForm')) {
    class WPCF7_ContactForm {
        private int $form_id;
        public function __construct(int $id = 1) {
            $this->form_id = $id;
        }
        public function id(): int {
            return $this->form_id;
        }
    }
}

require_once MAUTIC_PLUGIN_DIR . 'includes/class-logger.php';
require_once MAUTIC_PLUGIN_DIR . 'includes/class-settings.php';
require_once MAUTIC_PLUGIN_DIR . 'includes/class-http-client.php';
require_once MAUTIC_PLUGIN_DIR . 'includes/class-oauth-authenticator.php';
require_once MAUTIC_PLUGIN_DIR . 'includes/class-mautic-api.php';
require_once MAUTIC_PLUGIN_DIR . 'includes/class-submission-handler.php';
require_once MAUTIC_PLUGIN_DIR . 'includes/class-cron-handler.php';
require_once MAUTIC_PLUGIN_DIR . 'includes/class-uninstall.php';
