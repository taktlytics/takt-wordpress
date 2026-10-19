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

    private const URL_LABELS = [
        'api_endpoint' => 'Origine d\'ingestion',
        'script_origin' => 'Origine du script',
    ];

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'addPage']);
        \add_action('admin_init', [$this, 'registerSetting']);
        \add_action('admin_notices', [$this, 'renderIgnoredUrlNotice']);
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
        // Write-only key: a blank submission means "unchanged", not "erase".
        if (($raw['api_key'] ?? '') === '') {
            $stored = (array) \get_option(Plugin::OPTION, []);
            if (!empty($stored['api_key'])) {
                $raw['api_key'] = $stored['api_key'];
            }
        }

        $clean = Settings::sanitize($raw);
        foreach (self::rejectedUrlKeys($raw, $clean) as $key) {
            \add_settings_error(
                Plugin::OPTION,
                'takt_' . $key,
                self::URL_LABELS[$key] . ' : URL refusée. Seul https est accepté, http est réservé à localhost.',
                'error',
            );
        }

        return $clean;
    }

    /**
     * @param array<string,mixed> $stored
     * @return list<string>
     */
    public static function ignoredUrlKeys(array $stored): array
    {
        return self::rejectedUrlKeys($stored, Settings::sanitize($stored));
    }

    /**
     * @param array<string,mixed> $stored
     */
    public static function ignoredUrlNotice(array $stored): string
    {
        $messages = array_map(
            static fn (string $key): string => '<p>' . \esc_html(self::ignoredUrlMessage($key)) . '</p>',
            self::ignoredUrlKeys($stored),
        );

        return $messages === [] ? '' : '<div class="notice notice-error">' . implode('', $messages) . '</div>';
    }

    public function renderIgnoredUrlNotice(): void
    {
        if (!\current_user_can('manage_options') || \get_current_screen()?->id === 'settings_page_takt-analytics') {
            return;
        }

        echo self::ignoredUrlNotice((array) \get_option(Plugin::OPTION, []));
    }

    /**
     * @param array<string,mixed> $raw
     * @param array<string,mixed> $clean
     * @return list<string>
     */
    private static function rejectedUrlKeys(array $raw, array $clean): array
    {
        $rejected = [];
        foreach (array_keys(self::URL_LABELS) as $key) {
            $submitted = is_string($raw[$key] ?? null) ? trim($raw[$key]) : '';
            if ($submitted !== '' && $clean[$key] === '') {
                $rejected[] = $key;
            }
        }

        return $rejected;
    }

    private static function ignoredUrlMessage(string $key): string
    {
        $message = self::URL_LABELS[$key]
            . " : l'URL enregistrée n'est pas acceptée (https requis, http réservé à localhost).";

        return $key === 'api_endpoint'
            ? $message . ' Tant qu\'elle n\'est pas corrigée, l\'envoi des achats WooCommerce est interrompu.'
            : $message;
    }

    /**
     * @param list<string> $ignored
     */
    private static function ignoredUrlHint(string $key, array $ignored): string
    {
        return in_array($key, $ignored, true) ? self::ignoredUrlMessage($key) : '';
    }

    public static function keyStorageNotice(bool $locked, string $storedKey): string
    {
        if ($locked || $storedKey === '') {
            return '';
        }

        return '<div class="notice notice-warning"><p>'
            . \esc_html('La clé API est enregistrée dans les options WordPress. Définissez la constante TAKT_API_KEY dans wp-config.php pour la retirer de la base de données.')
            . '</p></div>';
    }

    public function render(): void
    {
        if (!\current_user_can('manage_options')) {
            return;
        }

        $stored = (array) \get_option(Plugin::OPTION, []);
        $s = Settings::sanitize($stored);
        $ignored = self::ignoredUrlKeys($stored);
        $keyLocked = \defined('TAKT_API_KEY');
        $hasKey = $keyLocked || ($s['api_key'] ?? '') !== '';

        echo '<div class="wrap"><h1>Takt Analytics</h1>'
            . self::keyStorageNotice($keyLocked, (string) $s['api_key'])
            . '<form method="post" action="options.php">';
        \settings_fields(self::GROUP);
        echo '<table class="form-table" role="presentation">';

        $this->textRow('domain', 'Domaine', $s['domain'], 'example.com');
        $this->selectRow('mode', 'Mode du script', $s['mode'], [
            'inline' => 'Inline (anti-adblock)',
            'cdn' => 'CDN (jsDelivr)',
            'asset' => 'Fichier hébergé',
            'sdk' => 'SDK (module ES)',
        ]);
        $this->textRow('script_origin', 'Origine du script', $s['script_origin'], 'https://cdn.exemple.com', self::ignoredUrlHint('script_origin', $ignored));
        $this->checkboxRow('exclude_localhost', 'Exclure localhost', $s['exclude_localhost']);

        $this->checkboxRow('outbound', 'Clics sortants', $s['outbound']);
        $this->checkboxRow('files', 'Téléchargements', $s['files']);
        $this->checkboxRow('tagged', 'Événements balisés (HTML)', $s['tagged']);
        $this->checkboxRow('not_found', 'Pages 404', $s['not_found']);
        $this->textRow('file_extensions', 'Extensions téléchargeables', implode(', ', $s['file_extensions']), 'pdf, zip, docx');

        $rate = $s['sample_rate'] === null ? '' : (string) $s['sample_rate'];
        $this->textRow('sample_rate', 'Taux d\'échantillonnage', $rate, '0.5');
        $this->checkboxRow('track_query', 'Conserver la query string', $s['track_query']);
        $this->textRow('query_params', 'Paramètres de requête conservés', implode(', ', $s['query_params']), 'utm_source, utm_medium');
        $this->textRow('exclude', 'Chemins exclus du suivi (mode SDK)', implode(', ', $s['exclude']), '/app, /account');
        $this->checkboxRow('ignore_dnt', 'Ignorer Do-Not-Track', $s['ignore_dnt']);
        $this->checkboxRow('disable_tracking', 'Suspendre le suivi', $s['disable_tracking']);

        $this->checkboxRow('woocommerce', 'Suivi WooCommerce (achats)', $s['woocommerce']);
        $this->selectRow('wc_trigger_status', 'Déclencheur de la commande', $s['wc_trigger_status'], [
            'completed' => 'Terminée',
            'processing' => 'En traitement',
        ]);
        $endpointHint = self::ignoredUrlHint('api_endpoint', $ignored);
        $this->textRow(
            'api_endpoint',
            "Origine d'ingestion",
            $s['api_endpoint'],
            'https://taktlytics.com',
            'Origine du service Takt, sans le chemin de collecte. « /api/event » est ajouté automatiquement, '
                . "et l'URL de collecte complète est acceptée puis repliée sur son origine."
                . ($endpointHint === '' ? '' : ' ' . $endpointHint),
        );
        $this->apiKeyRow($hasKey, $keyLocked);

        echo '</table>';
        \submit_button();
        echo '</form></div>';
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

    private function checkboxRow(string $key, string $label, bool $checked): void
    {
        printf(
            '<tr><th scope="row">%2$s</th><td><label><input type="checkbox" name="%3$s" value="1" %4$s /> %2$s</label></td></tr>',
            \esc_attr($key),
            \esc_html($label),
            \esc_attr($this->field($key)),
            \checked($checked, true, false),
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
            ? '<p class="description">Définie via la constante <code>TAKT_API_KEY</code> (wp-config.php).</p>'
            : '';
        printf(
            '<tr><th scope="row"><label for="takt_api_key">Clé API</label></th><td>'
                . '<input type="password" id="takt_api_key" name="%1$s" value="" placeholder="%2$s" autocomplete="new-password" class="regular-text" %3$s />%4$s</td></tr>',
            \esc_attr($this->field('api_key')),
            $hasKey ? '••••••••' : '',
            $locked ? 'disabled' : '',
            $note,
        );
    }
}
