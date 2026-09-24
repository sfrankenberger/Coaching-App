<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Program;
use App\Programs\Begleitung;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TermineController extends Controller
{
    public function __construct(protected Begleitung $begleitung) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $zeit = in_array($request->query('zeit'), ['kommend', 'vorbei', 'alle'], true) ? $request->query('zeit') : 'kommend';
        $kurs = (int) $request->query('kurs');

        $q = $this->begleitung->eventsQuery($user)->with(['program:id,title,color', 'attendees' => fn ($a) => $a->where('user_id', $user->id)]);
        match ($zeit) {
            'kommend' => $q->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at'),
            'vorbei' => $q->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at'),
            default => $q->orderBy('starts_at'),
        };
        if ($kurs) {
            $q->where('program_id', $kurs);
        }
        $events = $q->limit(200)->get();

        $kurse = Program::whereIn('id', $this->begleitung->eventsQuery($user)->select('program_id'))->orderBy('title')->pluck('title', 'id');

        return view('termine.index', ['events' => $events, 'zeit' => $zeit, 'kurs' => $kurs, 'kurse' => $kurse]);
    }

    public function show(Request $request, Event $termin): View
    {
        $user = $request->user();
        abort_unless($this->begleitung->canViewEvent($user, $termin), 403);
        $termin->load(['program', 'resources', 'attendees.user:id,name']);

        return view('termine.show', [
            'event' => $termin,
            'mein' => $termin->attendees->firstWhere('user_id', $user->id),
            'absagen' => $termin->attendees->where('status', 'declined'),
        ]);
    }

    /** Abmelden oder doch dabei sein (nur Gruppentermine). */
    public function dabei(Request $request, Event $termin): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->begleitung->canViewEvent($user, $termin), 403);
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
        abort_unless($this->begleitung->canViewEvent($user, $termin), 403);
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
