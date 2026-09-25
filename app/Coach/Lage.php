<?php

namespace App\Coach;

use App\Chat\Chat;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Task;
use App\Models\User;
use App\Programs\ProgramAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lage einer Person aus Sicht der Coachin (wie lea-coachees und lea-coach-kurs):
 * wartet sie auf Antwort, wann war sie zuletzt da, was steht an, was ist offen,
 * wie viele 1:1-Sitzungen hat sie noch. Daraus die Ampel mit Grund.
 */
class Lage
{
    public function __construct(protected Chat $chat, protected ProgramAccess $access) {}

    /** Alle begleiteten Personen (Mitglieder und Klientinnen) mit Lage, dringendste zuerst. */
    public function alle(): Collection
    {
        $team = $this->chat->teamIds();
        $wartet = $this->wartende($team);

        return Membership::query()->where('status', 'active')->whereIn('role', ['member', 'client'])->with('user')->get()
            ->filter(fn (Membership $m) => $m->user)
            ->map(fn (Membership $m) => $this->fuer($m, $wartet))
            ->sortBy([['stufe', 'desc'], ['seit', 'asc']])->values();
    }

    /** Lage einer Mitgliedschaft. $wartet: user_id => Zeit der aeltesten unbeantworteten Nachricht. */
    public function fuer(Membership $m, ?Collection $wartet = null): array
    {
        $user = $m->user;
        $wartet ??= $this->wartende($this->chat->teamIds());
        $wartetSeit = $wartet->get($user->id);
        $naechster = Event::query()->where('is_published', true)->where('starts_at', '>=', now())
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere(fn ($g) => $g->whereNull('user_id')->whereIn('program_id', ProgramMember::where('user_id', $user->id)->pluck('program_id'))))
            ->orderBy('starts_at')->first();
        $coachAufgaben = Task::where('user_id', $user->id)->whereNotNull('assigned_by');
        $aufgabenGesamt = (clone $coachAufgaben)->count();
        $aufgabenFertig = (clone $coachAufgaben)->whereNotNull('done_at')->count();
        $ueberfaellig = Task::where('user_id', $user->id)->whereNull('done_at')->whereNotNull('due_at')->where('due_at', '<', now()->startOfDay())->count();
        $verpasst = $this->verpassteCalls($user);
        $tage = $m->last_seen_at ? (int) $m->last_seen_at->diffInDays(now()) : null;

        // Ampel: rot vor gelb vor gruen, der erste Grund zaehlt
        [$stufe, $grund, $entwurf] = match (true) {
            (bool) $wartetSeit => [3, 'wartet auf deine Antwort', null],
            $tage === null && ($m->joined_at ?? $m->created_at)?->lt(now()->subDays(3)) => [3, 'war noch nie da', 'still'],
            $tage !== null && $tage >= 14 => [3, "seit {$tage} Tagen nicht da", 'still'],
            $verpasst >= 2 => [2, "{$verpasst} Calls verpasst", 'call'],
            $ueberfaellig >= 2 => [2, "{$ueberfaellig} Aufgaben überfällig", 'aufgaben'],
            $tage !== null && $tage >= 7 => [2, "seit {$tage} Tagen nicht da", 'still'],
            default => [1, 'alles im Fluss', null],
        };

        return [
            'membership' => $m,
            'user' => $user,
            'wartet' => $wartetSeit,
            'zuletzt' => $m->last_seen_at,
            'naechster' => $naechster,
            'aufgaben' => [$aufgabenFertig, $aufgabenGesamt],
            'ueberfaellig' => $ueberfaellig,
            'verpasst' => $verpasst,
            'kontingent' => $this->kontingent($user),
            'stufe' => $stufe,
            'farbe' => [1 => 'gruen', 2 => 'gelb', 3 => 'rot'][$stufe],
            'grund' => $grund,
            'entwurf' => $entwurf,
            'seit' => $wartetSeit?->getTimestamp() ?? PHP_INT_MAX,
        ];
    }

    /** Wer hat im 1:1 zuletzt geschrieben und hat noch keine Antwort? user_id => Zeitpunkt */
    public function wartende(?Collection $team = null): Collection
    {
        $team ??= $this->chat->teamIds();
        $out = collect();
        foreach (Conversation::where('type', 'direct')->whereNotNull('last_message_at')->where('last_message_at', '>', now()->subDays(60))->get() as $c) {
            $letzte = Message::where('conversation_id', $c->id)->latest('id')->first();
            if (! $letzte || $letzte->user_id !== $c->user_id || $team->contains($letzte->user_id)) {
                continue;
            }
            // Aelteste Nachricht seit der letzten Antwort aus dem Team
            $antwort = Message::where('conversation_id', $c->id)->whereIn('user_id', $team)->max('id') ?? 0;
            $out[$c->user_id] = Message::where('conversation_id', $c->id)->where('id', '>', $antwort)->min('created_at');
        }

        return $out->map(fn ($t) => Carbon::parse($t));
    }

    /** Gruppencalls der letzten drei Wochen, bei denen die Person weder live noch per Aufzeichnung dabei war. */
    public function verpassteCalls(User $user): int
    {
        $programme = ProgramMember::where('user_id', $user->id)->pluck('program_id');
        $calls = Event::whereIn('program_id', $programme)->whereNull('user_id')->where('is_published', true)
            ->whereNotIn('type', Event::ALL_DAY_TYPES)->whereBetween('starts_at', [now()->subWeeks(3), now()->subDay()])->pluck('id');
        if ($calls->isEmpty()) {
            return 0;
        }
        $dabei = EventAttendee::where('user_id', $user->id)->whereIn('event_id', $calls)->whereIn('status', ['attended', 'watched'])->count();

        return max(0, $calls->count() - $dabei);
    }

    /**
     * 1:1-Kontingent: Sitzungen aus programs.settings.sitzungen_gesamt (plus Zusatz aus
     * program_members.settings.sitzungen_extra) gegen vergangene und geplante 1:1-Termine.
     */
    public function kontingent(User $user): ?array
    {
        $mitglied = ProgramMember::where('user_id', $user->id)->pluck('settings', 'program_id');
        $programme = Program::where('type', 'one_on_one')->whereIn('id', $mitglied->keys())->get();
        $gesamt = 0;
        foreach ($programme as $p) {
            $gesamt += (int) ($p->settings['sitzungen_gesamt'] ?? 0) + (int) (($mitglied[$p->id] ?? [])['sitzungen_extra'] ?? 0);
        }
        if ($gesamt === 0) {
            return null;
        }
        $termine = Event::where('user_id', $user->id)->where('is_published', true);
        $gehabt = (clone $termine)->where('starts_at', '<', now())->count();
        $geplant = (clone $termine)->where('starts_at', '>=', now())->count();

        return ['gesamt' => $gesamt, 'gehabt' => $gehabt, 'geplant' => $geplant, 'offen' => max(0, $gesamt - $gehabt - $geplant)];
    }

    /** Vorbereiteter Satz fuer "kurz nachfragen". */
    public static function entwurf(?string $art, string $vorname): string
    {
        return match ($art) {
            'still' => "Hallo {$vorname}, ich habe länger nichts von dir gehört und wollte kurz nachfragen: Wie geht es dir?",
            'call' => "Hallo {$vorname}, ich habe dich in den letzten Calls vermisst. Magst du die Aufzeichnung anschauen? Und sag gern, wenn dir etwas im Weg steht.",
            'aufgaben' => "Hallo {$vorname}, wie kommst du mit deinen Aufgaben voran? Wenn etwas hakt, schreib mir gern.",
            default => "Hallo {$vorname}, ",
        };
    }
}
