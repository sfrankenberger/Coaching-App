<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Bild;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Profilbild: hochladen (quadratisch, 512 px), anzeigen (nur im eigenen Mandanten), loeschen. */
class AvatarController extends Controller
{
    public function show(Request $request, User $user): Response
    {
        abort_unless($user->membershipIn() && $user->avatar_path && Storage::exists($user->avatar_path), 404);

        return Storage::response($user->avatar_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function speichern(Request $request): RedirectResponse
    {
        $request->validate(['foto' => ['required', 'file', 'image', 'max:8192']]);
        try {
            $request->user()->avatarSpeichern(Bild::quadrat((string) file_get_contents($request->file('foto')->getRealPath())));
        } catch (\RuntimeException $e) {
            return back()->with('fehler', $e->getMessage());
        }

        return redirect()->to(route('profil').'#foto')->with('meldung', 'Dein Foto ist drin.');
    }

    public function loeschen(Request $request): RedirectResponse
    {
        $request->user()->avatarLoeschen();

        return redirect()->to(route('profil').'#foto')->with('meldung', 'Foto entfernt.');
    }
}
