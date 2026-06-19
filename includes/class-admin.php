<?php
defined('ABSPATH') || exit();

class CF7Mautic_Admin
{
    public static function init()
    {
        add_filter('plugin_row_meta', array(__CLASS__, 'add_row_meta'), 10, 4);
        add_filter('plugin_action_links_' . MAUTIC_PLUGIN_BASENAME, array(__CLASS__, 'add_action_links'));
        add_action('admin_menu', array(__CLASS__, 'add_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }

    public static function add_row_meta($plugin_meta, $plugin_file, $plugin_data, $status)
    {
        if (MAUTIC_PLUGIN_BASENAME === $plugin_file) {
            $plugin_meta[] = '<a href="' . admin_url('options-general.php?page=cf72mautic') . '">Paramètres</a>';
        }
        return $plugin_meta;
    }

    public static function add_action_links($links)
    {
        $settings_link = '<a href="' . admin_url('options-general.php?page=cf72mautic') . '">Paramètres</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public static function add_menu()
    {
        add_options_page(
            'CF7 to Mautic',
            'CF7 to Mautic',
            'manage_options',
            'cf72mautic',
            array(__CLASS__, 'render_page')
        );
    }

    public static function register_settings()
    {
        CF7Mautic_Settings::register();

        add_settings_section(
            'mautic_main_section',
            'Configuration de la connexion Mautic',
            array(__CLASS__, 'render_section'),
            'cf72mautic'
        );

        add_settings_field(
            'mautic_url',
            'URL Mautic',
            array(__CLASS__, 'render_url_field'),
            'cf72mautic',
            'mautic_main_section'
        );

        add_settings_field(
            'mautic_client_id',
            'Client ID',
            array(__CLASS__, 'render_client_id_field'),
            'cf72mautic',
            'mautic_main_section'
        );

        add_settings_field(
            'mautic_client_secret',
            'Client Secret',
            array(__CLASS__, 'render_client_secret_field'),
            'cf72mautic',
            'mautic_main_section'
        );
    }

    public static function render_section()
    {
        echo '<p>Configurez les paramètres de connexion à votre instance Mautic via OAuth2.</p>';
        echo '<p><strong>Pour obtenir vos credentials OAuth2 :</strong></p>';
        echo '<ol>';
        echo '<li>Dans Mautic, allez dans <strong>Paramètres &gt; Intégrations &gt; API Credentials</strong></li>';
        echo '<li>Cliquez sur <strong>+ Nouveau</strong></li>';
        echo '<li>Sélectionnez <strong>OAuth 2</strong></li>';
        echo '<li>Donnez un nom (ex: "CF7 WordPress")</li>';
        echo '<li>Laissez "Redirect URI" vide ou mettez l\'URL de votre site</li>';
        echo '<li>Copiez le <strong>Public Key</strong> (Client ID) et le <strong>Secret Key</strong> (Client Secret)</li>';
        echo '</ol>';
    }

    public static function render_url_field()
    {
        $value = get_option(CF7Mautic_Settings::URL_KEY, '');
        echo '<input type="text" name="' . CF7Mautic_Settings::URL_KEY . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="mautic.votredomaine.com" />';
        echo '<p class="description">URL de votre instance Mautic <strong>sans</strong> https://</p>';
    }

    public static function render_client_id_field()
    {
        $value = get_option(CF7Mautic_Settings::CLIENT_ID_KEY, '');
        echo '<input type="text" name="' . CF7Mautic_Settings::CLIENT_ID_KEY . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="1_abc123..." />';
        echo '<p class="description">Public Key de votre application API Mautic</p>';
    }

    public static function render_client_secret_field()
    {
        $value = get_option(CF7Mautic_Settings::CLIENT_SECRET_KEY, '');
        echo '<input type="password" name="' . CF7Mautic_Settings::CLIENT_SECRET_KEY . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="xyz789..." />';
        echo '<p class="description">Secret Key de votre application API Mautic</p>';
    }

    public static function render_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $test_result = null;
        if (isset($_POST['mautic_test_connection']) && check_admin_referer('mautic_test_connection_nonce')) {
            $config = CF7Mautic_Settings::get();
            $api = new CF7Mautic_MauticApi($config);
            $test_result = $api->test_connection();
        }

        $is_configured = CF7Mautic_Settings::is_complete();
        $config = CF7Mautic_Settings::get();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="card" style="max-width:100%;margin-bottom:20px;padding:15px;">
                <h2 style="margin-top:0;">Statut de la connexion</h2>
                <?php if ($is_configured): ?>
                    <p style="color:green;font-size:1.1em;">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <strong>Configuration complète</strong>
                    </p>
                    <p>URL Mautic : <code>https://<?php echo esc_html($config['url']); ?></code></p>
                <?php else: ?>
                    <p style="color:red;font-size:1.1em;">
                        <span class="dashicons dashicons-warning"></span>
                        <strong>Configuration incomplète</strong>
                    </p>
                    <p>Veuillez remplir tous les champs ci-dessous.</p>
                <?php endif; ?>

                <?php if ($test_result !== null): ?>
                    <div class="notice <?php echo $test_result['success'] ? 'notice-success' : 'notice-error'; ?> inline" style="margin:10px 0;">
                        <p>
                            <?php if ($test_result['success']): ?>
                                <span class="dashicons dashicons-yes"></span>
                                <strong>Connexion réussie !</strong> <?php echo esc_html($test_result['message']); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-no"></span>
                                <strong>Erreur :</strong> <?php echo esc_html($test_result['message']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($is_configured): ?>
                    <form method="post" style="margin-top:15px;">
                        <?php wp_nonce_field('mautic_test_connection_nonce'); ?>
                        <button type="submit" name="mautic_test_connection" class="button button-secondary">
                            <span class="dashicons dashicons-update" style="vertical-align:middle;"></span>
                            Tester la connexion
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card" style="max-width:100%;padding:15px;">
                <h2 style="margin-top:0;">Paramètres de connexion OAuth2</h2>
                <form method="post" action="options.php">
                    <?php
                    settings_fields(CF7Mautic_Settings::SETTINGS_GROUP);
                    do_settings_sections('cf72mautic');
                    submit_button('Enregistrer les paramètres');
                    ?>
                </form>
            </div>

            <div class="card" style="max-width:100%;margin-top:20px;padding:15px;">
                <h2 style="margin-top:0;">Configuration des formulaires Contact Form 7</h2>

                <h3>1. Ajouter un segment (requis)</h3>
                <p>Dans chaque formulaire CF7, ajoutez un champ caché pour spécifier le segment Mautic :</p>
                <pre style="background:#1d2327;color:#fff;padding:15px;border-radius:4px;overflow-x:auto;">[hidden segment "nom-du-segment"]</pre>

                <h3>2. Soumission à un formulaire Mautic (optionnel)</h3>
                <p>Pour le tracking via un formulaire Mautic :</p>
                <pre style="background:#1d2327;color:#fff;padding:15px;border-radius:4px;overflow-x:auto;">[hidden formId "16"]</pre>

                <h3>3. Mapping des champs</h3>
                <p>Le préfixe <code>your-</code> est automatiquement supprimé. Exemples :</p>
                <table class="widefat" style="margin:15px 0;">
                    <thead>
                        <tr>
                            <th>Champ CF7</th>
                            <th>Champ Mautic</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[email* your-email]</code></td>
                            <td>email</td>
                        </tr>
                        <tr>
                            <td><code>[text your-firstname]</code></td>
                            <td>firstname</td>
                        </tr>
                        <tr>
                            <td><code>[text your-lastname]</code></td>
                            <td>lastname</td>
                        </tr>
                        <tr>
                            <td><code>[tel your-phone]</code></td>
                            <td>phone</td>
                        </tr>
                    </tbody>
                </table>

                <h3>4. Exemple complet</h3>
                <pre style="background:#1d2327;color:#fff;padding:15px;border-radius:4px;overflow-x:auto;">&lt;label&gt;Email (requis)
    [email* your-email]&lt;/label&gt;

&lt;label&gt;Prénom
    [text your-firstname]&lt;/label&gt;

&lt;label&gt;Nom
    [text your-lastname]&lt;/label&gt;

&lt;label&gt;Message
    [textarea your-message]&lt;/label&gt;

[hidden segment "newsletter-site"]

[submit "Envoyer"]</pre>
            </div>

            <div class="card" style="max-width:100%;margin-top:20px;padding:15px;">
                <h2 style="margin-top:0;">Debug</h2>
                <p>Pour activer les logs, ajoutez ces lignes dans votre <code>wp-config.php</code> :</p>
                <pre style="background:#1d2327;color:#fff;padding:15px;border-radius:4px;overflow-x:auto;">define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );</pre>
                <p>Les logs seront disponibles dans <code>wp-content/debug.log</code></p>
            </div>

            <p style="color:#666;margin-top:20px;">
                Plugin version <?php echo MAUTIC_PLUGIN_VERSION; ?> |
                <a href="https://www.ufo-agency.com" target="_blank">Ufo Agency</a>
            </p>
        </div>
        <?php
    }
}
