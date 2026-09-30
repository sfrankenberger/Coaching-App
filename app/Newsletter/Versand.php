<?php

namespace App\Newsletter;

use App\Mail\NewsletterMail;
use App\Models\Kontakt;
use App\Models\Newsletter;
use App\Models\NewsletterVersand;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Newsletter verschicken: beim Start eine Zeile je Empfaengerin, dann Wellen (jede Minute bis WELLE Mails),
 * damit Mailgun und der Server nicht auf einmal alles bekommen. Test an bis zu fuenf Adressen.
 */
class Versand
{
    public const WELLE = 60;

    public const TEST_MAX = 5;

    public function __construct(protected CurrentTenant $current) {}

    /** Empfaenger einsammeln und den Versand freigeben; die Wellen macht newsletter:lauf. */
    public function starten(Newsletter $n): int
    {
        if (! $n->istEntwurf()) {
            return 0;
        }
        $ids = $n->empfaengerQuery()->pluck('id');
        $tenantId = $this->current->id();
        foreach ($ids->chunk(500) as $chunk) {
            NewsletterVersand::insertOrIgnore($chunk->map(fn ($id) => ['tenant_id' => $tenantId, 'newsletter_id' => $n->id, 'kontakt_id' => $id, 'token' => Str::random(40), 'status' => 'wartet', 'created_at' => now(), 'updated_at' => now()])->all());
        }
        $n->forceFill(['status' => $ids->isEmpty() ? 'gesendet' : 'laeuft', 'gestartet_at' => now(), 'gesendet_at' => $ids->isEmpty() ? now() : null, 'empfaenger' => $ids->count()])->save();

        return $ids->count();
    }

    /** Eine Welle: die naechsten wartenden Zeilen aller laufenden (und faelligen geplanten) Newsletter. */
    public function welle(int $max = self::WELLE): int
    {
        foreach (Newsletter::where('status', 'geplant')->whereNotNull('geplant_at')->where('geplant_at', '<=', now()->utc())->get() as $n) {
            $this->starten($n);
        }
        $n = 0;
        foreach (Newsletter::where('status', 'laeuft')->get() as $nl) {
            $zeilen = $nl->versand()->where('status', 'wartet')->with('kontakt')->limit(max(1, $max - $n))->get();
            foreach ($zeilen as $z) {
                $n++;
                $this->schicken($nl, $z);
            }
            if ($nl->versand()->where('status', 'wartet')->doesntExist()) {
                $nl->forceFill(['status' => 'gesendet', 'gesendet_at' => now(), 'gesendet' => $nl->versand()->where('status', 'gesendet')->count()])->save();
            } else {
                $nl->forceFill(['gesendet' => $nl->versand()->where('status', 'gesendet')->count()])->save();
            }
            if ($n >= $max) {
                break;
            }
        }

        return $n;
    }

    protected function schicken(Newsletter $nl, NewsletterVersand $z): void
    {
        $k = $z->kontakt;
        if (! $k || ! $k->istBestaetigt()) {
            $z->forceFill(['status' => 'fehler', 'fehler' => 'Kontakt nicht mehr bestätigt'])->save();

            return;
        }
        try {
            Mail::to($k->email, $k->name)->send(new NewsletterMail($nl, $k, $z));
            $z->forceFill(['status' => 'gesendet', 'gesendet_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::warning('Newsletter nicht gesendet', ['newsletter' => $nl->id, 'kontakt' => $k->id, 'fehler' => $e->getMessage()]);
            $z->forceFill(['status' => 'fehler', 'fehler' => mb_substr($e->getMessage(), 0, 500)])->save();
        }
    }

    /** Testversand an frei gewaehlte Adressen (ohne Zaehlung, Platzhalter mit "du"). Merkt sich die Adressen. */
    public function test(Newsletter $n, array $adressen): int
    {
        $adressen = collect($adressen)->map(fn ($a) => strtolower(trim((string) $a)))->filter(fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL))->unique()->take(self::TEST_MAX)->values();
        foreach ($adressen as $a) {
            $k = Kontakt::where('email', $a)->first() ?? new Kontakt(['email' => $a, 'name' => null]);
            Mail::to($a)->send(new NewsletterMail($n, $k, null, test: true));
        }
        $n->forceFill(['settings' => array_merge($n->settings ?? [], ['testadressen' => $adressen->all()])])->save();

        return $adressen->count();
    }

    public function geoeffnet(NewsletterVersand $z): void
    {
        if (! $z->geoeffnet_at) {
            $z->forceFill(['geoeffnet_at' => now()])->save();
            $z->newsletter?->increment('geoeffnet');
        }
    }

    public function geklickt(NewsletterVersand $z): void
    {
        $this->geoeffnet($z);
        if (! $z->geklickt_at) {
            $z->forceFill(['geklickt_at' => now()])->save();
            $z->newsletter?->increment('geklickt');
        }
    }
}
