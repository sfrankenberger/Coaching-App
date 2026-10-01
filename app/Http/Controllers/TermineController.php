<?php

namespace App\Http\Controllers;

use App\Ai\Summarizer;
use App\Booking\GoogleCalendar;
use App\Coach\Ansicht;
use App\Models\AiSummary;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\MediaPosition;
use App\Models\Program;
use App\Models\Task;
use App\Programs\Begleitung;
use App\Support\Besuche;
use App\Support\Ics;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TermineController extends Controller
{
    public function __construct(protected Begleitung $begleitung, protected CurrentTenant $current) {}

    public function index(Request $request): View
    {
        app(Besuche::class)->merken($request->user(), 'termine');
        $user = $request->user();
        $zeit = in_array($request->query('zeit'), ['kommend', 'vorbei', 'alle'], true) ? $request->query('zeit') : 'kommend';
        $kurs = (int) $request->query('kurs');
        $was = in_array($request->query('was'), ['termine', 'aufgaben'], true) ? $request->query('was') : 'alles';
        $suche = trim((string) $request->query('q', ''));

        $q = $this->begleitung->eventsQuery($user)->with(['program:id,title,color', 'attendees' => fn ($a) => $a->where('user_id', $user->id)])->withCount('gaeste');
        match ($zeit) {
            'kommend' => $q->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at'),
            'vorbei' => $q->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at'),
            default => $q->orderBy('starts_at'),
        };
        if ($kurs) {
            $q->where('program_id', $kurs);
        }
        if ($suche !== '') {
            $q->where('title', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $suche).'%');
        }
        $events = $was === 'aufgaben' ? collect() : $q->limit(200)->get();

        // Aufgaben mit Datum laufen im Kalender mit (wie lea_tm_aufgaben)
        $aufgaben = collect();
        if ($was !== 'termine' && ! Ansicht::teamSicht($user)) {
            $ta = Task::where('user_id', $user->id)->whereNotNull('due_at')->when($kurs, fn ($t) => $t->where('program_id', $kurs))
                ->when($suche !== '', fn ($t) => $t->where('title', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $suche).'%'));
            match ($zeit) {
                'kommend' => $ta->where('due_at', '>=', now()->startOfDay())->orderBy('due_at'),
                'vorbei' => $ta->where('due_at', '<', now()->startOfDay())->orderByDesc('due_at'),
                default => $ta->orderBy('due_at'),
            };
            $aufgaben = $ta->limit(100)->get();
        }
        $eintraege = $events->map(fn ($e) => ['zeit' => $e->starts_at, 'event' => $e])
            ->concat($aufgaben->map(fn ($t) => ['zeit' => $t->due_at->copy()->setTime(23, 59), 'task' => $t]))
            ->sortBy(fn ($x) => $x['zeit']->getTimestamp() * ($zeit === 'vorbei' ? -1 : 1))->values();

        $kurse = Program::whereIn('id', $this->begleitung->eventsQuery($user)->select('program_id'))->orderBy('title')->pluck('title', 'id');

        $m = $user->membershipIn();

        return view('termine.index', [
            'events' => $events, 'eintraege' => $eintraege, 'zeit' => $zeit, 'kurs' => $kurs, 'kurse' => $kurse, 'was' => $was, 'suche' => $suche,
            'kalenderUrl' => $m ? route('kalender.abo', ['token' => Ics::tokenFor($m)]) : null,
            // Eigene Buchung, wenn der Kalender angebunden ist, sonst ein Link nach aussen
            'buchenUrl' => app(GoogleCalendar::class)->aktiv() ? route('buchen.index') : data_get($this->current->get()?->settings, 'links.buchung'),
        ]);
    }

    public function show(Request $request, Event $termin): View
    {
        $user = $request->user();
        Gate::authorize('view', $termin);
        $termin->load(['program', 'resources', 'attendees.user:id,name', 'user:id,name']);

        return view('termine.show', [
            'position' => MediaPosition::where('user_id', $user->id)->where('key', 'event-'.$termin->id)->value('seconds'),
            'event' => $termin,
            'mein' => $termin->attendees->firstWhere('user_id', $user->id),
            'absagen' => $termin->attendees->where('status', 'declined'),
            'dabei' => $termin->attendees->whereNotNull('invited_at')->map(fn ($a) => $a->user)->filter()->when($termin->user, fn ($c) => $c->prepend($termin->user))->unique('id')->values(),
            'vorschlaege' => $termin->user_id === $user->id ? AiSummary::where('summarizable_type', 'event')->where('summarizable_id', $termin->id)->where('status', 'done')->first() : null,
        ]);
    }

    /** Aus der KI-Zusammenfassung der eigenen 1:1-Sitzung eine Aufgabe uebernehmen. */
    public function aufgabe(Request $request, Event $termin, Summarizer $summarizer): RedirectResponse
    {
        $user = $request->user();
        abort_unless($termin->user_id === $user->id, 403);
        $summary = AiSummary::where('summarizable_type', 'event')->where('summarizable_id', $termin->id)->where('status', 'done')->firstOrFail();
        $n = $summarizer->createTasks($summary, [(int) $request->input('nr')], $user);

        return back()->with('meldung', $n ? 'In deine Aufgaben übernommen.' : 'Die Aufgabe hast du schon.');
    }

    /** Abmelden oder doch dabei sein (nur Gruppentermine). */
    public function dabei(Request $request, Event $termin): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('view', $termin);
        abort_if($termin->isOneOnOne(), 422);

        $row = EventAttendee::firstOrNew(['event_id' => $termin->id, 'user_id' => $user->id]);
        $ab = $row->status !== 'declined';
        $row->status = $ab ? 'declined' : 'invited';
        $row->save();

        if ($request->expectsJson()) {
            return response()->json(['ab' => $ab]);
        }

        return back()->with('meldung', $ab ? 'Du bist abgemeldet.' : 'Schön, du bist wieder dabei.');
    }

    /** Aufzeichnung gesehen / live dabei gewesen. */
    public function gesehen(Request $request, Event $termin): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('view', $termin);
        $status = $request->input('status') === 'attended' ? 'attended' : 'watched';

        $row = EventAttendee::firstOrNew(['event_id' => $termin->id, 'user_id' => $user->id]);
        $row->status = $status;
        $row->attended_at = now();
        $row->save();

        if ($request->expectsJson()) {
            return response()->json(['status' => $status]);
        }

        return back()->with('meldung', $status === 'attended' ? 'Notiert: du warst live dabei.' : 'Notiert: Aufzeichnung gesehen.');
    }
}
