<?php

namespace App\Http\Controllers;

use App\Booking\Buchung;
use App\Booking\GoogleCalendar;
use App\Booking\Verfuegbarkeit;
use App\Coach\Lage;
use App\Models\Booking;
use App\Models\BookingType;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

/** Sitzung buchen (wie novamira-coaching): Art waehlen, freie Zeit, Vorbereitungsfragen. */
class BuchenController extends Controller
{
    public function __construct(protected Buchung $buchung) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(app(GoogleCalendar::class)->aktiv(), 404);

        return view('buchen.index', [
            'arten' => BookingType::where('is_active', true)->orderBy('position')->get()->map(fn ($a) => $a->setAttribute('hindernis', $this->buchung->hindernis($user, $a))),
            'buchungen' => Booking::where('user_id', $user->id)->where('status', 'gebucht')->where('starts_at', '>', now())->with(['event', 'type'])->orderBy('starts_at')->get(),
            'kontingent' => app(Lage::class)->kontingent($user),
        ]);
    }

    public function zeiten(Request $request, BookingType $art, Verfuegbarkeit $verfuegbarkeit): View|RedirectResponse
    {
        abort_unless(app(GoogleCalendar::class)->aktiv(), 404);
        if ($grund = $this->buchung->hindernis($request->user(), $art)) {
            return redirect()->route('buchen.index')->with('fehler', $grund);
        }
        try {
            $zeiten = $verfuegbarkeit->zeiten($art);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('buchen.index')->with('fehler', 'Der Kalender ist gerade nicht erreichbar. Versuch es bitte später noch einmal.');
        }

        return view('buchen.zeiten', ['art' => $art, 'tage' => $zeiten->groupBy(fn (Carbon $z) => $z->toDateString())]);
    }

    public function store(Request $request, BookingType $art): RedirectResponse
    {
        abort_unless(app(GoogleCalendar::class)->aktiv(), 404);
        $data = $request->validate([
            'start' => ['required', 'integer'],
            'antworten' => ['nullable', 'array', 'max:10'],
            'antworten.*' => ['nullable', 'string', 'max:3000'],
        ]);
        $start = Carbon::createFromTimestamp((int) $data['start'], app(CurrentTenant::class)->get()?->timezone ?: config('app.timezone'));
        $antworten = collect($art->questions ?? [])->values()->map(fn ($frage, $i) => ['frage' => $frage, 'antwort' => trim((string) ($data['antworten'][$i] ?? ''))])->all();

        $booking = $this->buchung->buchen($request->user(), $art, $start, $antworten);

        return redirect()->route('termine.show', $booking->event_id)->with('meldung', 'Gebucht. Ich freue mich auf dich.');
    }

    public function absagen(Request $request, Booking $booking): RedirectResponse
    {
        $this->buchung->absagen($booking, $request->user());

        return redirect()->route('buchen.index')->with('meldung', 'Abgesagt. Du kannst dir jederzeit eine neue Zeit buchen.');
    }
}
