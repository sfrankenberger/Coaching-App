<?php

namespace App\Coach;

use App\Chat\Chat;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Question;
use App\Notifications\Notifier;
use App\Support\Zeit;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Die Startseite der Coachin als Arbeitsliste (wie lea-start-lea): kein Neuigkeitenstrom,
 * sondern was auf sie wartet. Wer wartet auf Antwort, was wurde mit ihr geteilt, welche Fragen
 * sind ohne Antwort, welche Aufzeichnung wartet auf Freigabe, was steht als Naechstes an.
 * Testkonten bleiben draussen. Ist alles leer, steht das in einem Satz.
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
        $geteilt = $this->neues->zeilen(7, 8)->filter(fn ($z) => in_array($z['art'], ['antwort', 'reflexion', 'aufgabe'], true))->values();
        $fragen = $this->fragen($team)->reject(fn ($f) => $istTest($f->user))->take(6)->values();
        $freigaben = Event::query()->where('is_published', true)->whereNotNull('recording_url')->whereNotNull('summary')
            ->whereNull('recording_notified_at')->orderByDesc('starts_at')->limit(4)->get();
        $termine = Event::query()->where('is_published', true)->whereNotIn('type', Event::ALL_DAY_TYPES)
            ->where('starts_at', '>=', now()->subMinutes(45))->where('starts_at', '<', now()->addDays(8))
            ->with(['program:id,title', 'user:id,name'])->orderBy('starts_at')->limit(5)->get();

        $ruhig = [];
        if ($wartende->isEmpty()) {
            $ruhig[] = 'niemand wartet auf eine Antwort';
        }
        if ($geteilt->isEmpty()) {
            $ruhig[] = 'nichts Neues geteilt';
        }
        if ($fragen->isEmpty()) {
            $ruhig[] = 'alle Fragen sind beantwortet';
        }
        if ($termine->isEmpty()) {
            $ruhig[] = 'diese Woche nichts geplant';
        }
        $satz = null;
        if ($ruhig && $wartende->isEmpty() && $fragen->isEmpty() && $freigaben->isEmpty()) {
            $letztes = array_pop($ruhig);
            $satz = 'Alles ruhig: '.($ruhig ? implode(', ', $ruhig).' und '.$letztes : $letztes).'.';
        }

        return [
            'wartende' => $wartende,
            'geteilt' => $geteilt,
            'fragen' => $fragen,
            'freigaben' => $freigaben,
            'termine' => $termine,
            'offen' => $wartende->count() + $geteilt->count() + $fragen->count() + $freigaben->count(),
            'ruhig' => $satz,
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
