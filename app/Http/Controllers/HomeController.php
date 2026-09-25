<?php

namespace App\Http\Controllers;

use App\Chat\Chat;
use App\Content\Inhalte;
use App\Models\Membership;
use App\Models\Program;
use App\Models\ProgramStep;
use App\Models\Task;
use App\Notifications\Runden;
use App\Programs\Begleitung;
use App\Programs\ProgramAccess;
use App\Programs\ProgressTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        // Erste Anmeldung: kurz durch die Einfuehrung
        if ($membership && ! $membership->setting('onboarding_seen_at') && ! $user->canManageCurrentTenant() && ! $request->query('ohne')) {
            return redirect()->route('willkommen');
        }

        $zuletzt = $request->attributes->get('zuletzt') ?? $membership?->last_seen_at;
        $seit = $zuletzt ? $zuletzt->copy()->subMinutes(15) : now()->subDays(7);
        $programs = $this->access->programsFor($user);

        // Aktuelle Woche in getakteten Programmen, sonst naechste Einheit
        $weiter = $programs->map(function (Program $p) use ($user) {
            $stand = $this->progress->summary($user, $p);
            $step = null;
            if ($p->pacing === 'weekly') {
                $step = $p->steps->filter(fn (ProgramStep $s) => $s->isUnlocked($p))->sortByDesc('position')->first();
            }

            return ['program' => $p, 'stand' => $stand, 'step' => $step];
        })->filter(fn ($w) => $w['stand']['total'] > 0);

        return view('home', [
            'person' => $user,
            'rolle' => $user->roleIn(),
            'neues' => $this->runden->neuesFuer($user, $seit),
            'weiter' => $weiter,
            'termin' => $this->begleitung->eventsQuery($user)->upcoming()->first(),
            'aufgaben' => Task::where('user_id', $user->id)->open()->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at')->limit(4)->get(),
            'offen' => Task::where('user_id', $user->id)->open()->count(),
            'ungelesen' => $this->chat->unreadFor($user),
            'impuls' => $this->inhalte->postsQuery($user)->orderByDesc('published_at')->first(),
            'personen' => $user->canManageCurrentTenant() ? Membership::where('status', 'active')->count() : null,
        ]);
    }
}
