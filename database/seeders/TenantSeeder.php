<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Erster Mandant: Lea Wernli. Idempotent.
 * Farben und Schriften stammen aus dem Elementor-Kit von leawernli.ch (Kit 20)
 * und werden beim WordPress-Import (import:wordpress --branding) ueberschrieben.
 */
class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $lea = Tenant::updateOrCreate(['slug' => 'lea'], [
            'name' => 'Lea Wernli',
            'locale' => 'de_CH',
            'timezone' => 'Europe/Zurich',
            'currency' => 'CHF',
            'settings' => [
                'shop' => ['driver' => 'woocommerce', 'url' => 'https://leawernli.ch'],
                'website' => 'https://leawernli.ch',
            ],
            'branding' => [
                'app_name' => 'Lea Wernli',
                'card_bg' => '#FFFDF8',
                'card_border' => '#E4DFD2',
                'radius' => 16,
                'font_scale' => [11, 12.5, 13.5, 15.5, 17, 20, 26, 32],
            ],
        ]);

        foreach (['app.leawernli.ch' => true, 'lea.localhost' => false] as $domain => $primary) {
            $lea->domains()->updateOrCreate(['domain' => $domain], ['is_primary' => $primary]);
        }
    }
}
