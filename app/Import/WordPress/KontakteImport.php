<?php

namespace App\Import\WordPress;

use App\Models\Kontakt;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Abonnentinnen aus Mailster uebernehmen: mailster_subscribers (Status, Anmeldung, Bestaetigung, IP, Referrer),
 * mailster_subscriber_fields (Vorname, Nachname), Listen werden zu Tags (Slug der Liste).
 * Mailster-Status: 0 wartet, 1 bestaetigt, 2 abgemeldet, 3 hard bounce, 4 soft bounce, 5 Spam, 6 Fehler.
 */
class KontakteImport
{
    /** Mailster-Listen, die in der App unter einem anderen Tag laufen (die Hauptliste heisst in der App schlicht "newsletter"). */
    public const TAG_MAP = ['newsletter-alle' => 'newsletter'];

    public function __construct(protected Tenant $tenant, protected WordPressSource $wp, protected bool $dryRun = false) {}

    public function run(?callable $report = null): array
    {
        $stats = ['gelesen' => 0, 'angelegt' => 0, 'aktualisiert' => 0, 'abgemeldet' => 0, 'listen' => 0, 'hinweise' => []];
        $db = $this->wp->db();
        if (! $this->wp->hasTable('mailster_subscribers')) {
            $stats['hinweise'][] = 'Keine Mailster-Tabellen gefunden.';

            return $stats;
        }

        $listen = $db->table('mailster_lists')->get()->keyBy('ID');
        $stats['listen'] = $listen->count();
        $zuordnung = $db->table('mailster_lists_subscribers')->get()->groupBy('subscriber_id');
        $felder = $this->wp->hasTable('mailster_subscriber_fields')
            ? $db->table('mailster_subscriber_fields')->whereIn('meta_key', ['firstname', 'lastname'])->get()->groupBy('subscriber_id')
            : collect();

        foreach ($db->table('mailster_subscribers')->orderBy('ID')->cursor() as $s) {
            $stats['gelesen']++;
            $email = Str::lower(trim((string) $s->email));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $f = ($felder[$s->ID] ?? collect())->pluck('meta_value', 'meta_key');
            $name = trim(($f['firstname'] ?? '').' '.($f['lastname'] ?? '')) ?: null;
            $tags = ($zuordnung[$s->ID] ?? collect())->map(fn ($z) => $listen[$z->list_id] ?? null)->filter()->map(fn ($l) => self::TAG_MAP[Kontakt::tagSauber($l->slug ?: $l->name)] ?? Kontakt::tagSauber($l->slug ?: $l->name))->unique()->values()->all();
            $status = match ((int) $s->status) {
                1 => 'bestaetigt', 2 => 'abgemeldet', 0 => 'angemeldet', default => 'abgeprallt'
            };
            $report && $report("#{$s->ID} {$email}: {$status}, Tags ".implode(', ', $tags));
            if ($this->dryRun) {
                continue;
            }

            $k = Kontakt::firstOrNew(['email' => $email]);
            $neu = ! $k->exists;
            $k->name = $k->name ?: $name;
            $k->legacy_id = (string) $s->ID;
            $k->user_id ??= User::where('email', $email)->first()?->id;
            $k->tags = array_values(array_unique(array_merge($k->tags ?? [], $tags)));
            if ($neu || $k->status === 'angemeldet') {
                $k->status = $status;
                $k->bestaetigt_at = $status === 'bestaetigt' ? ($s->confirm ? Carbon::createFromTimestamp((int) $s->confirm) : now()) : null;
                $k->abgemeldet_at = $status === 'abgemeldet' ? now() : null;
            }
            $k->einwilligung = array_merge($k->einwilligung ?? [], array_filter([
                'herkunft' => 'mailster', 'zeit' => $s->signup ? Carbon::createFromTimestamp((int) $s->signup)->toIso8601String() : null,
                'ip' => $s->ip_signup ?? null, 'ip_bestaetigt' => $s->ip_confirm ?? null, 'referrer' => $s->referer ?? null,
            ]));
            $k->save();
            $stats[$neu ? 'angelegt' : 'aktualisiert']++;
            if ($status === 'abgemeldet') {
                $stats['abgemeldet']++;
            }
        }

        return $stats;
    }
}
