<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Membership;
use App\Shop\Bexio;
use App\Shop\Buchhaltung;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/** Verbindung zur Buchhaltung (OAuth mit bexio) und Rechnungs-PDFs fuer die Person selbst und das Team. */
class BuchhaltungController extends Controller
{
    public function __construct(protected CurrentTenant $current) {}

    /** Schritt 1 der dauerhaften Verbindung: zu bexio, um zuzustimmen. Nur die Inhaberin. */
    public function bexioStart(Request $request): RedirectResponse
    {
        $this->nurInhaberin($request);
        $bexio = new Bexio($this->current->getOrFail());
        $e = $bexio->einstellungen();
        if (blank($e['client_id'] ?? null) || blank($e['client_secret'] ?? null)) {
            return redirect()->to('/coach/buchhaltung')->with('fehler', 'Bitte zuerst Client-ID und Client-Secret speichern.');
        }
        $state = Str::random(32);
        $request->session()->put('bexio_state', $state);

        return redirect()->away($bexio->authUrl($state, route('buchhaltung.bexio.rueckkehr')));
    }

    /** Schritt 2: Rueckkehr von bexio mit dem Code. */
    public function bexioRueckkehr(Request $request): RedirectResponse
    {
        $this->nurInhaberin($request);
        $state = $request->session()->pull('bexio_state');
        if (! $state || $state !== $request->query('state') || blank($request->query('code'))) {
            return redirect()->to('/coach/buchhaltung')->with('fehler', 'Die Rückkehr von bexio passt nicht zur Anfrage. Bitte nochmals verbinden.');
        }
        try {
            (new Bexio($this->current->getOrFail()))->verbinden((string) $request->query('code'), route('buchhaltung.bexio.rueckkehr'));
        } catch (RuntimeException $e) {
            return redirect()->to('/coach/buchhaltung')->with('fehler', $e->getMessage());
        }

        return redirect()->to('/coach/buchhaltung')->with('meldung', 'bexio ist verbunden.');
    }

    /** PDF einer eigenen Rechnung. */
    public function pdf(Request $request, int $rechnung): Response
    {
        $b = Buchhaltung::fuer($this->current->get());
        abort_unless($b && $b->verbunden(), 404);
        $eigene = $b->rechnungen($request->user())->firstWhere('id', $rechnung);
        abort_unless($eigene, 404);

        return $this->pdfAntwort($b, $eigene);
    }

    /** PDF einer Rechnung aus dem Dossier (Team). */
    public function pdfDossier(Request $request, Membership $membership, int $rechnung): Response
    {
        abort_unless($request->user()->canManageCurrentTenant() && $membership->user, 403);
        $b = Buchhaltung::fuer($this->current->get());
        abort_unless($b && $b->verbunden(), 404);
        $r = $b->rechnungen($membership->user)->firstWhere('id', $rechnung);
        abort_unless($r, 404);

        return $this->pdfAntwort($b, $r);
    }

    protected function pdfAntwort(Buchhaltung $b, array $r): Response
    {
        try {
            $pdf = $b->pdf((int) $r['id']);
        } catch (RuntimeException $e) {
            $pdf = null;
        }
        abort_unless($pdf, 503, 'Die Rechnung lässt sich gerade nicht laden. Bitte später nochmals versuchen.');

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Rechnung-'.($r['nr'] ?: $r['id']).'.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    protected function nurInhaberin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->is_platform_admin || $user->roleIn() === Role::Owner), 403);
    }
}
