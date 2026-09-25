<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Erster Mandant: Lea Wernli. Idempotent.
 * Farben, Schriften und Masse stammen aus dem bisherigen Mitgliederbereich auf leawernli.ch
 * (lea-design, lea-ui, lea-willkommen). Alles Lea-Spezifische steht hier und nur hier.
 */
class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $vorgaben = [
            'name' => 'Lea Wernli',
            'locale' => 'de_CH',
            'timezone' => 'Europe/Zurich',
            'currency' => 'CHF',
            'settings' => [
                // Woo-Webhook: Geheimnis ueber /plattform eintragen (webhook_secret), Ziel https://app.leawernli.ch/hooks/woocommerce
                // Passkeys: RP-ID ist die Haupt-Domain, damit auf leawernli.ch angelegte Passkeys auch fuer app. gelten
                'passkeys' => ['rp_id' => 'leawernli.ch', 'origins' => ['https://leawernli.ch']],
                'coach_name' => 'Lea',
                // Parallelbetrieb: Benachrichtigungen nur an freigegebene Adressen (im Coach-Bereich unter Einstellungen erweiterbar)
                'notifications' => ['test_only' => true, 'test_emails' => ['mail@sfrankenberger.com']],
                'shop' => ['driver' => 'woocommerce', 'url' => 'https://leawernli.ch', 'webhook_secret' => null],
                // Impulse und Podcast per RSS (inhalte:feeds). Eigener Podcast bei Kajabi, Blog nur "Free"-Beitraege.
                'feeds' => [
                    ['type' => 'podcast', 'url' => 'https://app.kajabi.com/podcasts/2147743703/feed', 'show' => 'Abenteuer Leben', 'limit' => 30],
                    ['type' => 'post', 'url' => 'https://leawernli.ch/?sichtbarkeit=free&feed=rss2', 'limit' => 20],
                ],
                'website' => 'https://leawernli.ch',
                'mail' => [
                    'from_address' => 'hallo@leawernli.ch',
                    'from_name' => 'Lea Wernli',
                ],
                // Zugangsdaten fuer Google/Apple kommen ueber die Plattform-Verwaltung in die DB, nie in Git.
                'oauth' => [
                    'google' => ['client_id' => null, 'client_secret' => null],
                    'apple' => ['client_id' => null, 'client_secret' => null],
                ],
                // Wie der WordPress-Import Personen und Rollen zuordnet (import:wordpress lea --only=users).
                'import' => ['wordpress' => [
                    'owner_ids' => [2],                                   // LEA_KR_LEA in lea-kursraum.php
                    'team_roles' => ['administrator', 'lea_redaktion'],
                    'course_relation_id' => 13,                            // JetEngine-Relation Teilnehmer zu Kurse
                    'relations' => ['course_modules' => 9, 'module_units' => 10],
                    'club_product_id' => 1524,                             // WooCommerce-Produkt des Clubs
                    'program_types' => ['1849' => 'hybrid', '1112' => 'club'],
                    'workbook_dir' => '/var/www/vhosts/leawernli.ch/httpdocs/wp-content/novamira-daten',
                    'uploads_dir' => '/var/www/vhosts/leawernli.ch/httpdocs/wp-content/uploads',
                    'uploads_url' => 'https://leawernli.ch/wp-content/uploads',
                    'event_timestamps_are_local' => true,
                    // Inhalte: Blog-Beitraege mit Sichtbarkeit "Free", Kategorie 89 = Neuigkeiten, Podcast-Serien aus "series"
                    'post_visibility' => ['taxonomy' => 'sichtbarkeit', 'slug' => 'free'],
                    'news_categories' => [89],
                    'post_exclude_ids' => [],
                    'podcast_series_taxonomy' => 'series',
                    'topic_taxonomies' => ['thema', 'podcast_thema'],
                    'meta' => [
                        'phone' => 'lea_telefon',
                        'reminders_off' => 'lea_te_aus',
                        'evening_mail_off' => 'lea_am_aus',
                        'task_reminders_off' => 'lea_ap_erinnerung_aus',
                        'onboarding_seen' => 'lea_willkommen_gesehen',
                        'access' => 'lea_zugaenge',
                        'progress' => 'je_data_store_erledigt',
                        'manual_courses' => 'lea_kurse_manuell',
                        'unit_todos' => 'lea_todos_',
                        'workbook_answers' => 'lea_wb_antworten',
                        'workbook_shared' => 'lea_wb_geteilt',
                        'attended' => 'lea_live_dabei',
                        'watched' => 'lea_angeschaut',
                        'foreign_tasks_done' => 'lea_af_fremd_fertig',
                        'chat_seen' => 'lea_ch_gesehen_',
                    ],
                ]],
            ],
            'branding' => [
                'app_name' => 'Lea Wernli',
                'short_name' => 'Lea',
                'primary' => '#B4795F',
                'primary_contrast' => '#FFFFFF',
                'text' => '#2E2D29',
                'text_soft' => '#4A473F',
                'muted' => '#86816F',
                'bg' => '#F6F2EA',
                'card_bg' => '#FFFDF8',
                'card_border' => '#E4DFD2',
                'success' => '#6E8B74',
                'danger' => '#B5544F',
                'font_heading' => 'Lora, Georgia, serif',
                'font_body' => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
                'font_url' => 'https://fonts.googleapis.com/css2?family=Lora:wght@400;500&display=swap',
                'radius' => 16,
                'card_padding_y' => 14,
                'card_padding_x' => 16,
                'gap' => 8,
                'page_width' => 720,
                'font_scale' => [11, 12.5, 13.5, 15.5, 17, 20, 26, 32],
                // App-Icons liegen noch in WordPress unter uploads/lea-app/, werden mit dem Import kopiert.
                'icon_url' => null,
                'logo_url' => null,
            ],
        ];

        // Idempotent und schonend: Vorgaben nur dort, wo noch nichts steht. Was spaeter gesetzt
        // wurde (Push-Schluessel, Bruecke, Freigaben, Aenderungen im Coach-Bereich), bleibt erhalten.
        $lea = Tenant::firstOrNew(['slug' => 'lea']);
        foreach (['name', 'locale', 'timezone', 'currency'] as $feld) {
            $lea->{$feld} ??= $vorgaben[$feld];
        }
        $lea->settings = self::vorgabenErgaenzen($lea->settings ?? [], $vorgaben['settings']);
        $lea->branding = self::vorgabenErgaenzen($lea->branding ?? [], $vorgaben['branding']);
        $lea->save();

        foreach (['app.leawernli.ch' => true, 'lea.localhost' => false] as $domain => $primary) {
            $lea->domains()->updateOrCreate(['domain' => $domain], ['is_primary' => $primary]);
        }
    }

    /** Vorgaben rekursiv ergaenzen, vorhandene Werte gewinnen (Listen werden nicht gemischt). */
    public static function vorgabenErgaenzen(array $ist, array $vorgabe): array
    {
        foreach ($vorgabe as $k => $v) {
            if (! array_key_exists($k, $ist) || $ist[$k] === null) {
                $ist[$k] = $v;
            } elseif (is_array($v) && is_array($ist[$k]) && ! array_is_list($v)) {
                $ist[$k] = self::vorgabenErgaenzen($ist[$k], $v);
            }
        }

        return $ist;
    }
}
