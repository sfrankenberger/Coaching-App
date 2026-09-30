<?php

namespace App\Http\Controllers;

use App\Ai\Anthropic;
use App\Booking\Buchung;
use App\Booking\GoogleCalendar;
use App\Booking\Verfuegbarkeit;
use App\Chat\Chat;
use App\Chat\Terminvorschlag;
use App\Coach\Kommentare;
use App\Coach\Lage;
use App\Enums\Role;
use App\Jobs\VorbereitungErstellen;
use App\Models\AiSummary;
use App\Models\Answer;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\CoachNote;
use App\Models\Entitlement;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Note;
use App\Models\Offer;
use App\Models\ProgramMember;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\User;
use App\Models\Verkauf;
use App\Programs\ProgramAccess;
use App\Programs\ProgressTracker;
use App\Shop\Buchhaltung;
use App\Shop\Verkaufen;
use App\Shop\Zahlen;
use App\Shop\Zugang;
use App\Support\Telefon;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Dossier einer Person in der App-Huelle (wie lea-coachees "wer=": fuers Handy, fuer den Alltag):
 * Kopf mit Kontakt, Pakete und Kontingent, Verkaufen, Kennzahlen, dann Reiter Gespraech, Termine,
 * Kurs, Aufgaben, Freigegeben, Meine Notizen, Vorbereitung. Zum Einrichten bleibt die Verwaltung.
 */
class DossierController extends Controller
{
    public const REITER = ['gespraech', 'termine', 'kurs', 'aufgaben', 'geteilt', 'notizen', 'vorbereitung', 'rechnungen'];

    public function __construct(protected Chat $chat, protected Lage $lage, protected CurrentTenant $current) {}

    public function show(Request $request, Membership $membership): View
    {
        $this->pruefen($request, $membership);
        $user = $membership->user;
        $reiter = in_array($request->query('r'), self::REITER, true) ? $request->query('r') : 'gespraech';
        $tenant = $this->current->get();
        $lage = $this->lage->fuer($membership);

        $daten = [
            'm' => $membership,
            'person' => $user,
            'reiter' => $reiter,
            'lage' => $lage,
            'pakete' => Entitlement::where('user_id', $user->id)->where('status', 'active')->with('offer.programs')->orderByDesc('starts_at')->get(),
            'angebote' => Offer::where('is_active', true)->orderBy('title')->get(),
            'whatsapp' => filled($user->phone) ? Telefon::whatsapp($user->phone, $tenant) : null,
            'wochenaufgaben' => [Task::where('user_id', $user->id)->whereNotNull('assigned_by')->whereNotNull('done_at')->count(), Task::where('user_id', $user->id)->whereNotNull('assigned_by')->count()],
            'calls' => $this->calls($user->id),
            'schreibt' => Message::where('user_id', $user->id)->where('created_at', '>', now()->subDays(30))->count(),
            'ki' => Anthropic::configured($tenant),
            'verkaeufe' => Verkauf::where('user_id', $user->id)->whereNotNull('entitlement_id')->get()->keyBy('entitlement_id'),
            'buchhaltung' => ($bh = Buchhaltung::fuer($tenant)) && $bh->verbunden() ? $bh : null,
            'waehrung' => strtoupper((string) ($membership->setting('waehrung') ?: ($tenant?->currency ?: 'CHF'))),
        ];

        $daten += match ($reiter) {
            'termine' => $this->termine($user->id) + ['freieZeiten' => $this->freieZeiten()],
            'kurs' => ['programme' => $this->programme($user)],
            'aufgaben' => [
                'aufgaben' => Task::where('user_id', $user->id)->where(fn ($q) => $q->where('visibility', '!=', 'private')->orWhereNotNull('assigned_by'))
                    ->with('comments.user')->orderByRaw('CASE WHEN done_at IS NULL THEN 0 ELSE 1 END')->orderBy('due_at')->limit(30)->get(),
                'privateAufgaben' => Task::where('user_id', $user->id)->where('visibility', 'private')->whereNull('assigned_by')->count(),
            ],
            'geteilt' => $this->geteilt($user->id),
            'notizen' => ['coachNotizen' => CoachNote::where('user_id', $user->id)->with('author')->orderByDesc('is_pinned')->latest()->get()],
            'vorbereitung' => ['vorbereitung' => $this->vorbereitungVon($membership)],
            'rechnungen' => $this->rechnungen($user, (bool) $request->query('frisch')),
            default => $this->gespraech($user, $request->user()),
        };

        return view('coachees.show', $daten);
    }

