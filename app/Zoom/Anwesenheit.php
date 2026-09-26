<?php

namespace App\Zoom;

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Membership;
use App\Models\ProgramMember;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Zoom-Anwesenheit (wie novamira-zoom-anwesenheit und lea-zoom): nach dem Call die Teilnehmerliste
 * holen, Personen ueber Mail, Namen, Vor- oder Nachnamen zuordnen, Gastgeberin und Kurzbesuche
 * auslassen, dann "live dabei". Was die Person selbst gesagt hat, wird nicht ueberschrieben.
 *
 * tenants.settings.zoom: min_minutes (10), tolerance_minutes (120), hosts ("Name, mail@..."),
 * name_match (true), days (14), attendance (true).
 */
class Anwesenheit
{
    public const ARTEN = ['group_call', 'one_on_one', 'qa', 'webinar'];

    public function __construct(protected CurrentTenant $current, protected Zoom $zoom) {}

    protected function opt(string $key, mixed $standard): mixed
    {
        return $this->current->get()?->setting('zoom.'.$key, $standard) ?? $standard;
    }

    /** Vergangene Termine mit Zoom-Link, die noch nicht abgeglichen sind. */
    public function termine(): Collection
    {
        return Event::where('is_published', true)->whereIn('type', self::ARTEN)->whereNotNull('zoom_url')
            ->whereBetween('starts_at', [now()->subDays((int) $this->opt('days', 14)), now()->subHour()])
            ->orderByDesc('starts_at')->get()
            ->filter(fn (Event $e) => Zoom::nummer($e->zoom_url) && empty($e->settings['zoom']['abgeglichen']))->values();
    }

    public function lauf(): array
    {
        return $this->termine()->map(fn (Event $e) => $this->abgleich($e))->all();
    }

    /** Wer gehoert zu diesem Termin: 1:1 die Person, sonst alle im Programm (ohne Team). */
    public function kandidaten(Event $e): Collection
    {
        $team = Membership::whereIn('role', ['owner', 'team'])->pluck('user_id');
        $ids = $e->user_id ? collect([$e->user_id]) : ($e->program_id ? ProgramMember::where('program_id', $e->program_id)->pluck('user_id') : collect());

        return User::whereIn('id', $ids->diff($team))->get();
    }

    public function abgleich(Event $e, bool $trocken = false): array
    {
        $b = ['termin' => $e->id, 'titel' => $e->title, 'gesetzt' => [], 'unsicher' => [], 'uebersprungen' => [], 'gastgeber' => [], 'fremd' => [], 'fehler' => null];
        try {
            $nummer = Zoom::nummer($e->zoom_url);
            $sitzung = $this->sitzung($nummer, $e->starts_at);
            if (! $sitzung) {
                $b['fehler'] = 'Keine Zoom-Sitzung zu dieser Zeit gefunden.';

                return $b;
            }
            $leute = $this->zoom->teilnehmer($sitzung['uuid']);
        } catch (Throwable $x) {
            $b['fehler'] = $x->getMessage();

            return $b;
        }

        $kandidaten = $this->kandidaten($e);
        $v = $this->verzeichnis($kandidaten);
        $mindest = (int) $this->opt('min_minutes', 10) * 60;
        $namenErlaubt = (bool) $this->opt('name_match', true);
        $liste = [];
        foreach ($leute as $l) {
            $min = (int) round($l['sekunden'] / 60);
            $zeile = $l['name'].' ('.$min.' Min)';
            if ($this->istGastgeber($l)) {
                $b['gastgeber'][] = $zeile;

                continue;
            }
            [$uid, $wie] = $this->zuordnen($l, $v);
            $liste[] = ['name' => $l['name'], 'mail' => $l['mail'], 'minuten' => $min, 'person' => $uid, 'wie' => $wie];
            $person = $uid ? $kandidaten->firstWhere('id', $uid) : null;
            $zeile .= $person && $person->name !== $l['name'] ? ' = '.$person->name : '';
            if (! $uid) {
                $b['fremd'][] = $zeile;

                continue;
            }
            if ($wie && ! $namenErlaubt) {
                $b['fremd'][] = $zeile.' ('.$wie.' erkannt, Namenssuche ist aus)';

                continue;
            }
            if ($l['sekunden'] < $mindest) {
                $b['uebersprungen'][] = $zeile.': zu kurz dabei';

                continue;
            }
            $a = EventAttendee::where('event_id', $e->id)->where('user_id', $uid)->first();
            if ($a && $a->status === 'declined') {
                $b['uebersprungen'][] = $zeile.': hatte abgesagt, nicht überschrieben';

                continue;
            }
            if ($a && in_array($a->status, ['attended', 'watched'], true)) {
                $b['uebersprungen'][] = $zeile.': war schon erfasst';

                continue;
            }
            if (! $trocken) {
                EventAttendee::updateOrCreate(['event_id' => $e->id, 'user_id' => $uid], ['status' => 'attended', 'attended_at' => $e->starts_at]);
                // Beim naechsten Mal ohne Raten
                if ($wie && ($m = Membership::where('user_id', $uid)->first()) && ! $m->setting('zoom_name')) {
                    $m->forceFill(['settings' => array_merge($m->settings ?? [], ['zoom_name' => $l['name']])])->save();
                }
            }
            $b[$wie ? 'unsicher' : 'gesetzt'][] = $zeile.($wie ? ' ('.$wie.')' : '');
        }
        if (! $trocken) {
            $e->forceFill(['settings' => array_merge($e->settings ?? [], ['zoom' => ['abgeglichen' => now()->toIso8601String(), 'liste' => $liste, 'bericht' => $b]])])->saveQuietly();
        }

        return $b;
    }

