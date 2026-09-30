<?php

namespace App\Console\Commands;

use App\Chat\Chat;
use App\Models\Entitlement;
use App\Models\Tenant;
use App\Models\Verkauf;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Shop\Buchhaltung;
use App\Shop\Verkaufen;
use App\Tenancy\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Zahlungsabgleich mit der Buchhaltung: php84 artisan buchhaltung:zahlungen [lea]
 * Offene Verkaeufe mit Rechnung werden nachgeschaut; meldet die Buchhaltung "bezahlt", wird der
 * Verkauf bezahlt gesetzt und ein wartender Zugang freigeschaltet. Laeuft stuendlich.
 */
class BuchhaltungZahlungen extends Command
{
    protected $signature = 'buchhaltung:zahlungen {tenant? : Kuerzel, sonst alle aktiven}';

    protected $description = 'Offene Rechnungen mit der Buchhaltung abgleichen';

    public function handle(CurrentTenant $current): int
    {
        $tenants = $this->argument('tenant') ? Tenant::where('slug', $this->argument('tenant'))->get() : Tenant::where('is_active', true)->get();
        foreach ($tenants as $tenant) {
            $current->run($tenant, function () use ($tenant) {
                $this->ablaufWarnen($tenant);
                $b = Buchhaltung::fuer($tenant);
                if (! $b || ! $b->verbunden()) {
                    return;
                }
                // Rechnungen nachholen, die bei einer Stoerung nicht angelegt wurden
                foreach (Verkauf::whereNull('rechnung_id')->where('settings->rechnung_fehler', '!=', '')->where('status', '!=', 'storniert')->with(['user', 'offer'])->get() as $v) {
                    if (app(Verkaufen::class)->rechnungNachholen($v)) {
                        $this->line("{$tenant->slug}: {$v->user?->name}, {$v->title}: Rechnung nachgeholt ({$v->rechnung_nr})");
                    }
                }
                $offen = Verkauf::where('status', 'offen')->whereNotNull('rechnung_id')->with(['user', 'offer', 'entitlement'])->get();
                foreach ($offen as $v) {
                    try {
                        $status = $b->rechnungStatus((int) $v->rechnung_id);
                    } catch (RuntimeException $e) {
                        $this->warn("{$tenant->slug}: Rechnung {$v->rechnung_nr}: ".$e->getMessage());

                        continue;
                    }
                    if ($status === 'bezahlt') {
                        app(Verkaufen::class)->bezahlt($v);
                        $this->line("{$tenant->slug}: {$v->user?->name}, {$v->title}: bezahlt");
                    } elseif ($status === 'storniert') {
                        $v->forceFill(['status' => 'storniert'])->save();
                        $this->line("{$tenant->slug}: {$v->user?->name}, {$v->title}: storniert");
                    }
                }
            });
        }

        return self::SUCCESS;
    }

    /** Sieben Tage vor Ablauf eines Zugangs: Hinweis an die Person und ans Team, je Zugang einmal. */
    protected function ablaufWarnen(Tenant $tenant): void
    {
        $bald = Entitlement::query()->current()->whereNotNull('ends_at')->whereBetween('ends_at', [now()->addDays(6), now()->addDays(7)])->with(['user', 'offer'])->get();
        foreach ($bald as $e) {
            if (! $e->user || ! Cache::add('ablauf-'.$tenant->id.'-'.$e->id, 1, now()->addDays(10))) {
                continue;
            }
            $wann = $e->ends_at->translatedFormat('j. F Y');
            app(Notifier::class)->send([$e->user], new Nachricht(
                titel: 'Dein Zugang läuft am '.$wann.' ab', text: ($e->offer?->title ?? 'Dein Zugang').' ist noch bis '.$wann.' offen. Wenn du weitermachen möchtest, melde dich einfach.',
                url: route('kurse.index'), anlass: 'system', tag: 'ablauf-'.$e->id, mailImmer: true, knopf: 'Zu deinen Kursen',
            ));
            app(Notifier::class)->send(app(Chat::class)->teamIds(), new Nachricht(
                titel: 'Zugang läuft ab: '.$e->user->name, text: ($e->offer?->title ?? 'Zugang').' bis '.$wann.'. Verlängern oder ein neues Angebot machen?',
                url: ($m = $e->user->membershipIn()) ? route('coachees.show', $m) : url('/coach'), anlass: 'system', tag: 'ablauf-team-'.$e->id,
            ));
            $this->line("{$tenant->slug}: {$e->user->name}: Zugang läuft am {$wann} ab, gewarnt");
        }
    }
}