    /** Als gelesen: die Person verschwindet aus "wartet auf deine Antwort". */
    public function gelesen(Request $request, Membership $membership): RedirectResponse
    {
        $this->pruefen($request, $membership);
        $conv = $this->chat->directFor($membership->user, false);
        if ($conv) {
            $this->chat->markRead($conv, $request->user());
            $membership->forceFill(['settings' => array_merge(is_array($membership->settings) ? $membership->settings : [], ['gelesen_bis' => (int) Message::where('conversation_id', $conv->id)->max('id')])])->save();
        }

        return back()->with('meldung', 'Gelesen, '.$membership->user->vorname().' wartet nicht mehr.');
    }

    /** Kurze Antwort direkt aus dem Dossier. */
    public function nachricht(Request $request, Membership $membership): RedirectResponse
    {
        $this->pruefen($request, $membership);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $this->chat->send($this->chat->directFor($membership->user), $request->user(), ['body' => $data['body']]);

        return redirect()->route('coachees.show', $membership)->with('meldung', 'Geschickt.');
    }

    public function notiz(Request $request, Membership $membership): RedirectResponse
    {
        $this->pruefen($request, $membership);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        CoachNote::create(['user_id' => $membership->user_id, 'author_id' => $request->user()->id, 'body' => trim($data['body'])]);

        return redirect()->route('coachees.show', [$membership, 'r' => 'notizen'])->with('meldung', 'Notiz gespeichert.');
    }

    public function notizLoeschen(Request $request, Membership $membership, int $notiz): RedirectResponse
    {
        $this->pruefen($request, $membership);
        CoachNote::where('user_id', $membership->user_id)->findOrFail($notiz)->delete();

        return redirect()->route('coachees.show', [$membership, 'r' => 'notizen']);
    }

