<?php

namespace App\Http\Controllers;

use App\Auth\MagicLink;
use App\Booking\Buchung;
use App\Booking\GoogleCalendar;
use App\Booking\Verfuegbarkeit;
use App\Enums\Role;
use App\Mail\GastBuchungMail;
use App\Models\BookingType;
use App\Models\Membership;
use App\Models\User;
use App\Support\Zeit;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Klarheitsgespraech fuer Gaeste, ohne Anmeldung (wie der Buchungsblock auf der Website):
 * Name, Mail, Zeit, Vorbereitungsfragen. Die Person bekommt ein Konto als Gast, eine liebe Mail
 * mit Bestaetigung und Anmeldelink, und sieht den Termin danach in der App.
 */
class GastBuchenController extends Controller
{
    public function __construct(protected Buchung $buchung, protected CurrentTenant $current) {}

    public function zeiten(Request $request, BookingType $art, Verfuegbarkeit $verfuegbarkeit): View|RedirectResponse
    {
        abort_unless(app(GoogleCalendar::class)->aktiv() && $art->is_active && $art->is_open, 404);
        if ($request->user()) {
            return redirect()->route('buchen.zeiten', $art);
        }
        try {
            $zeiten = $verfuegbarkeit->zeiten($art);
        } catch (Throwable $e) {
            report($e);
            $zeiten = collect();
        }

        return view('buchen.gast', ['art' => $art, 'tage' => $zeiten->groupBy(fn (Carbon $z) => $z->toDateString())]);
    }

    public function store(Request $request, BookingType $art): RedirectResponse
    {
        abort_unless(app(GoogleCalendar::class)->aktiv() && $art->is_active && $art->is_open, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'start' => ['required', 'integer'],
            'antworten' => ['nullable', 'array', 'max:10'],
            'antworten.*' => ['nullable', 'string', 'max:3000'],
            'website' => ['nullable', 'size:0'],   // Honigtopf: Menschen lassen das Feld leer
            'ref' => ['nullable', 'string', 'max:120'],
        ]);
        $herkunft = filled($data['ref'] ?? null) ? trim($data['ref']) : null;
        $tenant = $this->current->getOrFail();
        $start = Carbon::createFromTimestamp((int) $data['start'], $tenant->timezone ?: config('app.timezone'));
        $antworten = collect($art->questions ?? [])->values()->map(fn ($frage, $i) => ['frage' => $frage, 'antwort' => trim((string) ($data['antworten'][$i] ?? ''))])->all();

        $email = Str::lower(trim($data['email']));
        $user = User::where('email', $email)->first();
        $neu = ! $user;
        if (! $user) {
            $user = User::create(['name' => trim($data['name']), 'email' => $email, 'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null]);
        } elseif (filled($data['phone'] ?? null) && ! $user->phone) {
            $user->forceFill(['phone' => trim($data['phone'])])->save();
        }
        if (! $user->membershipIn($tenant)) {
            Membership::anlegen(['user_id' => $user->id, 'role' => Role::Guest->value, 'status' => 'active', 'joined_at' => now(), 'settings' => array_filter(['quelle' => 'klarheitsgespraech', 'herkunft' => $herkunft])]);
            $neu = true;
        }

        $booking = $this->buchung->buchen($user, $art, $start, $antworten, melden: false, herkunft: $herkunft);

        $url = app(MagicLink::class)->create($user, route('termine.show', $booking->event_id, absolute: false), $request->ip(), 60 * 24 * 7);
        Mail::to($user->email, $user->name)->send(new GastBuchungMail($user, $booking, $url, $neu));

        return redirect()->route('buchen.gast.danke', $art)->with('gast', ['wann' => Zeit::wann($booking->starts_at), 'email' => $user->email, 'titel' => $art->title]);
    }

    public function danke(Request $request, BookingType $art): View|RedirectResponse
    {
        $gast = $request->session()->get('gast');
        if (! $gast) {
            return redirect()->route('buchen.gast', $art);
        }

        return view('buchen.gast-danke', ['art' => $art, 'gast' => $gast]);
    }
}
