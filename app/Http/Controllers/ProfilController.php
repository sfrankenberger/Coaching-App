<?php

namespace App\Http\Controllers;

use App\Booking\GoogleCalendar;
use App\Coach\Lage;
use App\Models\Entitlement;
use App\Models\PushSubscription;
use App\Models\TelegramLink;
use App\Programs\ProgramAccess;
use App\Support\Ics;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ProfilController extends Controller
{
    public function show(Request $request): View
    {
        $tenant = app(CurrentTenant::class)->get();

        return view('profil', [
            'mitgliedschaft' => $request->user()->membershipIn(),
            'pushGeraete' => PushSubscription::where('user_id', $request->user()->id)->count(),
            'telegram' => TelegramController::configured($tenant) ? TelegramLink::where('user_id', $request->user()->id)->first() : false,
            'telegramBot' => $tenant?->setting('telegram.bot_username'),
            'passkeys' => $request->user()->webAuthnCredentials()->orderBy('created_at')->get(),
            'kalenderUrl' => ($m = $request->user()->membershipIn()) ? route('kalender.abo', ['token' => Ics::tokenFor($m)]) : null,
            'zugaenge' => $this->zugaenge($request->user()),
            'kontingent' => app(Lage::class)->kontingent($request->user()),
            'buchen' => app(GoogleCalendar::class)->aktiv(),
            'aboUrl' => $tenant?->setting('shop.account_url'),
        ]);
    }

    /** Meine Buchungen (wie lea-mitgliedschaft-neu): Zugaenge mit Programmen, Laufzeit und Kurswoche. */
    protected function zugaenge($user)
    {
        return Entitlement::where('user_id', $user->id)->with('offer.programs.steps')->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->latest('starts_at')->get()
            ->filter(fn ($e) => $e->offer)
            ->map(function ($e) {
                $e->setAttribute('aktiv', $e->status === 'active' && (! $e->ends_at || $e->ends_at->isFuture()));
                $e->setAttribute('wochen', $e->offer->programs->where('pacing', 'weekly')->map(function ($p) {
                    $alle = $p->steps->count();
                    $offen = $p->steps->filter(fn ($s) => $s->isUnlocked($p))->count();

                    return $alle ? ['program' => $p, 'jetzt' => max(1, $offen), 'alle' => $alle] : null;
                })->filter()->values());

                return $e;
            });
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $request->user()->forceFill([
            'name' => trim($data['name']),
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
        ])->save();

        return back()->with('meldung', 'Gespeichert.');
    }

    public function notifications(Request $request): RedirectResponse
    {
        $membership = $request->user()->membershipIn();
        abort_unless($membership, 403);

        $settings = $membership->settings ?? [];
        foreach (['termine', 'abendmail', 'aufgaben'] as $key) {
            data_set($settings, "notifications.$key", $request->boolean($key));
        }
        $membership->forceFill(['settings' => $settings])->save();

        return back()->with('meldung', 'Gespeichert.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $request->user()->forceFill(['password' => $data['password']])->save();

        return back()->with('meldung', 'Passwort gesetzt. Der Link per Mail geht weiterhin.');
    }

    /** Technik-Hilfe: Meldung mit Seite, Geraet und Browser an die Support-Adresse des Mandanten. */
    public function hilfe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'wo' => ['nullable', 'string', 'max:200'],
            'was' => ['required', 'string', 'max:3000'],
            'seite' => ['nullable', 'string', 'max:500'],
            'geraet' => ['nullable', 'string', 'max:300'],
        ]);
        $tenant = app(CurrentTenant::class)->get();
        $an = $tenant?->setting('support.email') ?: $tenant?->setting('mail.reply_to') ?: $tenant?->setting('mail.from_address');
        if (! $an) {
            return back()->with('fehler', 'Gerade kann keine Meldung verschickt werden. Schreib bitte ins Gespräch.');
        }
        $user = $request->user();
        $programme = app(ProgramAccess::class)->programsFor($user)->pluck('title')->join(', ');
        $text = "Technik-Meldung von {$user->name} <{$user->email}>\n\n"
            .'Wo: '.($data['wo'] ?: '-')."\n\nWas:\n{$data['was']}\n\n"
            .'Seite: '.($data['seite'] ?: '-')."\n"
            .'Gerät: '.($data['geraet'] ?: '-')."\n"
            .'Browser: '.$request->userAgent()."\n"
            .'Kurse: '.($programme ?: '-')."\n"
            .'Mandant: '.($tenant?->name ?? '-');

        Mail::raw($text, function ($m) use ($an, $user, $tenant) {
            $m->to($an)->replyTo($user->email, $user->name)->subject('Technik: '.$user->name.' ('.($tenant?->name ?? 'App').')');
            if ($from = $tenant?->setting('mail.from_address')) {
                $m->from($from, $tenant->setting('mail.from_name', $tenant->name));
            }
        });

        return redirect()->route('profil')->with('meldung', 'Danke, deine Meldung ist unterwegs. Du bekommst eine Antwort per Mail.');
    }
}