    /** Die Sitzung des Raums, die zeitlich zum Termin passt. */
    protected function sitzung(string $nummer, Carbon $start): ?array
    {
        $alle = $this->zoom->sitzungen($nummer);
        $beste = null;
        $abstand = max(1, (int) $this->opt('tolerance_minutes', 120)) * 60;
        foreach ($alle as $m) {
            if (empty($m['start_time'])) {
                continue;
            }
            $d = abs(Carbon::parse($m['start_time'])->getTimestamp() - $start->getTimestamp());
            if ($d <= $abstand) {
                $abstand = $d;
                $beste = $m;
            }
        }

        // Ein eigenes Meeting je Termin lief nur einmal: dann passt es auch bei verschobener Zeit
        return $beste ?? (count($alle) === 1 ? $alle[0] : null);
    }

    public static function schlicht(?string $s): string
    {
        return trim(preg_replace('~[^a-z0-9]+~', ' ', Str::lower(Str::ascii((string) $s))));
    }

    protected function verzeichnis(Collection $kandidaten): array
    {
        $v = ['mail' => [], 'name' => [], 'vorname' => [], 'nachname' => [], 'teile' => []];
        $zoomNamen = Membership::whereIn('user_id', $kandidaten->pluck('id'))->get()->mapWithKeys(fn ($m) => [$m->user_id => $m->setting('zoom_name')]);
        foreach ($kandidaten as $u) {
            $v['mail'][strtolower((string) $u->email)] = $u->id;
            $woerter = explode(' ', self::schlicht($u->name));
            $vor = $woerter[0] ?? '';
            $nach = count($woerter) > 1 ? end($woerter) : '';
            $namen = array_filter([$u->name, trim($nach.' '.$vor), $zoomNamen[$u->id] ?? null]);
            $teile = [];
            foreach ($namen as $n) {
                $n = self::schlicht($n);
                if ($n === '') {
                    continue;
                }
                $v['name'][$n] = $u->id;
                foreach (explode(' ', $n) as $w) {
                    if (strlen($w) > 1) {
                        $teile[$w] = true;
                    }
                }
            }
            $v['teile'][$u->id] = array_keys($teile);
            foreach (['vorname' => $vor, 'nachname' => $nach] as $art => $w) {
                if ($w !== '') {
                    $v[$art][$w] = isset($v[$art][$w]) && $v[$art][$w] !== $u->id ? 0 : $u->id;   // 0 = mehrdeutig
                }
            }
        }

        return $v;
    }

    /** @return array{0: int, 1: string} Person (0 = unbekannt) und wie sicher ('' = sicher) */
    protected function zuordnen(array $p, array $v): array
    {
        if ($p['mail'] !== '' && isset($v['mail'][$p['mail']])) {
            return [$v['mail'][$p['mail']], ''];
        }
        $n = self::schlicht($p['name']);
        if ($n === '') {
            return [0, ''];
        }
        if (isset($v['name'][$n])) {
            return [$v['name'][$n], ''];
        }
        $woerter = array_values(array_filter(explode(' ', $n), fn ($w) => strlen($w) > 1));
        if (! $woerter) {
            return [0, ''];
        }
        $treffer = array_keys(array_filter($v['teile'], fn ($teile) => ! array_diff($woerter, $teile)));
        if (count($treffer) === 1) {
            return [(int) $treffer[0], count($woerter) === 1 ? 'über den Vornamen' : 'über Namensteile'];
        }
        if (count($woerter) === 1) {
            if (! empty($v['vorname'][$woerter[0]])) {
                return [$v['vorname'][$woerter[0]], 'über den Vornamen'];
            }
            if (! empty($v['nachname'][$woerter[0]])) {
                return [$v['nachname'][$woerter[0]], 'über den Nachnamen'];
            }
        }

        return [0, ''];
    }

    protected function istGastgeber(array $p): bool
    {
        foreach (preg_split('~[,;\n]+~', (string) $this->opt('hosts', '')) as $e) {
            $e = trim($e);
            if ($e === '') {
                continue;
            }
            if (str_contains($e, '@')) {
                if ($p['mail'] !== '' && strtolower($e) === $p['mail']) {
                    return true;
                }

                continue;
            }
            if (self::schlicht($e) !== '' && self::schlicht($e) === self::schlicht($p['name'])) {
                return true;
            }
        }

        return false;
    }
}
