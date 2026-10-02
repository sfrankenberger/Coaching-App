<?php

namespace App\Coach;

use App\Chat\Chat;
use App\Models\Event;
use App\Models\Kontakt;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Question;
use App\Models\Verkauf;
use App\Notifications\Notifier;
use App\Support\Zeit;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Die Startseite der Coachin als Arbeitsliste (wie lea-start-lea): kein Neuigkeitenstrom,
 * sondern was auf sie wartet. Wer wartet auf Antwort, welche Fragen sind ohne Antwort, was wurde
 * mit ihr geteilt, von wem sie lange nichts gehoert hat, wer neu dabei ist, was als Naechstes
 * ansteht, dazu die Zahlen der Woche. Aufzeichnungen gehen von selbst raus (Wache), darum gibt
 * es hier keine Freigabe. Testkonten bleiben draussen. Ist alles leer, steht das in einem Satz.
 */
class Arbeitsliste
{
    public function __construct(protected Lage $lage, protected Chat $chat, protected Neues $neues, protected Notifier $notifier, protected CurrentTenant $current) {}

    public function fuer(): array
    {
        $team = $this->chat->teamIds();
        $test = collect($this->notifier->testMode() ? (array) $this->current->get()?->setting('notifications.test_emails', []) : [])->map(fn ($e) => mb_strtolower($e));
        $istTest = fn ($user) => $user && $test->contains(mb_strtolower($user->email));

        $wartende = $this->wartende($team)->reject(fn ($z) => $istTest($z['user']));
        $geteilt = $this->geteilt();
        $fragen = $this->fragen($team)->reject(fn ($f) => $istTest($f->user))->take(6)->values();
        $still = $this->still()->reject(fn ($z) => $istTest($z['user']))->take(4)->values();
        $neu = $this->neuDabei()->reject(fn (Membership $m) => $istTest($m->user))->take(4)->values();
        $termine = Event::query()->where('is_published', true)->whereNotIn('type', Event::ALL_DAY_TYPES)
            ->where('starts_at', '>=', now()->subMinutes(45))->where('starts_at', '<', now()->addDays(8))
            ->with(['program:id,title', 'user:id,name'])->orderBy('starts_at')->limit(5)->get();
        // Laeuft gerade ein Call oder faengt er in den naechsten drei Stunden an, steht er ganz oben
        $gleich = $termine->first(fn (Event $e) => $e->isLive() || $e->starts_at->lt(now()->addHours(3)));

        $ruhig = [];
        if ($wartende->isEmpty()) {
            $ruhig[] = 'niemand wartet auf eine Antwort';
        }
        if ($fragen->isEmpty()) {
            $ruhig[] = 'alle Fragen sind beantwortet';
        }
        if ($geteilt->isEmpty()) {
            $ruhig[] = 'nichts Neues geteilt';
        }
        if ($termine->isEmpty()) {
            $ruhig[] = 'diese Woche nichts geplant';
        }
        $satz = null;
        if ($ruhig && $wartende->isEmpty() && $fragen->isEmpty()) {
            $letztes = array_pop($ruhig);
            $satz = 'Alles ruhig: '.($ruhig ? implode(', ', $ruhig).' und '.$letztes : $letztes).'.';
        }

        return [
            'wartende' => $wartende,
            'geteilt' => $geteilt,
            'fragen' => $fragen,
            'still' => $still,
            'neu' => $neu,
            'termine' => $termine,
            'gleich' => $gleich,
            'woche' => $this->woche(),
            'offen' => $wartende->count() + $geteilt->count() + $fragen->count(),
            'ruhig' => $satz,
        ];
    }

    /**
     * Mit dir geteilt, eine Zeile je Person: Antworten ueber alle Einheiten gebuendelt
     * ("hat 39 Antworten in 5 Einheiten geteilt"), dazu Reflexionen, Notizen, Aufgaben.
     * Sonst steht dieselbe Person sechsmal untereinander.
     */
    public function geteilt(int $max = 8): Collection
    {
        $zeilen = $this->neues->zeilen(7, 60)->filter(fn ($z) => in_array($z['art'], ['antwort', 'reflexion', 'aufgabe', 'notiz', 'aufgabe_geteilt'], true));

        return $zeilen->groupBy(fn ($z) => $z['wer'].'|'.$z['url'])->map(function (Collection $g) {
            $erste = $g->sortByDesc('zeit')->first();
            $antworten = $g->where('art', 'antwort');
            $teile = [];
            if ($antworten->isNotEmpty()) {
                $n = (int) $antworten->sum('anzahl');
                $e = $antworten->count();
                $teile[] = ($n === 1 ? 'eine Antwort' : "{$n} Antworten").($e > 1 ? " in {$e} Einheiten" : '');
            }
            foreach (['reflexion' => ['eine Reflexion', 'Reflexionen'], 'notiz' => ['eine Notiz', 'Notizen'], 'aufgabe_geteilt' => ['eine Aufgabe', 'Aufgaben']] as $art => [$eins, $viele]) {
                $n = $g->where('art', $art)->count();
                if ($n) {
                    $teile[] = $n === 1 ? $eins : "{$n} {$viele}";
                }
            }
            $erledigt = $g->where('art', 'aufgabe')->count();
            $was = $teile ? 'hat '.Str::of(implode(', ', $teile))->replaceLast(', ', ' und ').' geteilt' : '';
            if ($erledigt) {
                $was .= ($was ? ' und ' : 'hat ').($erledigt === 1 ? 'eine Aufgabe erledigt' : "{$erledigt} Aufgaben erledigt");
            }
            $details = $g->sortByDesc('zeit')->pluck('detail')->filter()->unique()->values();

            return [
                'art' => $erste['art'],
                'zeit' => $erste['zeit'],
                'wer' => $erste['wer'],
                'was' => $was,
                'detail' => $details->take(3)->implode(', ').($details->count() > 3 ? ' …' : ''),
                'url' => $erste['url'],
                'anzahl' => $g->count(),
            ];
        })->sortByDesc('zeit')->take($max)->values();
    }

