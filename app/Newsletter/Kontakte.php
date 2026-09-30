<?php

namespace App\Newsletter;

use App\Mail\KontaktBestaetigenMail;
use App\Models\Kontakt;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Kontakte anmelden (Double-Opt-in mit signiertem Link, 30 Tage), bestaetigen, abmelden, taggen.
 * Ein Tag kann eine Serie ausloesen. Mitglieder bekommen automatisch einen bestaetigten Kontakt.
 */
class Kontakte
{
    public const DOI_TAGE = 30;

    public function __construct(protected CurrentTenant $current, protected Serien $serien) {}

    /**
     * Anmeldung aus Formular oder API. Mit $bestaetigen = true kommt erst die Bestaetigungsmail (Newsletter),
     * sonst ist der Kontakt sofort bestaetigt (Veranstaltung, Freebie mit direktem Versand).
     */
    public function anmelden(string $email, ?string $name, array $tags, array $einwilligung = [], bool $bestaetigen = true): Kontakt
    {
        $email = Str::lower(trim($email));
        $k = Kontakt::firstOrNew(['email' => $email]);
        $neu = ! $k->exists;
        $k->name = filled($name) ? trim($name) : $k->name;
        $k->user_id ??= User::where('email', $email)->first()?->id;
        $k->einwilligung = array_merge($k->einwilligung ?? [], array_filter($einwilligung) + ['zeit' => now()->toIso8601String()]);
        if ($k->status === 'abgemeldet' || $neu || $k->status === 'abgeprallt') {
            $k->status = $bestaetigen ? 'angemeldet' : 'bestaetigt';
            $k->abgemeldet_at = null;
            $k->bestaetigt_at = $bestaetigen ? null : now();
        } elseif (! $bestaetigen && $k->status === 'angemeldet') {
            $k->status = 'bestaetigt';
            $k->bestaetigt_at = now();
        }
        $k->save();

        if ($k->status === 'angemeldet') {
            $k->forceFill(['settings' => array_merge($k->settings ?? [], ['tags_offen' => array_values(array_unique(array_merge($k->settings['tags_offen'] ?? [], array_map([Kontakt::class, 'tagSauber'], $tags))))])])->save();
            $this->bestaetigungSchicken($k);
        } else {
            $this->taggen($k, $tags);
        }

        return $k;
    }

    public function bestaetigungSchicken(Kontakt $k): void
    {
        $url = URL::temporarySignedRoute('newsletter.bestaetigen', now()->addDays(self::DOI_TAGE), ['kontakt' => $k->id, 't' => substr($k->token, 0, 12)]);
        Mail::to($k->email, $k->name)->send(new KontaktBestaetigenMail($k, $url));
    }

    /** Klick auf den Bestaetigungslink: bestaetigt, offene Tags werden gesetzt (und loesen Serien aus). */
    public function bestaetigen(Kontakt $k): Kontakt
    {
        if ($k->status !== 'bestaetigt') {
            $k->forceFill(['status' => 'bestaetigt', 'bestaetigt_at' => now(), 'abgemeldet_at' => null])->save();
        }
        $offen = (array) ($k->settings['tags_offen'] ?? []);
        if ($offen) {
            $k->forceFill(['settings' => array_merge($k->settings ?? [], ['tags_offen' => []])])->save();
            $this->taggen($k, $offen);
        }

        return $k;
    }

    public function abmelden(Kontakt $k, string $grund = 'link'): Kontakt
    {
        $k->forceFill(['status' => 'abgemeldet', 'abgemeldet_at' => now(), 'settings' => array_merge($k->settings ?? [], ['abmeldung' => $grund])])->save();

        return $k;
    }

    /** Tags dazu; neue Tags loesen ihre Serien aus. */
    public function taggen(Kontakt $k, array $tags): Kontakt
    {
        $tags = array_values(array_unique(array_filter(array_map([Kontakt::class, 'tagSauber'], $tags))));
        $vorher = $k->tags ?? [];
        $neu = array_values(array_diff($tags, $vorher));
        if ($neu) {
            $k->forceFill(['tags' => array_values(array_unique(array_merge($vorher, $neu)))])->save();
            if ($k->istBestaetigt()) {
                foreach ($neu as $t) {
                    $this->serien->ausloesen($k, $t);
                }
            }
        }

        return $k;
    }

    public function enttaggen(Kontakt $k, array $tags): Kontakt
    {
        $tags = array_map([Kontakt::class, 'tagSauber'], $tags);
        $k->forceFill(['tags' => array_values(array_diff($k->tags ?? [], $tags))])->save();

        return $k;
    }

    /** Mitglied als bestaetigten Kontakt fuehren (Kundin, Angebot als Tag). Keine Bestaetigungsmail: die Person ist bekannt. */
    public function ausMitglied(User $user, array $tags = []): Kontakt
    {
        $k = Kontakt::firstOrNew(['email' => Str::lower($user->email)]);
        $neu = ! $k->exists;
        $k->name = $k->name ?: $user->name;
        $k->user_id = $user->id;
        if ($neu || $k->status === 'angemeldet') {
            $k->status = 'bestaetigt';
            $k->bestaetigt_at = now();
            $k->einwilligung = array_merge($k->einwilligung ?? [], ['herkunft' => 'mitglied', 'zeit' => now()->toIso8601String()]);
        }
        $k->save();

        return $this->taggen($k, array_merge(['kundin'], $tags));
    }

    /** Alle Tags, die im Mandanten vorkommen, mit Anzahl. */
    public function alleTags(): array
    {
        $out = [];
        foreach (Kontakt::query()->whereNotNull('tags')->pluck('tags') as $tags) {
            foreach ((array) $tags as $t) {
                $out[$t] = ($out[$t] ?? 0) + 1;
            }
        }
        ksort($out);

        return $out;
    }
}
