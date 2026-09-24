<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Models\TelegramLink;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
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
}