    /** Von wem die Coachin lange nichts gehoert hat (Ampel gelb oder rot wegen Stille, Calls oder Aufgaben), ohne die, die ohnehin auf Antwort warten. */
    public function still(): Collection
    {
        return $this->lage->alle()->filter(fn ($z) => $z['stufe'] > 1 && ! $z['wartet'] && $z['entwurf'])
            ->map(fn ($z) => $z + ['nachfragen' => route('gespraech.show', ['gespraech' => $this->chat->directFor($z['user']), 'entwurf' => Lage::entwurf($z['entwurf'], $z['user']->vorname())])])
            ->values();
    }

    /** Wer in den letzten sieben Tagen dazugekommen ist. */
    public function neuDabei(): Collection
    {
        return Membership::query()->where('status', 'active')->whereIn('role', ['member', 'client', 'guest'])
            ->where(fn ($q) => $q->where('joined_at', '>=', now()->subDays(7))->orWhere(fn ($w) => $w->whereNull('joined_at')->where('created_at', '>=', now()->subDays(7))))
            ->with('user')->latest('created_at')->get()->filter(fn (Membership $m) => $m->user)->values();
    }

    /** Zahlen der letzten sieben Tage: neue Personen, Verkaeufe, Newsletter-Anmeldungen, Calls. */
    public function woche(): array
    {
        $seit = now()->subDays(7);
        $verkaeufe = Verkauf::where('created_at', '>=', $seit)->get();

        return [
            'personen' => Membership::where('status', 'active')->whereIn('role', ['member', 'client', 'guest'])->where('created_at', '>=', $seit)->count(),
            'verkaeufe' => $verkaeufe->count(),
            'umsatz' => (float) $verkaeufe->sum('betrag'),
            'waehrung' => $verkaeufe->first()?->waehrung ?? 'CHF',
            'kontakte' => Kontakt::whereNotNull('bestaetigt_at')->where('bestaetigt_at', '>=', $seit)->count(),
            'calls' => Event::where('is_published', true)->whereNotIn('type', Event::ALL_DAY_TYPES)->whereBetween('starts_at', [$seit, now()])->count(),
        ];
    }

    /** Wer im 1:1 zuletzt geschrieben hat und noch keine Antwort bekam, mit dem Anfang der Nachricht. */
    public function wartende(?Collection $team = null): Collection
    {
        $team ??= $this->chat->teamIds();
        $wartet = $this->lage->wartende($team);
        if ($wartet->isEmpty()) {
            return collect();
        }

        return Membership::query()->whereIn('user_id', $wartet->keys())->where('status', 'active')->with('user')->get()
            ->filter(fn (Membership $m) => $m->user)
            ->map(function (Membership $m) use ($wartet) {
                $conv = $this->chat->directFor($m->user, false);
                $letzte = $conv ? Message::where('conversation_id', $conv->id)->where('user_id', $m->user_id)->latest('id')->first() : null;

                return [
                    'membership' => $m,
                    'user' => $m->user,
                    'seit' => $wartet[$m->user_id],
                    'wann' => Zeit::relativ($wartet[$m->user_id]),
                    'text' => $letzte ? ($letzte->body ? Str::limit($letzte->body, 90) : ($letzte->audio_path ? 'Sprachnachricht' : 'Anhang')) : '',
                ];
            })->sortBy(fn ($z) => $z['seit']->getTimestamp())->values();
    }

    /** Offene Fragen aus den Kursraeumen, auf die noch niemand aus dem Team geantwortet hat. */
    public function fragen(?Collection $team = null): Collection
    {
        $team ??= $this->chat->teamIds();

        return Question::query()->whereIn('status', ['offen', 'call'])->with(['user:id,name,email', 'program:id,title,slug'])
            ->withCount('answers')->orderBy('created_at')->limit(25)->get()
            ->reject(fn (Question $f) => $f->answers()->whereIn('user_id', $team)->exists());
    }
}
