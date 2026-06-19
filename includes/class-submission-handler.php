<?php
defined('ABSPATH') || exit;

class CF7Mautic_SubmissionHandler {
    public static function handle($contact_form) {
        if (!CF7Mautic_Settings::is_complete()) {
            CF7Mautic_Logger::log('Configuration Mautic incomplète, abandon', 'error');
            return;
        }

        $submission = WPCF7_Submission::get_instance();
        if (!$submission) {
            return;
        }

        $posted_data = $submission->get_posted_data();

        if (!isset($posted_data['segment'])) {
            return;
        }

        $data = self::extract($posted_data);
        $data = self::sanitize($data);

        if (!self::validate($data)) {
            CF7Mautic_Logger::log('Validation des données échouée', 'error');
            return;
        }

        $data['ip'] = self::get_client_ip();

        wp_schedule_single_event(time(), 'mautic_process_submission', array($data));
        spawn_cron();

        CF7Mautic_Logger::log('Soumission planifiée pour envoi asynchrone: ' . $data['email'], 'info');
    }

    private static function extract($posted_data) {
        $data = array();
        $data['segment'] = sanitize_text_field($posted_data['segment']);

        $cf7_system_fields = array(
            '_wpcf7', '_wpcf7_version', '_wpcf7_locale',
            '_wpcf7_unit_tag', '_wpcf7_container_post',
        );

        foreach ($posted_data as $key => $value) {
            if (in_array($key, $cf7_system_fields)) {
                continue;
            }

            if (strpos($key, 'your-') === 0) {
                $clean_key = substr($key, 5);
            } else {
                $clean_key = $key;
            }

            $data[$clean_key] = $value;
        }

        return $data;
    }

    private static function sanitize($data) {
        $sanitized = array();
        foreach ($data as $key => $value) {
            $clean_key = preg_replace('/[^a-zA-Z0-9_\-]/', '', $key);
            if (is_array($value)) {
                $sanitized[$clean_key] = self::sanitize($value);
            } elseif ($key === 'email' || stripos($key, 'email') !== false) {
                $sanitized[$clean_key] = sanitize_email($value);
            } else {
                $sanitized[$clean_key] = sanitize_text_field($value);
            }
        }
        return $sanitized;
    }

    private static function get_client_ip() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '';
    }

    private static function validate($data) {
        if (!isset($data['email']) || !is_email($data['email'])) {
            CF7Mautic_Logger::log('Email invalide ou manquant: ' . (isset($data['email']) ? $data['email'] : 'non défini'), 'error');
            return false;
        }

        if (!isset($data['segment']) || empty($data['segment'])) {
            CF7Mautic_Logger::log('Segment manquant', 'error');
            return false;
        }

        return true;
    }
}
