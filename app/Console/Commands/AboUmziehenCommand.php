<?php

namespace App\Console\Commands;

use App\Models\Offer;
use App\Models\Tenant;
use App\Models\User;
use App\Shop\Abo;
use App\Tenancy\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Ein bestehendes Abo (WooCommerce, mit Stripe als Zahlungsdienst) in ein Stripe-Abo der App umziehen.
 * Voraussetzung: die Stripe-Kundennummer (cus_...) aus WooCommerce (_stripe_customer_id) und der naechste
 * Abbuchungstag. Bis dahin bucht Stripe nichts ab, der Zugang in der App gilt ab sofort.
 */
class AboUmziehenCommand extends Command
{
    protected $signature = 'abo:umziehen {tenant} {email} {angebot : Slug des Angebots} {--kunde= : Stripe-Kundennummer cus_...} {--ab= : naechste Abbuchung, z. B. 2026-11-01} {--betrag=} {--waehrung=CHF} {--trocken}';

    protected $description = 'Bestehendes Abo als Stripe-Abo in der App anlegen (Umzug aus WooCommerce)';

    public function handle(CurrentTenant $current): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->firstOrFail();

        return $current->run($tenant, function () use ($tenant) {
            $user = User::where('email', strtolower(trim($this->argument('email'))))->first();
            if (! $user || ! $user->membershipIn($tenant)) {
                $this->error('Keine Person mit dieser Adresse im Mandanten.');

                return self::FAILURE;
            }
            $offer = Offer::where('slug', $this->argument('angebot'))->first();
            if (! $offer || ! $offer->istAbo()) {
                $this->error('Angebot nicht gefunden oder kein Abo (settings.abo_intervall fehlt).');

                return self::FAILURE;
            }
            $kunde = (string) $this->option('kunde');
            if (! str_starts_with($kunde, 'cus_')) {
                $this->error('--kunde muss eine Stripe-Kundennummer sein (cus_...).');

                return self::FAILURE;
            }
            $ab = Carbon::parse((string) ($this->option('ab') ?: now()->addMonth()->toDateString()), $tenant->timezone ?? 'Europe/Zurich')->startOfDay();
            $r = app(Abo::class)->umziehen($user, $offer, $kunde, $ab, $this->option('betrag') !== null ? (float) $this->option('betrag') : null, strtoupper((string) $this->option('waehrung')), (bool) $this->option('trocken'));
            $this->line(json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        });
    }
}
