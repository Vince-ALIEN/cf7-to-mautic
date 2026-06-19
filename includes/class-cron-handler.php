<?php
defined('ABSPATH') || exit;

class CF7Mautic_CronHandler {
    const MAX_RETRIES = 3;

    public static function process($send_array) {
        $retry_count = isset($send_array['_retry_count']) ? intval($send_array['_retry_count']) : 0;
        $email_label = isset($send_array['email']) ? $send_array['email'] : 'email manquant';

        CF7Mautic_Logger::log(
            'Début du traitement asynchrone pour: ' . $email_label . ' (tentative ' . ($retry_count + 1) . ')',
            'info'
        );
        CF7Mautic_Logger::log(
            'Données reçues: segment=' . ($send_array['segment'] ?? 'N/A') . ', formId=' . ($send_array['formId'] ?? 'N/A'),
            'debug'
        );

        $config = CF7Mautic_Settings::get();
        if (empty($config['url'])) {
            CF7Mautic_Logger::log('Configuration Mautic vide dans le traitement asynchrone', 'error');
            return;
        }

        CF7Mautic_Logger::log('Configuration chargée: URL=' . $config['url'], 'debug');

        $api = new CF7Mautic_MauticApi($config);

        $contact = $api->find_or_create_contact($send_array);
        if (!$contact || isset($contact['error'])) {
            CF7Mautic_Logger::log(
                'Erreur lors de la récupération/création du contact: ' . (isset($contact['error']) ? $contact['error'] : 'inconnue'),
                'error'
            );
            self::maybe_retry($send_array, $retry_count);
            return;
        }

        if (!isset($contact['contact']['id'])) {
            CF7Mautic_Logger::log('ID contact manquant dans la réponse', 'error');
            self::maybe_retry($send_array, $retry_count);
            return;
        }

        $segment_id = $api->find_or_create_segment($send_array['segment']);
        if ($segment_id === null) {
            CF7Mautic_Logger::log('Erreur lors de la récupération/création du segment', 'error');
            self::maybe_retry($send_array, $retry_count);
            return;
        }

        $api->add_contact_to_segment($contact['contact']['id'], $segment_id);

        if (isset($send_array['formId'])) {
            CF7Mautic_Logger::log('Envoi au formulaire Mautic ID=' . $send_array['formId'], 'debug');
            $api->submit_form($send_array);
        }

        CF7Mautic_Logger::log('Données envoyées avec succès à Mautic pour: ' . $email_label, 'info');
    }

    private static function maybe_retry($send_array, $retry_count) {
        if ($retry_count >= self::MAX_RETRIES) {
            CF7Mautic_Logger::log('Abandon après ' . self::MAX_RETRIES . ' tentatives pour: ' . ($send_array['email'] ?? 'inconnu'), 'error');
            return;
        }
        $send_array['_retry_count'] = $retry_count + 1;
        $delay = (int) pow(2, $retry_count) * 60;
        wp_schedule_single_event(time() + $delay, 'mautic_process_submission', array($send_array));
        CF7Mautic_Logger::log('Retry #' . ($retry_count + 1) . ' planifié dans ' . $delay . 's', 'info');
    }
}
