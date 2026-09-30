<?php

namespace App\Programs;

use App\Chat\Chat;
use App\Models\Event;
use App\Models\JournalEntry;
use App\Models\Note;
use App\Models\Projekt;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Meine Zeitleiste (wie lea-timeline): ein Strom aus allem, was die Person betrifft. Aufgaben, Notizen,
 * Reflexionen, Eintraege aus der Begleitung, Termine und Aufzeichnungen, zurueckblickend bis heute,
 * mit Monaten und Wochen als Ueberschriften. Filter nach Art und Projekt.
 */
class Zeitleiste
{
    public const ARTEN = [
        'termin' => ['Termin', 'video'],
        'aufzeichnung' => ['Aufzeichnung', 'circle-play'],
        'aufgabe' => ['Aufgabe', 'list-check'],
        'notiz' => ['Notiz', 'feather'],
        'reflexion' => ['Reflexion', 'pen-to-square'],
        'journal' => ['Begleitung', 'book-open'],
    ];

    public function __construct(protected Begleitung $begleitung, protected Chat $chat) {}

    /** Die Punkte, neueste zuerst: ['zeit', 'art', 'item', 'projekt'] */
    public function punkte(User $user, ?string $art = null, ?Projekt $projekt = null, int $limit = 200): Collection
    {
        $team = $this->chat->teamIds();
        $p = collect();
        $eigen = fn ($q) => $q->where(fn ($w) => $w->where('user_id', $user->id)->orWhere(fn ($x) => $x->whereIn('user_id', $team)->whereIn('visibility', ['program', 'all'])))
            ->when($projekt, fn ($q) => $q->where('project_id', $projekt->id));

        if (! $art || $art === 'aufgabe') {
            foreach ($eigen(Task::query())->with(['projekt:id,name,farbe,icon', 'program:id,title'])->latest()->limit($limit)->get() as $t) {
                $p->push(['zeit' => $t->created_at, 'art' => 'aufgabe', 'item' => $t]);
            }
        }
        if (! $art || $art === 'notiz') {
            foreach ($eigen(Note::query())->with(['projekt:id,name,farbe,icon', 'program:id,title'])->latest()->limit($limit)->get() as $n) {
                $p->push(['zeit' => $n->created_at, 'art' => 'notiz', 'item' => $n]);
            }
        }
        if (! $art || $art === 'reflexion') {
            foreach ($eigen(Reflection::query())->with(['projekt:id,name,farbe,icon', 'program:id,title'])->latest()->limit($limit)->get() as $r) {
                $p->push(['zeit' => $r->created_at, 'art' => 'reflexion', 'item' => $r]);
            }
        }
        if (! $art || $art === 'journal') {
            foreach (JournalEntry::where('user_id', $user->id)->when($projekt, fn ($q) => $q->where('project_id', $projekt->id))->with('program:id,title')->latest()->limit($limit)->get() as $j) {
                $p->push(['zeit' => $j->created_at, 'art' => 'journal', 'item' => $j]);
            }
        }
        if (! $projekt && (! $art || in_array($art, ['termin', 'aufzeichnung'], true))) {
            $termine = $this->begleitung->eventsQuery($user)->whereNotIn('type', Event::ALL_DAY_TYPES)->where('starts_at', '<=', now())
                ->with(['program:id,title', 'attendees' => fn ($a) => $a->where('user_id', $user->id)])->orderByDesc('starts_at')->limit($limit)->get();
            foreach ($termine as $e) {
                $a = $e->hasRecording() ? 'aufzeichnung' : 'termin';
                if (! $art || $art === $a) {
                    $p->push(['zeit' => $e->starts_at, 'art' => $a, 'item' => $e]);
                }
            }
        }

        return $p->filter(fn ($x) => $x['zeit'] && $x['zeit']->lte(now()))->sortByDesc('zeit')->take($limit)->values();
    }

    /** Als Naechstes: der naechste Termin. */
    public function naechster(User $user): ?Event
    {
        return $this->begleitung->eventsQuery($user)->whereNotIn('type', Event::ALL_DAY_TYPES)->where('starts_at', '>', now())->orderBy('starts_at')->first();
    }

    /** Ueberschrift der Woche: "Diese Woche · 28. bis 4. Oktober". */
    public static function wocheLabel(Carbon $zeit): string
    {
        $start = $zeit->copy()->startOfWeek();
        $ende = $start->copy()->addDays(6);
        $diese = now()->startOfWeek();
        $vor = match (true) {
            $start->isSameDay($diese) => 'Diese Woche · ',
            $start->isSameDay($diese->copy()->subWeek()) => 'Letzte Woche · ',
            default => '',
        };

        return $vor.$start->translatedFormat('j.').' bis '.$ende->translatedFormat('j. F');
    }
}
