<?php

namespace App\Http\Controllers;

use App\Ai\Anthropic;
use App\Ai\Dialog;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Claude in der App: Gespraech mit Werkzeugen, Rueckfrage vor jedem Schreiben. Zustand in der Sitzung. */
class AssistentChatController extends Controller
{
    public const SCHLUESSEL = 'assistent.chat';

    public function __construct(protected Dialog $dialog, protected CurrentTenant $current) {}

    public function senden(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageCurrentTenant(), 403);
        $data = $request->validate(['text' => ['required', 'string', 'max:4000']]);
        if (! Anthropic::configured($this->current->get())) {
            return redirect()->to(route('assistent').'#chat')->with('fehler', 'Ohne KI-Schlüssel gibt es kein Gespräch. Trag ihn unter Verbindungen ein.');
        }
        $zustand = $request->session()->get(self::SCHLUESSEL, Dialog::leer());
        if ($zustand['offen']) {
            return redirect()->to(route('assistent').'#chat')->with('fehler', 'Erst die offene Rückfrage beantworten.');
        }
        $request->session()->put(self::SCHLUESSEL, $this->dialog->sagen($zustand, $data['text'], $request->user()));

        return redirect()->to(route('assistent').'#chat');
    }

    public function entscheiden(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageCurrentTenant(), 403);
        $zustand = $request->session()->get(self::SCHLUESSEL, Dialog::leer());
        if (! $zustand['offen']) {
            return redirect()->to(route('assistent').'#chat');
        }
        $request->session()->put(self::SCHLUESSEL, $this->dialog->entscheiden($zustand, $request->boolean('ja'), $request->user()));

        return redirect()->to(route('assistent').'#chat');
    }

    public function neu(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SCHLUESSEL);

        return redirect()->to(route('assistent').'#chat');
    }
}
