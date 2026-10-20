<?php

declare(strict_types=1);

namespace Vskstudio\Takt\WordPress;

/**
 * Settings screen under Settings → Takt Analytics. Values are stored in the
 * single Plugin::OPTION array and sanitized through Settings::sanitize; the API
 * key is write-only in the UI (never echoed back) and kept when left blank.
 */
final class AdminPage
{
    private const GROUP = 'takt_group';

    private const TEXT_DOMAIN = 'takt-analytics';

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'addPage']);
        \add_action('admin_init', [$this, 'registerSetting']);
        \add_action('admin_notices', [$this, 'renderRefusedUrlNotice']);
    }

    public function addPage(): void
    {
        \add_options_page(
            'Takt Analytics',
            'Takt Analytics',
            'manage_options',
            'takt-analytics',
            [$this, 'render'],
        );
    }

    public function registerSetting(): void
    {
        \register_setting(self::GROUP, Plugin::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize'],
            'default' => [],
        ]);
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    public static function sanitize(array $raw): array
    {
        $stored = (array) \get_option(Plugin::OPTION, []);
        if (($raw['api_key'] ?? '') === '' && !empty($stored['api_key'])) {
            $raw['api_key'] = $stored['api_key'];
        }
        $raw['refused_urls'] = Settings::refusedUrls($stored);

        $clean = Settings::sanitize($raw);
        foreach (Settings::refusedUrls($clean) as $key) {
            \add_settings_error(
                Plugin::OPTION,
                'takt_' . $key,
                self::refusalMessage($key),
                'error',
            );
        }

        return $clean;
    }

    /**
     * @param array<string,mixed> $stored
     */
    public static function refusedUrlNotice(array $stored): string
    {
        $messages = array_map(
            static fn (string $key): string => '<p>' . \esc_html(self::refusalMessage($key)) . '</p>',
            Settings::refusedUrls(Settings::sanitize($stored)),
        );

        return $messages === [] ? '' : '<div class="notice notice-error">' . implode('', $messages) . '</div>';
    }

    public function renderRefusedUrlNotice(): void
    {
        if (!\current_user_can('manage_options') || \get_current_screen()?->id === 'settings_page_takt-analytics') {
            return;
        }

        echo self::refusedUrlNotice((array) \get_option(Plugin::OPTION, []));
    }

    public static function keyStorageNotice(bool $locked, string $storedKey): string
    {
        if ($locked || $storedKey === '') {
            return '';
        }

        return '<div class="notice notice-warning"><p>'
            . \esc_html(\__('La clé API est enregistrée dans les options WordPress. Définissez la constante TAKT_API_KEY dans wp-config.php pour la retirer de la base de données.', self::TEXT_DOMAIN))
            . '</p></div>';
    }

    public function render(): void
    {
        if (!\current_user_can('manage_options')) {
            return;
        }

        $stored = (array) \get_option(Plugin::OPTION, []);
        $s = Settings::sanitize($stored);
        $refused = Settings::refusedUrls($s);
        $keyLocked = \defined('TAKT_API_KEY');
        $hasKey = $keyLocked || ($s['api_key'] ?? '') !== '';

        echo '<div class="wrap"><h1>Takt Analytics</h1>'
            . self::outOfSdkNotice($s)
            . self::keyStorageNotice($keyLocked, (string) $s['api_key'])
            . '<form method="post" action="options.php">';
        \settings_fields(self::GROUP);
        echo '<table class="form-table" role="presentation">';

        $this->textRow('domain', \__('Domaine', self::TEXT_DOMAIN), $s['domain'], 'example.com');
        $this->selectRow('mode', \__('Mode du script', self::TEXT_DOMAIN), $s['mode'], [
            'inline' => \__('Inline (anti-adblock)', self::TEXT_DOMAIN),
            'cdn' => \__('CDN (jsDelivr)', self::TEXT_DOMAIN),
            'asset' => \__('Fichier hébergé', self::TEXT_DOMAIN),
            'sdk' => \__('SDK (module ES)', self::TEXT_DOMAIN),
        ]);
        $this->textRow('script_origin', \__('Origine du script', self::TEXT_DOMAIN), $s['script_origin'], 'https://cdn.exemple.com', self::refusalHint('script_origin', $refused));
        $this->checkboxRow('exclude_localhost', \__('Exclure localhost', self::TEXT_DOMAIN), $s['exclude_localhost']);

        $this->checkboxRow('outbound', \__('Clics sortants', self::TEXT_DOMAIN), $s['outbound']);
        $this->checkboxRow('files', \__('Téléchargements', self::TEXT_DOMAIN), $s['files']);
        $this->checkboxRow('tagged', \__('Événements balisés (HTML)', self::TEXT_DOMAIN), $s['tagged']);
        $this->checkboxRow('not_found', \__('Pages 404', self::TEXT_DOMAIN), $s['not_found']);
        $this->textRow('file_extensions', \__('Extensions téléchargeables', self::TEXT_DOMAIN), implode(', ', $s['file_extensions']), 'pdf, zip, docx');

        $rate = $s['sample_rate'] === null ? '' : (string) $s['sample_rate'];
        $this->textRow('sample_rate', \__("Taux d'échantillonnage", self::TEXT_DOMAIN), $rate, '0.5');
        $this->checkboxRow('track_query', \__('Conserver la query string', self::TEXT_DOMAIN), $s['track_query']);
        $this->textRow('query_params', \__('Paramètres de requête conservés', self::TEXT_DOMAIN), implode(', ', $s['query_params']), 'utm_source, utm_medium');
        $this->textRow('exclude', \__('Chemins exclus du suivi (mode SDK)', self::TEXT_DOMAIN), implode(', ', $s['exclude']), '/app, /account');
        $this->textRow(
            'redact_routes',
            \__('Routes masquées (mode SDK)', self::TEXT_DOMAIN),
            implode(', ', $s['redact_routes']),
            '/verify/{token}, /reset/{code}',
            \__('Les pages qui correspondent à une route sont envoyées sous son gabarit, sans la valeur réelle des segments. Une valeur qui ne respecte pas une contrainte (ex. /reset/1234 avec {code<\d{1,3}>}) n\'est pas masquée et sort en clair. Le motif /* réécrit toutes les pages : ne l\'utilisez pas.', self::TEXT_DOMAIN),
        );
        $this->checkboxRow(
            'route_templates',
            \__('Toutes les pages sous leur gabarit (mode SDK)', self::TEXT_DOMAIN),
            $s['route_templates'],
            \__('Les pages sont envoyées sous leur gabarit, jamais sous leur chemin réel. Gabarit : le filtre takt_route_template, sinon / pour l\'accueil, /{post_type}/{slug} pour les contenus uniques et /[redacted] pour le reste (archives, recherche, 404).', self::TEXT_DOMAIN),
        );
        $this->checkboxRow('ignore_dnt', \__('Ignorer Do-Not-Track', self::TEXT_DOMAIN), $s['ignore_dnt']);
        $this->checkboxRow('disable_tracking', \__('Suspendre le suivi', self::TEXT_DOMAIN), $s['disable_tracking']);

        $this->checkboxRow('woocommerce', \__('Suivi WooCommerce (achats)', self::TEXT_DOMAIN), $s['woocommerce']);
        $this->selectRow('wc_trigger_status', \__('Déclencheur de la commande', self::TEXT_DOMAIN), $s['wc_trigger_status'], [
            'completed' => \__('Terminée', self::TEXT_DOMAIN),
            'processing' => \__('En traitement', self::TEXT_DOMAIN),
        ]);
        $this->textRow(
            'api_endpoint',
            \__("Origine d'ingestion", self::TEXT_DOMAIN),
            $s['api_endpoint'],
            'https://taktlytics.com',
            \__('Origine du service Takt, sans le chemin de collecte. « /api/event » est ajouté automatiquement, et l\'URL de collecte complète est acceptée puis repliée sur son origine.', self::TEXT_DOMAIN)
                . ' ' . self::refusalHint('api_endpoint', $refused),
        );
        $this->apiKeyRow($hasKey, $keyLocked);

        echo '</table>';
        \submit_button();
        echo '</form></div>';
    }

    private static function refusalMessage(string $key): string
    {
        return $key === 'api_endpoint'
            ? \__("Origine d'ingestion : l'URL n'est pas acceptée (https requis). L'envoi des achats WooCommerce est interrompu tant qu'elle n'est pas corrigée.", self::TEXT_DOMAIN)
            : \__("Origine du script : l'URL n'est pas acceptée (https requis). Le suivi est suspendu tant qu'elle n'est pas corrigée. Saisissez une origine https valide pour reprendre le suivi (une valeur vide ne suffit pas).", self::TEXT_DOMAIN);
    }

    /**
     * @param array<string,mixed> $s
     */
    public static function outOfSdkNotice(array $s): string
    {
        $routesSet = !empty($s['route_templates']) || $s['redact_routes'] !== [];
        if ($s['mode'] === 'sdk' || !$routesSet) {
            return '';
        }

        return '<div class="notice notice-error"><p>'
            . \esc_html(\__('Ces réglages sont ignorés hors mode SDK : les chemins bruts sont envoyés.', self::TEXT_DOMAIN))
            . '</p></div>';
    }

    /**
     * @param list<string> $refused
     */
    private static function refusalHint(string $key, array $refused): string
    {
        return in_array($key, $refused, true) ? self::refusalMessage($key) : '';
    }

    private function field(string $key): string
    {
        return Plugin::OPTION . '[' . $key . ']';
    }

    private function textRow(
        string $key,
        string $label,
        string $value,
        string $placeholder,
        string $description = '',
    ): void {
        printf(
            '<tr><th scope="row"><label for="takt_%1$s">%2$s</label></th><td>'
                . '<input type="text" id="takt_%1$s" name="%3$s" value="%4$s" placeholder="%5$s" class="regular-text" />%6$s</td></tr>',
            \esc_attr($key),
            \esc_html($label),
            \esc_attr($this->field($key)),
            \esc_attr($value),
            \esc_attr($placeholder),
            $description === '' ? '' : '<p class="description">' . \esc_html($description) . '</p>',
        );
    }

    private function checkboxRow(string $key, string $label, bool $checked, string $description = ''): void
    {
        printf(
            '<tr><th scope="row">%2$s</th><td><label><input type="checkbox" name="%3$s" value="1" %4$s /> %2$s</label>%5$s</td></tr>',
            \esc_attr($key),
            \esc_html($label),
            \esc_attr($this->field($key)),
            \checked($checked, true, false),
            $description === '' ? '' : '<p class="description">' . \esc_html($description) . '</p>',
        );
    }

    /** @param array<string,string> $choices */
    private function selectRow(string $key, string $label, string $value, array $choices): void
    {
        printf(
            '<tr><th scope="row"><label for="takt_%1$s">%2$s</label></th><td><select id="takt_%1$s" name="%3$s">',
            \esc_attr($key),
            \esc_html($label),
            \esc_attr($this->field($key)),
        );
        foreach ($choices as $val => $text) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                \esc_attr($val),
                \selected($value, $val, false),
                \esc_html($text),
            );
        }
        echo '</select></td></tr>';
    }

    private function apiKeyRow(bool $hasKey, bool $locked): void
    {
        $note = $locked
            ? '<p class="description">' . \__('Définie via la constante <code>TAKT_API_KEY</code> (wp-config.php).', self::TEXT_DOMAIN) . '</p>'
            : '';
        printf(
            '<tr><th scope="row"><label for="takt_api_key">%5$s</label></th><td>'
                . '<input type="password" id="takt_api_key" name="%1$s" value="" placeholder="%2$s" autocomplete="new-password" class="regular-text" %3$s />%4$s</td></tr>',
            \esc_attr($this->field('api_key')),
            $hasKey ? '••••••••' : '',
            $locked ? 'disabled' : '',
            $note,
            \esc_html(\__('Clé API', self::TEXT_DOMAIN)),
        );
    }
}