    /** Aufgabe geben: sichtbar fuer die Coachin, die Person bekommt Bescheid (TaskObserver). */
    public function aufgabe(Request $request, Membership $membership): RedirectResponse
    {
        $this->pruefen($request, $membership);
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'body' => ['nullable', 'string', 'max:5000'], 'due_at' => ['nullable', 'date']]);
        Task::create([
            'user_id' => $membership->user_id,
            'assigned_by' => $request->user()->id,
            'title' => trim($data['title']),
            'body' => filled($data['body'] ?? null) ? trim($data['body']) : null,
            'due_at' => filled($data['due_at'] ?? null) ? Carbon::parse($data['due_at'], $this->zone())->utc() : null,
            'source' => 'coach',
            'visibility' => 'coach',
        ]);

        return redirect()->route('coachees.show', [$membership, 'r' => 'aufgaben'])->with('meldung', $membership->user->vorname().' hat die Aufgabe.');
    }

    /** Termin eintragen (1:1), die Person bekommt Bescheid (EventObserver). */
    public function termin(Request $request, Membership $membership): RedirectResponse
    {
        $this->pruefen($request, $membership);
        $data = $request->validate(['start' => ['required', 'date'], 'dauer' => ['required', 'integer', 'min:15', 'max:240'], 'title' => ['nullable', 'string', 'max:200']]);
        $start = Carbon::parse($data['start'], $this->zone())->utc();
        app(Buchung::class)->fest($membership->user, $start, (int) $data['dauer'], $request->user(), $data['title'] ?? null, 'dossier');

        return redirect()->route('coachees.show', [$membership, 'r' => 'termine'])->with('meldung', 'Termin eingetragen, '.$membership->user->vorname().' bekommt Bescheid mit Kalenderdatei.');
    }

    /** Zeiten vorschlagen: landen im Gespraech, die Person tippt eine an. */
    public function vorschlag(Request $request, Membership $membership): RedirectResponse
    {
        $this->pruefen($request, $membership);
        $data = $request->validate(['zeiten' => ['required', 'array', 'min:1', 'max:6'], 'zeiten.*' => ['nullable', 'date'], 'dauer' => ['required', 'integer', 'min:15', 'max:240'], 'text' => ['nullable', 'string', 'max:2000']]);
        $zeiten = collect($data['zeiten'])->filter()->map(fn ($z) => Carbon::parse($z, $this->zone())->toIso8601String())->all();
        app(Terminvorschlag::class)->vorschlagen($request->user(), $membership->user, $zeiten, (int) $data['dauer'], $data['text'] ?? null);

        return redirect()->route('coachees.show', $membership)->with('meldung', 'Die Zeiten sind im Gespräch mit '.$membership->user->vorname().'.');
    }

    /** Etwas verkaufen oder Zugang geben: Angebot freischalten, Programme betreten, Notiz, Mail. */
    public function zugang(Request $request, Membership $membership, Verkaufen $verkaufen): RedirectResponse
    {
        $this->pruefen($request, $membership);
        $data = $request->validate([
            'offer_id' => ['required', 'integer'],
            'betrag' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'waehrung' => ['nullable', 'in:CHF,EUR'],
            'zahlungsart' => ['nullable', 'in:rechnung,bezahlt,kostenlos'],
            'rechnung' => ['nullable', 'boolean'],
            'tage' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'sitzungen' => ['nullable', 'integer', 'min:0', 'max:200'],
            'notiz' => ['nullable', 'string', 'max:2000'],
            'mail' => ['nullable', 'boolean'],
        ]);
        $offer = Offer::where('is_active', true)->findOrFail($data['offer_id']);
        $v = $verkaufen->verkaufen($request->user(), $membership->user, $offer, $data);
        $text = $offer->title.' ist für '.$membership->user->vorname().' '.(($v->settings['warten_auf_zahlung'] ?? false) ? 'vorgemerkt, Zugang ab Zahlungseingang' : 'freigeschaltet').'.';
        if ($fehler = $v->settings['rechnung_fehler'] ?? null) {
            return redirect()->route('coachees.show', [$membership, 'r' => 'rechnungen'])->with('fehler', $text.' Die Rechnung konnte nicht angelegt werden: '.$fehler);
        }

        return redirect()->route('coachees.show', [$membership, 'r' => $v->rechnung_id ? 'rechnungen' : 'kurs'])->with('meldung', $text.($v->rechnung_nr ? ' Rechnung '.$v->rechnung_nr.' ist in bexio.' : ''));
    }

    public function einladung(Request $request, Membership $membership, Zugang $zugang): RedirectResponse
    {
        $this->pruefen($request, $membership);
        $zugang->welcome($membership->user);

        return back()->with('meldung', 'Einladung an '.$membership->user->email.' geschickt.');
    }

    public function vorbereitung(Request $request, Membership $membership): RedirectResponse
    {
        $this->pruefen($request, $membership);
        abort_unless(Anthropic::configured($this->current->get()), 404);
        AiSummary::updateOrCreate(['summarizable_type' => 'membership', 'summarizable_id' => $membership->id, 'kind' => 'vorbereitung'], ['status' => 'pending', 'error' => null]);
        VorbereitungErstellen::dispatch($this->current->id(), $membership->id, $request->user()->id);

        return redirect()->route('coachees.show', [$membership, 'r' => 'vorbereitung'])->with('meldung', 'Läuft. Du bekommst Bescheid, sobald es fertig ist.');
    }

    /** Antwort auf etwas Geteiltes (Antwort, Reflexion, Notiz, Aufgabe). */
    public function kommentar(Request $request, Membership $membership, Kommentare $k): RedirectResponse
    {
        $this->pruefen($request, $membership);
        $data = $request->validate(['typ' => ['required', 'string'], 'id' => ['required', 'integer'], 'text' => ['required', 'string', 'max:5000']]);
        $item = $k->finden($data['typ'], (int) $data['id']);
        abort_unless($item && $item->user_id === $membership->user_id, 404);
        $k->schreiben($request->user(), $item, trim($data['text']));

        return redirect()->route('coachees.show', [$membership, 'r' => $data['typ'] === 'task' ? 'aufgaben' : 'geteilt'])->with('meldung', $membership->user->vorname().' bekommt Bescheid.');
    }

    /** Rechnungen der Person aus der Buchhaltung des Mandanten (bexio), mit Fehlertext statt Absturz. */
    protected function rechnungen(User $user, bool $frisch = false): array
    {
        $b = Buchhaltung::fuer($this->current->get());
        $zahlen = ['zahlen' => app(Zahlen::class)->person($user), 'zahlenSichtbar' => auth()->user()->is_platform_admin || auth()->user()->roleIn() === Role::Owner];
        if (! $b || ! $b->verbunden()) {
            return ['rechnungen' => collect(), 'buchhaltung' => $b, 'rechnungenFehler' => null] + $zahlen;
        }
        try {
            return ['rechnungen' => $b->rechnungen($user, $frisch), 'buchhaltung' => $b, 'rechnungenFehler' => null] + $zahlen;
        } catch (\RuntimeException $e) {
            report($e);

            return ['rechnungen' => collect(), 'buchhaltung' => $b, 'rechnungenFehler' => $e->getMessage()] + $zahlen;
        }
    }

    /** Drei freie Zeiten aus dem Kalender, moeglichst an verschiedenen Tagen, fuer "Zeiten vorschlagen". */
    protected function freieZeiten(): Collection
    {
        $art = BookingType::where('is_active', true)->where('is_open', false)->orderBy('position')->first() ?? BookingType::where('is_active', true)->orderBy('position')->first();
        if (! $art || ! app(GoogleCalendar::class)->aktiv()) {
            return collect();
        }
        try {
            $zeiten = app(Verfuegbarkeit::class)->zeiten($art);
        } catch (\Throwable $e) {
            return collect();
        }
        $tage = $zeiten->groupBy(fn (Carbon $z) => $z->toDateString());
        $aus = $tage->map(fn ($z) => $z->values()->get((int) floor($z->count() / 2)))->values()->take(3);

        return $aus->count() < 3 ? $zeiten->take(3)->values() : $aus;
    }

    protected function pruefen(Request $request, Membership $membership): void
    {
        abort_unless($request->user()->canManageCurrentTenant(), 403);
        abort_unless($membership->user, 404);
    }

    protected function zone(): string
    {
        return $this->current->get()?->timezone ?: config('app.timezone');
    }

    protected function gespraech($user, $ich): array
    {
        $conv = $this->chat->directFor($user, false);
        $nachrichten = $conv ? Message::where('conversation_id', $conv->id)->with('user:id,name,avatar_path,updated_at')->latest('id')->limit(40)->get()->reverse()->values() : collect();
        if ($conv) {
            $this->chat->markRead($conv, $ich);
        }

        return [
            'conv' => $conv,
            'nachrichten' => $nachrichten,
            'gelesenBis' => $conv ? $this->chat->readUntilByOthers($conv, $ich) : null,
            'gespraechUrl' => route('gespraech.show', $this->chat->directFor($user)),
        ];
    }

    protected function termine(int $userId): array
    {
        $einzel = Event::where('user_id', $userId)->orderByDesc('starts_at')->limit(20)->get();
        $buchungen = Booking::where('user_id', $userId)->get()->keyBy('event_id');
        $gruppe = EventAttendee::where('user_id', $userId)->with('event')->get()->filter(fn ($a) => $a->event);
        $zeilen = $einzel->map(fn (Event $e) => ['event' => $e, 'art' => '1:1', 'buchung' => $buchungen->get($e->id), 'status' => ($b = $buchungen->get($e->id)) && $b->status === 'abgesagt' ? 'abgesagt' : ($b && $b->booked_by === $b->user_id ? 'selbst gebucht' : null)])
            ->concat($gruppe->map(fn (EventAttendee $a) => ['event' => $a->event, 'art' => $a->event->program?->title ?: 'Gruppe', 'status' => ['invited' => null, 'declined' => 'abgesagt', 'attended' => 'live dabei', 'watched' => 'Aufzeichnung gesehen'][$a->status] ?? $a->status]));

        return [
            'kommt' => $zeilen->filter(fn ($z) => $z['event']->starts_at->gte(now()->subHours(2)))->sortBy(fn ($z) => $z['event']->starts_at->getTimestamp())->values(),
            'war' => $zeilen->filter(fn ($z) => $z['event']->starts_at->lt(now()->subHours(2)))->sortByDesc(fn ($z) => $z['event']->starts_at->getTimestamp())->take(15)->values(),
        ];
    }

    protected function programme($user)
    {
        $tracker = app(ProgressTracker::class);

        return app(ProgramAccess::class)->programsFor($user)->map(function ($p) use ($user, $tracker) {
            $p->setAttribute('stand', $tracker->summary($user, $p));
            $p->setAttribute('mitglied', ProgramMember::where('program_id', $p->id)->where('user_id', $user->id)->first());

            return $p;
        });
    }

    protected function geteilt(int $userId): array
    {
        return [
            'antworten' => Answer::where('user_id', $userId)->where('shared_with_coach', true)->with(['exercise.unit.program', 'comments.user'])
                ->get()->filter(fn (Answer $a) => $a->isFilled() && $a->exercise?->unit)->groupBy(fn (Answer $a) => $a->exercise->unit_id),
            'reflexionen' => Reflection::where('user_id', $userId)->where('visibility', '!=', 'private')->with('comments.user')->latest()->limit(10)->get(),
            'notizen' => Note::where('user_id', $userId)->whereIn('visibility', ['coach', 'program', 'all'])->with('comments.user')->latest()->limit(20)->get(),
        ];
    }

    protected function vorbereitungVon(Membership $m): ?AiSummary
    {
        return AiSummary::where('summarizable_type', 'membership')->where('summarizable_id', $m->id)->where('kind', 'vorbereitung')->where('updated_at', '>=', now()->subDays(3))->first();
    }

    /** Bei den Calls: dabei (live oder Aufzeichnung) von allen Gruppencalls der letzten drei Monate. */
    protected function calls(int $userId): array
    {
        $programme = ProgramMember::where('user_id', $userId)->pluck('program_id');
        $calls = Event::whereIn('program_id', $programme)->whereNull('user_id')->where('is_published', true)->whereNotIn('type', Event::ALL_DAY_TYPES)
            ->whereBetween('starts_at', [now()->subMonths(3), now()])->pluck('id');
        $dabei = $calls->isEmpty() ? 0 : EventAttendee::where('user_id', $userId)->whereIn('event_id', $calls)->whereIn('status', ['attended', 'watched'])->count();

        return [$dabei, $calls->count()];
    }
}
