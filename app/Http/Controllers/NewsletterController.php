<?php

namespace App\Http\Controllers;

use App\Mail\AbmeldeLinkMail;
use App\Models\Kontakt;
use App\Models\NewsletterVersand;
use App\Newsletter\Kontakte;
use App\Newsletter\Versand;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Oeffentliche Seiten des Newsletters: Anmeldung (Formular der Website oder eigene Seite), Bestaetigung
 * (Double-Opt-in), Abmeldung mit einem Klick, Zaehlpixel, Klickzaehlung, Webversion.
 */
class NewsletterController extends Controller
{
    public function __construct(protected CurrentTenant $current, protected Kontakte $kontakte, protected Versand $versand) {}

    public function anmeldenForm(Request $request): View
    {
        return view('newsletter.anmelden', ['tag' => (string) $request->query('tag', 'newsletter'), 'zurueck' => $this->zurueck($request->query('zurueck'))]);
    }

    /** POST aus dem Website-Shortcode oder der eigenen Seite; JSON, wenn per fetch gefragt. */
    public function anmelden(Request $request): View|RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
            'name' => ['nullable', 'string', 'max:120'],
            'tag' => ['nullable', 'string', 'max:200'],
            'einwilligung' => ['accepted'],
            'sofort' => ['nullable', 'boolean'],       // Veranstaltung: ohne Bestaetigungsmail
            'zurueck' => ['nullable', 'string', 'max:500'],
            'herkunft' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'size:0'],       // Honigtopf
        ], ['einwilligung.accepted' => 'Bitte bestätige, dass wir dir schreiben dürfen.']);

        $tags = array_filter(array_map('trim', explode(',', (string) ($data['tag'] ?? 'newsletter'))));
        $k = $this->kontakte->anmelden($data['email'], $data['name'] ?? null, $tags ?: ['newsletter'], [
            'ip' => $request->ip(), 'referrer' => mb_substr((string) $request->headers->get('referer'), 0, 300), 'herkunft' => $data['herkunft'] ?? 'formular',
            'text' => 'Ich möchte Post bekommen und weiss, dass ich mich jederzeit abmelden kann.',
        ], ! $request->boolean('sofort'));

        $stand = $k->istBestaetigt() ? 'dabei' : 'postfach';
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'stand' => $stand]);
        }
        if ($zurueck = $this->zurueck($data['zurueck'] ?? null)) {
            return redirect()->away($zurueck.(str_contains($zurueck, '?') ? '&' : '?').'newsletter='.$stand);
        }

        return view('newsletter.stand', ['stand' => $stand, 'kontakt' => $k]);
    }

    public function bestaetigen(Request $request, Kontakt $kontakt): View
    {
        abort_unless(hash_equals(substr($kontakt->token, 0, 12), (string) $request->query('t')), 403);
        $this->kontakte->bestaetigen($kontakt);

        return view('newsletter.stand', ['stand' => 'bestaetigt', 'kontakt' => $kontakt]);
    }

    /** Ein Klick genuegt (auch List-Unsubscribe-Post der Mailprogramme). */
    /** Abmelden ohne Token, z.B. aus alten Mails eines frueheren Mailprogramms: Adresse eingeben, Link kommt per Mail. */
    public function abmeldenForm(): View
    {
        return view('newsletter.abmelden');
    }

    public function abmeldeLink(Request $request): View
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:190'], 'website' => ['nullable', 'size:0']]);
        $k = Kontakt::where('email', Str::lower(trim($data['email'])))->first();
        if ($k && $k->status !== 'abgemeldet') {
            Mail::to($k->email, $k->name)->send(new AbmeldeLinkMail($k, route('newsletter.abmelden', $k->token)));
        }

        return view('newsletter.abmelden', ['geschickt' => true]);   // immer dieselbe Antwort, verraet nicht, wer eingetragen ist
    }

    public function abmelden(Request $request, string $token): View
    {
        $k = Kontakt::where('token', $token)->firstOrFail();
        $this->kontakte->abmelden($k, $request->isMethod('post') ? 'mailprogramm' : 'link');

        return view('newsletter.stand', ['stand' => 'abgemeldet', 'kontakt' => $k]);
    }

    public function wiederAnmelden(string $token): View
    {
        $k = Kontakt::where('token', $token)->firstOrFail();
        $this->kontakte->anmelden($k->email, $k->name, $k->tags ?? ['newsletter'], ['herkunft' => 'wieder-angemeldet'], false);

        return view('newsletter.stand', ['stand' => 'dabei', 'kontakt' => $k->fresh()]);
    }

    public function oeffnen(string $token): Response
    {
        if ($v = NewsletterVersand::where('token', $token)->first()) {
            $this->versand->geoeffnet($v);
        }
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200, ['Content-Type' => 'image/gif', 'Cache-Control' => 'no-store, private']);
    }

    public function klick(Request $request, string $token): RedirectResponse
    {
        $u = (string) $request->query('u');
        abort_unless(str_starts_with($u, 'http://') || str_starts_with($u, 'https://'), 404);
        if ($v = NewsletterVersand::where('token', $token)->first()) {
            $this->versand->geklickt($v);
        }

        return redirect()->away($u);
    }

    public function web(string $token): View
    {
        $v = NewsletterVersand::where('token', $token)->with(['newsletter', 'kontakt'])->firstOrFail();

        return view('newsletter.web', ['n' => $v->newsletter, 'k' => $v->kontakt]);
    }

    /** Nur zurueck auf eine Website des Mandanten (oder dieselbe Domain), nie irgendwohin. */
    protected function zurueck(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || ! preg_match('~^https?://([^/]+)~i', $url, $m)) {
            return null;
        }
        $host = strtolower($m[1]);
        $erlaubt = array_filter([strtolower((string) parse_url((string) $this->current->get()?->setting('website'), PHP_URL_HOST)), strtolower((string) request()->getHost())]);
        foreach ($erlaubt as $e) {
            if ($host === $e || str_ends_with($host, '.'.$e) || str_ends_with($e, '.'.$host)) {
                return $url;
            }
        }

        return null;
    }
}
