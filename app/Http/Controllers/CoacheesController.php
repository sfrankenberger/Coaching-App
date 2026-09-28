<?php

namespace App\Http\Controllers;

use App\Ai\Assistent;
use App\Chat\Chat;
use App\Coach\Lage;
use App\Coach\Neues;
use App\Models\CoachNote;
use App\Models\Membership;
use App\Models\ProgramMember;
use App\Notifications\Notifier;
use App\Shop\Zugang;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Coachees in der App-Huelle (wie lea-coachees, fuers Handy): Ampel, Freigegebenes, Personenkarten
 * mit Suche und Sortierung, dazu die Auskunft ("Frag mich etwas zu deinem Betrieb"). Nur fuer das Team.
 */
class CoacheesController extends Controller
{
    public function __construct(protected Lage $lage, protected Neues $neues, protected Chat $chat) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->canManageCurrentTenant(), 403);
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        $sort = in_array($request->query('sort'), ['aktiv', 'name'], true) ? $request->query('sort') : 'termin';
        $alle = $this->lage->alle();
        $testAdressen = collect(app(Notifier::class)->testMode() ? (array) app(CurrentTenant::class)->get()?->setting('notifications.test_emails', []) : []);
        $imProgramm = ProgramMember::query()->pluck('user_id')->flip();

        $karten = $alle->map(function (array $z) use ($imProgramm, $testAdressen) {
            /** @var Membership $m */
            $m = $z['membership'];
            $z['begleitet'] = $m->role->value === 'client' || $imProgramm->has($m->user_id) || (bool) $z['wartet'];
            $z['test'] = $testAdressen->contains(mb_strtolower($m->user->email));
            $z['dossier'] = route('coachees.show', $m);
            $z['gespraech'] = route('gespraech.show', ['gespraech' => $this->chat->directFor($m->user)]);
            $z['nachfragen'] = route('gespraech.show', ['gespraech' => $this->chat->directFor($m->user), 'entwurf' => Lage::entwurf($z['entwurf'], $m->user->vorname())]);

            return $z;
        });
        if ($q !== '') {
            $karten = $karten->filter(fn ($z) => str_contains(mb_strtolower($z['user']->name.' '.$z['user']->email), $q));
        }
        $karten = match ($sort) {
            'aktiv' => $karten->sortByDesc(fn ($z) => $z['zuletzt']?->getTimestamp() ?? 0),
            'name' => $karten->sortBy(fn ($z) => mb_strtolower($z['user']->name)),
            default => $karten->sortBy(fn ($z) => [$z['wartet'] ? 0 : 1, $z['naechster']?->starts_at?->getTimestamp() ?? PHP_INT_MAX, mb_strtolower($z['user']->name)]),
        };

        return view('coachees.index', [
            'q' => $q,
            'sort' => $sort,
            'ampel' => $alle->where('stufe', '>', 1)->take(12)->map(fn ($z) => $z + [
                'nachfragen' => route('gespraech.show', ['gespraech' => $this->chat->directFor($z['user']), 'entwurf' => Lage::entwurf($z['entwurf'], $z['user']->vorname())]),
                'dossier' => route('coachees.show', $z['membership']),
            ]),
            'neues' => $this->neues->zeilen(7, 8),
            'begleitet' => $karten->where('begleitet', true)->values(),
            'kontakte' => $karten->where('begleitet', false)->values(),
            'wartend' => $alle->whereNotNull('wartet')->count(),
            'beispiele' => ['Was hat '.($alle->first()['user']->vorname() ?? 'Anna').' gebucht?', 'Wer war beim letzten Call dabei?', 'Wo trage ich meine Buchungszeiten ein?'],
            'rundnachricht' => route('filament.coach.pages.rundnachricht'),
            'waehrung' => app(CurrentTenant::class)->get()?->currency ?: 'CHF',
        ]);
    }

    /** Neue Person anlegen (wie im alten Bereich): Name, Mail, Telefon, auf Wunsch gleich die Willkommensmail. */
    public function anlegen(Request $request, Zugang $zugang): RedirectResponse
    {
        abort_unless($request->user()->canManageCurrentTenant(), 403);
        $data = $request->validate([
            'vorname' => ['required', 'string', 'max:80'],
            'nachname' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'rolle' => ['nullable', 'in:member,client'],
            'notiz' => ['nullable', 'string', 'max:2000'],
            'mail' => ['nullable', 'boolean'],
        ]);
        [$user, $neu] = $zugang->ensureUser($data['email'], trim($data['vorname'].' '.($data['nachname'] ?? '')));
        if (filled($data['phone'] ?? null)) {
            $user->forceFill(['phone' => trim($data['phone'])])->save();
        }
        $m = $user->membershipIn();
        if (($data['rolle'] ?? 'member') === 'client' && $m && ! $m->role->canManage()) {
            $m->forceFill(['role' => 'client'])->save();
        }
        if (filled($data['notiz'] ?? null)) {
            CoachNote::create(['user_id' => $user->id, 'author_id' => $request->user()->id, 'body' => trim($data['notiz'])]);
        }
        if ($data['mail'] ?? false) {
            $zugang->welcome($user);
        }

        return redirect()->route('coachees.show', $m)->with('meldung', $neu ? $user->vorname().' ist angelegt.' : $user->vorname().' war schon da, ich habe die Angaben übernommen.');
    }

    /** Auskunft: die Frage geht an den Assistenten, zurueck kommt ein HTML-Stueck. */
    public function frage(Request $request): Response
    {
        abort_unless($request->user()->canManageCurrentTenant(), 403);
        $frage = trim((string) $request->input('frage', ''));
        if (mb_strlen($frage) < 3) {
            return response(view('coachees._antwort', ['a' => null, 'msg' => 'Stell mir eine ganze Frage.'])->render(), 422);
        }

        return response(view('coachees._antwort', ['a' => app(Assistent::class)->antwort($frage, $request->user()), 'msg' => null])->render());
    }
}
