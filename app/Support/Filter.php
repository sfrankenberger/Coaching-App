<?php

namespace App\Support;

use App\Models\Reflection;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Die einheitliche Filterleiste (wie lea_el_filterleiste): Art, Zeit, Projekt, Kurs, Status und Suche
 * aus der Adresse lesen und auf Eintraege anwenden. Listen sind klein, darum wird in PHP gefiltert.
 */
class Filter
{
    public const ZEITEN = ['woche' => 'Diese Woche', 'monat' => 'Dieser Monat', '30' => 'Letzte 30 Tage'];

    public const STATUS = ['offen' => 'Offen', 'erledigt' => 'Erledigt', 'neu' => 'Mit Kommentaren'];

    public function __construct(
        public ?string $art = null,
        public ?string $zeit = null,
        public ?string $projekt = null,   // ID oder "ohne"
        public ?int $kurs = null,
        public ?string $st = null,
        public string $q = '',
    ) {}

    public static function aus(Request $r): self
    {
        $zeit = (string) $r->query('zeit', '');
        $st = (string) $r->query('st', '');

        return new self(
            art: filled($r->query('art')) ? (string) $r->query('art') : null,
            zeit: array_key_exists($zeit, self::ZEITEN) ? $zeit : null,
            projekt: filled($r->query('projekt')) ? (string) $r->query('projekt') : null,
            kurs: (int) $r->query('kurs', 0) ?: null,
            st: array_key_exists($st, self::STATUS) ? $st : null,
            q: mb_strtolower(trim((string) $r->query('q', ''))),
        );
    }

    public function aktiv(): bool
    {
        return $this->art || $this->zeit || $this->projekt || $this->kurs || $this->st || $this->q !== '';
    }

    /** Als Adresse-Parameter, z. B. fuer Links, die den Filter behalten. */
    public function query(array $mit = []): array
    {
        return array_filter(['art' => $this->art, 'zeit' => $this->zeit, 'projekt' => $this->projekt, 'kurs' => $this->kurs, 'st' => $this->st, 'q' => $this->q] + $mit, fn ($v) => $v !== null && $v !== '' && $v !== 0);
    }

    public function passt(Model $item, ?string $art = null): bool
    {
        if ($this->art && $art && $this->art !== $art) {
            return false;
        }
        if ($this->zeit) {
            $ts = $item->created_at;
            $ab = match ($this->zeit) {
                'woche' => now()->startOfWeek(),
                'monat' => now()->startOfMonth(),
                default => now()->subDays(30),
            };
            if (! $ts || $ts->lt($ab)) {
                return false;
            }
        }
        if ($this->projekt) {
            $hat = (int) ($item->project_id ?? 0);
            if ($this->projekt === 'ohne' ? $hat !== 0 : $hat !== (int) $this->projekt) {
                return false;
            }
        }
        if ($this->kurs && (int) ($item->program_id ?? 0) !== $this->kurs) {
            return false;
        }
        if ($this->st === 'offen' || $this->st === 'erledigt') {
            $fertig = ($item instanceof Task) ? $item->isDone() : false;
            if (($this->st === 'offen') === $fertig && $item instanceof Task) {
                return false;
            }
        }
        if ($this->st === 'neu') {
            $n = $item->relationLoaded('comments') ? $item->comments->count() : (method_exists($item, 'comments') ? $item->comments()->count() : 0);
            if ($n === 0) {
                return false;
            }
        }
        if ($this->q !== '') {
            $text = $item instanceof Reflection
                ? implode(' ', [$item->week_label, $item->went_well, $item->challenges, $item->focus, $item->addendum])
                : implode(' ', [$item->title ?? '', $item->body ?? '']);
            if (! str_contains(mb_strtolower($text), $this->q)) {
                return false;
            }
        }

        return true;
    }

    /** "Alles wird gezeigt" oder was gerade eingegrenzt ist, fuer die eingeklappte Leiste. */
    public function zusammenfassung(array $arten = [], iterable $projekte = [], array $kurse = []): string
    {
        if (! $this->aktiv()) {
            return 'Alles wird gezeigt';
        }
        $teile = [];
        if ($this->art && isset($arten[$this->art])) {
            $teile[] = is_array($arten[$this->art]) ? $arten[$this->art][0] : $arten[$this->art];
        }
        if ($this->zeit) {
            $teile[] = self::ZEITEN[$this->zeit];
        }
        if ($this->projekt) {
            $name = 'ohne Projekt';
            foreach ($projekte as $p) {
                if ((string) $p->id === $this->projekt) {
                    $name = $p->name;
                }
            }
            $teile[] = $name;
        }
        if ($this->kurs && isset($kurse[$this->kurs])) {
            $teile[] = $kurse[$this->kurs];
        }
        if ($this->st) {
            $teile[] = self::STATUS[$this->st];
        }
        if ($this->q !== '') {
            $teile[] = '«'.$this->q.'»';
        }

        return 'Gefiltert: '.implode(', ', $teile);
    }
}
