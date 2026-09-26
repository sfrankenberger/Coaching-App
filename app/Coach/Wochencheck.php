<?php

namespace App\Coach;

use App\Chat\Chat;
use App\Filament\Coach\Resources\Events\EventResource;
use App\Filament\Coach\Resources\Programs\ProgramResource;
use App\Models\Event;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\ProgramStep;
use App\Models\Question;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Collection;

/**
 * Wochenpruefung fuers Team (wie lea-wochencheck): Fuer jedes Programm mit Wochentaktung die
 * aktuelle und die naechste Woche pruefen. Dazu frei einstellbare Haken je Kalenderwoche
 * (tenants.settings.wochencheck.haken = {schluessel: Text}), abgehakt in settings.wochencheck.erledigt.
 */
class Wochencheck
{
    public function __construct(protected CurrentTenant $current, protected Chat $chat) {}

    public function kw(): string
    {
        return now($this->current->get()?->timezone ?: config('app.timezone'))->format('o-W');
    }

    /** @return Collection<int, array{program: Program, wochen: array}> */
    public function programme(): Collection
    {
        return Program::where('pacing', 'weekly')->where('is_published', true)->with('steps')->orderBy('position')->get()
            ->map(function (Program $p) {
                $steps = $p->steps->values();
                if ($steps->isEmpty()) {
                    return null;
                }
                $i = $steps->search(fn (ProgramStep $s) => $s === $steps->filter(fn ($x) => $x->isUnlocked($p))->last());
                $jetzt = $i === false ? null : $steps[$i];
                $naechste = $i === false ? $steps->first() : ($steps[$i + 1] ?? null);
                // Kurs vorbei: letzte Woche liegt mehr als zwei Wochen zurueck
                if ($jetzt && ! $naechste && $jetzt->unlocks_at && $jetzt->unlocks_at->lt(now()->subWeeks(2))) {
                    return null;
                }
                $wochen = [];
                if ($jetzt) {
                    $wochen[] = ['titel' => 'Diese Woche: '.$jetzt->title, 'zeilen' => $this->pruefen($p, $jetzt, true)];
                }
                if ($naechste) {
                    $wochen[] = ['titel' => 'Nächste Woche: '.$naechste->title, 'zeilen' => $this->pruefen($p, $naechste, false)];
                }

                return ['program' => $p, 'wochen' => $wochen];
            })->filter()->values();
    }

    /** Zeilen: [ok (true|false|null=Hinweis), text, link|null, linktext|null] */
    public function pruefen(Program $p, ProgramStep $s, bool $jetzt): array
    {
        $z = [];
        $termine = Event::where('step_id', $s->id)->where('is_published', true)->orderBy('starts_at')->get();
        $call = $termine->firstWhere('type', 'group_call');
        if ($call) {
            $z[] = [(bool) $call->zoom_url, 'Call '.$call->starts_at->translatedFormat('l, j. F, H:i').' Uhr: '.($call->zoom_url ? 'Zoom-Link steht' : 'Zoom-Link fehlt'), EventResource::getUrl('edit', ['record' => $call]), 'Termin'];
        } else {
            $z[] = [false, 'Kein Call in dieser Woche', EventResource::getUrl('create'), 'Termin anlegen'];
        }
        $z[] = [filled(strip_tags((string) $s->summary)), filled(strip_tags((string) $s->summary)) ? 'Einleitung ist eingetragen' : 'Einleitung fehlt', ProgramResource::getUrl('edit', ['record' => $p]), 'Programm'];

        $aufgaben = Task::where('step_id', $s->id)->whereNotNull('assigned_by')->get()->unique('title');
        $ohneTag = $aufgaben->whereNull('due_at')->count();
        $z[] = [$aufgaben->isNotEmpty(), $aufgaben->isEmpty() ? 'Noch keine Aufgaben von der Coachin'
            : $aufgaben->count().' Aufgabe'.($aufgaben->count() === 1 ? '' : 'n').($ohneTag ? ", davon {$ohneTag} ohne Datum (keine Erinnerung)" : ', alle mit Datum'), null, null];
        if (! $termine->firstWhere('type', 'question_day')) {
            $z[] = [null, 'Kein Fragentag in dieser Woche', null, null];
        }
        if (! $termine->firstWhere('type', 'reflection_day')) {
            $z[] = [false, 'Kein Reflexionstermin in dieser Woche', EventResource::getUrl('create'), 'Termin anlegen'];
        }

        if ($jetzt) {
            if ($call && $call->starts_at->copy()->addHours(2)->isPast()) {
                $z[] = match (true) {
                    ! $call->hasRecording() => [false, 'Aufzeichnung fehlt noch', EventResource::getUrl('edit', ['record' => $call]), 'Termin'],
                    blank($call->summary) => [false, 'Aufzeichnung da, Zusammenfassung fehlt', EventResource::getUrl('edit', ['record' => $call]), 'Termin'],
                    default => [true, 'Aufzeichnung und Zusammenfassung sind da', null, null],
                };
            }
            $team = $this->chat->teamIds();
            $leute = ProgramMember::where('program_id', $p->id)->where('role_in_program', '!=', 'coach')->whereNotIn('user_id', $team)->pluck('user_id');
            if ($leute->isNotEmpty() && $s->unlocks_at) {
                $bis = $s->unlocks_at->copy()->addWeek();
                $mit = Reflection::whereIn('user_id', $leute)->whereBetween('created_at', [$s->unlocks_at, $bis])->pluck('user_id')->unique();
                $ohne = User::whereIn('id', $leute->diff($mit))->get()->map->vorname()->sort()->values();
                $refl = $termine->firstWhere('type', 'reflection_day');
                $z[] = [$refl && $refl->starts_at->isPast() ? $ohne->isEmpty() : null,
                    'Reflexionen: '.$mit->count().' von '.$leute->count().($ohne->isNotEmpty() ? ' (noch offen: '.$ohne->implode(', ').')' : ''), null, null];
            }
            $offen = Question::where('program_id', $p->id)->where('status', 'offen')
                ->whereDoesntHave('answers', fn ($q) => $q->whereIn('user_id', $team))->count();
            $z[] = [$offen === 0, $offen ? ($offen === 1 ? 'Eine offene Frage' : "{$offen} offene Fragen").' ohne Antwort' : 'Keine offenen Fragen', $offen ? route('kurse.fragen', [$p, 'f' => 'offen']) : null, 'Fragen'];
        }

        return $z;
    }

    /** Frei einstellbare Haken dieser Kalenderwoche: [schluessel => [text, erledigt_von|null]] */
    public function haken(): array
    {
        $tenant = $this->current->get();
        $erledigt = (array) ($tenant?->setting('wochencheck.erledigt.'.$this->kw()) ?? []);

        return collect((array) ($tenant?->setting('wochencheck.haken') ?? []))
            ->mapWithKeys(fn ($text, $k) => [$k => [$text, $erledigt[$k] ?? null]])->all();
    }

    public function abhaken(string $key, bool $an, User $von): void
    {
        $tenant = $this->current->getOrFail();
        abort_unless(array_key_exists($key, (array) $tenant->setting('wochencheck.haken', [])), 404);
        $settings = $tenant->settings ?? [];
        $alle = (array) ($settings['wochencheck']['erledigt'] ?? []);
        $alle[$this->kw()][$key] = $an ? $von->id : null;
        krsort($alle);
        $settings['wochencheck']['erledigt'] = array_slice($alle, 0, 8, true);
        $tenant->forceFill(['settings' => $settings])->save();
    }
}
