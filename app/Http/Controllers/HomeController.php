<?php

namespace App\Http\Controllers;

use App\Chat\Chat;
use App\Coach\Ansicht;
use App\Coach\Arbeitsliste;
use App\Content\Inhalte;
use App\Models\Program;
use App\Models\ProgramStep;
use App\Models\Task;
use App\Notifications\Runden;
use App\Programs\Begleitung;
use App\Programs\Wochenaufgabe;
use App\Programs\ProgramAccess;
use App\Programs\ProgressTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Startseite: was heute dran ist, was neu ist, wo es weitergeht. */
class HomeController extends Controller
{
    public function __construct(
        protected ProgramAccess $access,
        protected ProgressTracker $progress,
        protected Begleitung $begleitung,
        protected Chat $chat,
        protected Inhalte $inhalte,
        protected Runden $runden,
    ) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $membership = $user->membershipIn();

        // Arbeitsplatz (Team): die Arbeitsliste statt der Teilnehmer-Startseite
        if (Ansicht::arbeitsplatz($user)) {
            return view('arbeitsplatz.heute', ['person' => $user] + app(Arbeitsliste::class)->fuer());
        }

        // Erste Anmeldung: kurz durch die Einfuehrung
        if ($membership && ! $membership->setting('onboarding_seen_at') && ! $user->canManageCurrentTenant() && ! $request->query('ohne')) {
            return redirect()->route('willkommen');
        }

        // "Was ist neu" bleibt stehen, bis die Person "Alles gesehen" tippt (hoechstens 14 Tage zurueck)
        $gesehen = $membership?->setting('neu_gesehen_at');
        $seit = max($gesehen ? Carbon::parse($gesehen) : now()->subDays(14), now()->subDays(14));
        $programs = $this->access->programsFor($user);

        // Aktuelle Woche in getakteten Programmen (wie im alten Bereich: nur die, Selbstlernkurse haben ihren Platz unter Meine Kurse)
        $wa = app(Wochenaufgabe::class);
        $weiter = $programs->map(function (Program $p) use ($user, $wa) {
            $stand = $this->progress->summary($user, $p);
            $erledigteUnits = $this->progress->completedUnitIds($user, $p);
            $step = null;
            $aufgaben = collect();
            if ($p->pacing === 'weekly') {
                $step = $p->steps->filter(fn (ProgramStep $s) => $s->isUnlocked($p))->sortByDesc('position')->first();
                if ($step) {
                    $wa->sicherstellen($user, $p, $step);
                    $aufgaben = $wa->derWoche($user, $step);
                }
            }

            $woche = $step ? $p->steps->sortBy('position')->values()->search(fn ($s) => $s->id === $step->id) + 1 : null;
            // Der Call dieser Woche (nicht der naechste im Kurs): vorher Termin, nachher Aufzeichnung, mit Stand
            $call = $step ? $this->begleitung->eventsQuery($user)->where('step_id', $step->id)->where('all_day', false)
                ->with(['attendees' => fn ($a) => $a->where('user_id', $user->id)])->orderBy('starts_at')->first() : null;
            $punkte = collect();
            if ($call) {
                $status = $call->attendees->first()?->status;
                $punkte->push(match (true) {
                    $call->isLive() => ['titel' => 'Call läuft gerade', 'erledigt' => false, 'icon' => 'video'],
                    $call->starts_at->isFuture() => ['titel' => 'Call '.$call->starts_at->translatedFormat('D, j. M, H:i').' Uhr', 'erledigt' => false, 'icon' => 'video'],
                    (bool) $call->recording_url => ['titel' => 'Aufzeichnung vom '.$call->starts_at->translatedFormat('l').' ansehen', 'erledigt' => in_array($status, ['attended', 'watched'], true), 'icon' => 'circle-play'],
                    default => ['titel' => 'Call vom '.$call->starts_at->translatedFormat('l'), 'erledigt' => $status === 'attended', 'icon' => 'video'],
                });
            }
            foreach ($p->units->where('step_id', $step?->id)->where('is_published', true)->sortBy('position') as $u) {
                $punkte->push(['titel' => $u->title, 'erledigt' => $erledigteUnits->contains($u->id), 'icon' => 'circle-play']);
            }
            foreach ($aufgaben as $t) {
                $punkte->push(['titel' => $t->title, 'erledigt' => $t->isDone() || $t->isSkipped(), 'ausgelassen' => $t->isSkipped(), 'icon' => (Task::KINDS[$t->kind] ?? Task::KINDS['haken'])[1]]);
            }

            return ['program' => $p, 'stand' => $stand, 'step' => $step, 'woche' => $woche, 'wochen' => $p->steps->count(), 'call' => $call, 'punkte' => $punkte,
                'aufgaben' => $punkte->count(), 'erledigt' => $punkte->where('erledigt', true)->count(), 'naechste' => $punkte->firstWhere('erledigt', false)['titel'] ?? null];
        })->filter(fn ($w) => $w['step']);
        $wochenSteps = $weiter->map(fn ($w) => $w['step']->id)->values();

        return view('home', [
            'person' => $user,
            'rolle' => $user->roleIn(),
            'neues' => $this->runden->neuesFuer($user, $seit, 20),
            'weiter' => $weiter,
            'termin' => $this->begleitung->eventsQuery($user)->upcoming()->with('program:id,title,slug')->limit(6)->get()->first(fn ($e) => ! $e->isPast()),
            'aufgaben' => Task::where('user_id', $user->id)->open()->where(fn ($q) => $q->whereNull('step_id')->orWhereNotIn('step_id', $wochenSteps))->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at')->limit(4)->get(),
            'offen' => Task::where('user_id', $user->id)->open()->where(fn ($q) => $q->whereNull('step_id')->orWhereNotIn('step_id', $wochenSteps))->count(),
            'ungelesen' => $this->chat->unreadFor($user),
            'impuls' => $this->inhalte->postsQuery($user)->orderByDesc('published_at')->first(),
        ]);
    }

    /** "Alles gesehen": Neu-Liste ab jetzt leeren. */
    public function gesehen(Request $request): RedirectResponse
    {
        $m = $request->user()->membershipIn();
        if ($m) {
            $m->forceFill(['settings' => array_merge($m->settings ?? [], ['neu_gesehen_at' => now()->toIso8601String()])])->save();
        }

        return redirect()->route('home');
    }
}
