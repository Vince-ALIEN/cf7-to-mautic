# Changelog

All notable changes to CF7 to Mautic are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [2.0.1] — 2026-06-19

### Fixed

- **Alignement icône dashicon** dans le bouton "Tester la connexion" : l'icône avait un `font-size` de 20 px par défaut, supérieur à celui du texte, causant un décalage visuel. Corrigé en forçant `font-size`, `width`, `height` et `line-height` à 16 px avec `display:inline-flex` sur le bouton.

### Added

- **`Makefile`** : automatise la création du zip de distribution (`make zip`) et l'exécution des tests PHPUnit (`make test`) depuis le répertoire du plugin.

---

## [2.0] — 2026-06-19

Ufo Agency — correctifs de robustesse, sécurité et fiabilité.

### Fixed

- **Champs parasites envoyés à Mautic** : `segment`, `formId`, `ip` et `_retry_count` sont désormais filtrés avant l'appel `POST /api/contacts/new`. Seuls les champs métier du formulaire sont transmis à l'API contacts.
- **Doublons de segment/contact** : `find_segment_by_name` et `find_contact_by_email` retournaient `null` dès que l'API renvoyait plus d'un résultat, ce qui déclenchait une création en double. Ils retournent maintenant le premier résultat existant.
- **IP visiteur derrière un proxy** : `SubmissionHandler` lit désormais `HTTP_X_FORWARDED_FOR` en priorité avant `REMOTE_ADDR`, ce qui donne l'IP réelle du visiteur derrière un load-balancer ou un CDN.
- **Fallback `REMOTE_ADDR` en contexte cron** : le fallback `$_SERVER['REMOTE_ADDR']` dans `submit_form` est supprimé — il capturait l'IP du serveur (127.0.0.1) lors de l'exécution asynchrone, l'IP étant déjà capturée dans `SubmissionHandler` avant la planification.
- **`submit_form` bypassait `HttpClient`** : la soumission au formulaire Mautic utilisait un `wp_remote_post` direct, échappant au timeout centralisé. Elle passe désormais par `HttpClient::post_raw()`, une nouvelle méthode dédiée aux réponses non-JSON.
- **Logs debug avec données personnelles** : le journal de débogage écrivait le tableau complet (`email`, `firstname`, etc.) en clair dans `debug.log`. Seuls `segment` et `formId` sont maintenant loggés.
- **Discordance de version** : `readme.txt` indiquait `Stable tag: 0.5` alors que le plugin était en version 2.0.

### Added

- **Retry automatique** : en cas d'échec de l'appel API Mautic, la soumission est replanifiée via WP-Cron avec un backoff exponentiel (60 s, 120 s, 240 s). Après 3 tentatives, l'abandon est tracé en `error`. Le champ interne `_retry_count` est propagé dans la tâche cron et filtré avant tout envoi à Mautic.
- **`HttpClient::post_raw()`** : nouvelle méthode pour les endpoints retournant du HTML ou un redirect (soumission formulaire Mautic).
- **`CF7Mautic_SubmissionHandler::get_client_ip()`** : méthode dédiée à la résolution de l'IP client, avec support `X-Forwarded-For`.

---

## [0.5] — Ufo Agency (fork initial)

Refonte et sécurisation du plugin original.

### Added

- Authentification OAuth2 Client Credentials (token mis en cache via les transients WP)
- Traitement asynchrone via WP-Cron : le spinner CF7 ne bloque plus
- Page de paramètres dédiée sous *Réglages > CF7 to Mautic*
- Bouton de test de connexion Mautic
- Capture de l'IP visiteur avant planification asynchrone

### Changed

- Logs de débogage enrichis
- Sanitisation des clés de champs préservant la casse (ex: `formId`)

---

## [0.0.4] — Ulrich Eckardt (version originale)

Version initiale du plugin.

### Added

- Envoi synchrone des soumissions CF7 vers l'API Mautic
- Création/mise à jour de contact
- Ajout du contact à un segment
- Soumission optionnelle à un formulaire Mautic (tracking)
