<?php

namespace App\Notifications;

use App\Content\Inhalte;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Message;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Observers\EventObserver;
use App\Programs\Begleitung;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Collection;

/**
 * Die wiederkehrenden Laeufe je Mandant: Termin-Erinnerungen, Nachfassen bei
 * ungelesenen Nachrichten, Aufgaben-Hinweise, Abendmail.
 * Alle laufen im Scheduler ueber alle aktiven Mandanten.
 */
class Runden
{
    public function __construct(protected CurrentTenant $current, protected Notifier $notifier) {}

    /** Fuer jeden aktiven Mandanten im richtigen Kontext ausfuehren. */
    public function jeMandant(callable $fn): array
    {
        $out = [];
        foreach (Tenant::where('is_active', true)->get() as $tenant) {
            $out[$tenant->slug] = $this->current->run($tenant, fn () => $fn($tenant));
        }

        return $out;
    }

    /**
     * Termin-Erinnerungen: am Tag um 9 Uhr, und eine Stunde vorher.
     * Laeuft alle zehn Minuten.
     */
    public function terminErinnerungen(): int
    {
        $tz = $this->current->get()?->timezone ?: config('app.timezone');
        $jetzt = now($tz);
        $n = 0;

        $heute = Event::query()->where('is_published', true)->where('all_day', false)
            ->whereBetween('starts_at', [$jetzt->copy()->startOfDay()->utc(), $jetzt->copy()->endOfDay()->utc()])
            ->get();

        foreach ($heute as $event) {
            $start = $event->starts_at->copy()->setTimezone($tz);
            $zeit = $start->format('H:i');

            if ($jetzt->hour >= 9 && $start->gt($jetzt) && ! $event->reminded_day_at) {
                $n += $this->melden($event, "Heute {$zeit} Uhr: {$event->title}", "Heute um {$zeit} Uhr. Du findest alles auf der Terminseite.", 'tag');
                $event->forceFill(['reminded_day_at' => now()])->saveQuietly();
            }
            $vorlauf = $jetzt->diffInMinutes($start, false);
            if ($vorlauf > 0 && $vorlauf <= 70 && ! $event->reminded_hour_at) {
                $n += $this->melden($event, "In einer Stunde: {$event->title}", "Es geht um {$zeit} Uhr los. Tipp hier, dann bist du direkt drin.", 'stunde');
                $event->forceFill(['reminded_hour_at' => now()])->saveQuietly();
            }
        }

        return $n;
    }

    protected function melden(Event $event, string $titel, string $text, string $wann): int
    {
        $ids = EventObserver::recipients($event);
        if ($ids->isEmpty()) {
            return 0;
        }
        $report = $this->notifier->send($ids, new Nachricht(
            titel: $titel,
            text: $text,
            url: $event->zoom_url ?: route('termine.show', $event),
            anlass: 'termin',
            tag: 'termin-'.$event->id.'-'.$wann,
            knopf: $event->zoom_url ? 'Zum Zoom-Call' : 'Zum Termin',
        ));

        return count(array_filter($report));
    }

    /**
     * Nachfassen: eine 1:1-Nachricht, die nach 20 Minuten noch ungelesen ist,
     * geht einmal per Mail raus (auch an Personen mit Push).
     */
    public function nachfassen(): int
    {
        $n = 0;
        $messages = Message::query()->whereNull('nudged_at')->where('created_at', '<=', now()->subMinutes(20))
            ->where('created_at', '>=', now()->subDay())->whereNotNull('user_id')
            ->with(['conversation.participants', 'user:id,name'])->get();

        foreach ($messages as $msg) {
            $conv = $msg->conversation;
            $msg->forceFill(['nudged_at' => now()])->saveQuietly();
            if (! $conv || ! $conv->isDirect()) {
                continue;
            }
            $empfaenger = $conv->participants->where('user_id', '!=', $msg->user_id);
            foreach ($empfaenger as $p) {
                if ($p->last_read_at && $p->last_read_at->gte($msg->created_at)) {
                    continue;
                }
                $user = User::find($p->user_id);
                if (! $user || ! filled($user->email) || ! $user->membershipIn()?->isActive()) {
                    continue;
                }
                $user->notify(new AppNotification(new Nachricht(
                    titel: $msg->user->vorname().' hat dir geschrieben',
                    text: $msg->excerpt(40),
                    url: route('gespraech.show', $conv),
                    anlass: 'chat',
                    tag: 'chat-'.$msg->id,
                    knopf: 'Antworten',
                ), ['mail'], $this->current->id()));
                $n++;
            }
        }

        return $n;
    }

    /** Morgens und abends ein Hinweis auf offene Aufgaben (nur Push/Telegram, keine Mail). */
    public function aufgabenHinweis(string $wann): int
    {
        $n = 0;
        $tz = $this->current->get()?->timezone ?: config('app.timezone');
        $heute = now($tz)->toDateString();

        $offen = Task::query()->open()->where(fn ($q) => $q->whereDate('due_at', '<=', $heute)->orWhere('is_daily', true))
            ->get()->groupBy('user_id');

        foreach ($offen as $userId => $tasks) {
            $user = User::find($userId);
            if (! $user || ! $this->notifier->hasPushOrTelegram($user)) {
                continue;
            }
            $anzahl = $tasks->count();
            $titel = $wann === 'morgen' ? ($anzahl === 1 ? 'Eine Aufgabe für heute' : "{$anzahl} Aufgaben für heute") : ($anzahl === 1 ? 'Noch eine Aufgabe offen' : "Noch {$anzahl} Aufgaben offen");
            $report = $this->notifier->send([$user], new Nachricht(
                titel: $titel,
                text: $tasks->take(3)->pluck('title')->join(', ').($anzahl > 3 ? ' ...' : ''),
                url: route('aufgaben.index'),
                anlass: 'aufgabe_erinnerung',
                tag: 'aufgaben-'.$wann,
                mailWennKeinPush: false,
            ));
            $n += count(array_filter($report));
        }

        return $n;
    }

    /**
     * Abendmail: an Personen ohne Push oder Telegram, nur wenn seit der letzten
     * Sammelmail etwas Neues da ist. Hoechstens einmal am Tag.
     */
    public function abendmail(): int
    {
        $n = 0;
        $memberships = Membership::query()->where('status', 'active')->with('user')->get();

        foreach ($memberships as $m) {
            $user = $m->user;
            if (! $user || ! filled($user->email) || $user->canManageCurrentTenant()) {
                continue;
            }
            if (! $this->notifier->wants($m, 'abendmail') || $this->notifier->hasPushOrTelegram($user)) {
                continue;
            }
            if ($m->digest_sent_at && $m->digest_sent_at->isToday()) {
                continue;
            }
            $seit = $m->digest_sent_at ?? now()->subDays(3);
            $neues = $this->neuesFuer($user, $seit);
            if ($neues->isEmpty()) {
                continue;
            }

            $text = $neues->map(fn ($e) => '• '.$e['titel'].($e['text'] ? ': '.$e['text'] : ''))->join("\n");
            $user->notify(new AppNotification(new Nachricht(
                titel: $neues->count() === 1 ? 'Etwas Neues in deinem Bereich' : $neues->count().' neue Sachen in deinem Bereich',
                text: "Seit deinem letzten Besuch ist dazugekommen:\n\n".$text,
                url: url('/'),
                anlass: 'abendmail',
                tag: 'abendmail',
                knopf: 'Ansehen',
            ), ['mail'], $this->current->id()));
            $m->forceFill(['digest_sent_at' => now()])->save();
            $n++;
        }

        return $n;
    }

    /**
     * Was seit einem Zeitpunkt fuer eine Person neu ist: Nachrichten, Beitraege, Podcast,
     * Aufzeichnungen, Termine, Material, Aufgaben. Je Eintrag Titel, Herkunft, Icon, Link
     * und Zeit, neueste zuerst.
     */
    public function neuesFuer(User $user, \DateTimeInterface $seit, int $limit = 30): Collection
    {
        $out = collect();
        $begleitung = app(Begleitung::class);
        $inhalte = app(Inhalte::class);

        foreach (Conversation::query()->whereHas('participants', fn ($p) => $p->where('user_id', $user->id))->get() as $c) {
            $neu = $c->messages()->where('user_id', '!=', $user->id)->where('created_at', '>', $seit)->with('user:id,name')->latest()->get();
            if ($neu->isEmpty()) {
                continue;
            }
            $von = $neu->first()->user?->vorname() ?? 'Jemand';
            $out->push([
                'titel' => $c->type === 'direct' ? $von.' hat dir geschrieben' : 'Neu im Austausch: '.($c->title ?: 'Gruppe'),
                'text' => $neu->count() > 1 ? $neu->count().' Nachrichten' : $neu->first()->excerpt(10),
                'herkunft' => $c->type === 'direct' ? 'Persönlich' : 'Austausch',
                'icon' => 'comments',
                'url' => route('gespraech.show', $c),
                'zeit' => $neu->first()->created_at,
            ]);
        }

        foreach ($inhalte->postsQuery($user)->published()->where('published_at', '>', $seit)->latest('published_at')->limit(10)->get() as $p) {
            $out->push(['titel' => $p->title, 'text' => $p->excerptText(80), 'herkunft' => $p->typeLabel(), 'icon' => $p->type === 'neuigkeit' ? 'bullhorn' : 'lightbulb', 'url' => route('impulse.show', $p), 'zeit' => $p->published_at]);
        }
        foreach ($inhalte->episodesQuery($user)->where('published_at', '>', $seit)->latest('published_at')->limit(5)->get() as $f) {
            $out->push(['titel' => $f->title, 'text' => $f->durationLabel() ?? '', 'herkunft' => 'Podcast', 'icon' => 'microphone', 'url' => route('impulse.folge', $f), 'zeit' => $f->published_at]);
        }
        foreach ($begleitung->eventsQuery($user)->whereNotNull('recording_url')->where('updated_at', '>', $seit)->get() as $e) {
            $out->push(['titel' => 'Aufzeichnung: '.$e->title, 'text' => $e->starts_at->translatedFormat('j. F'), 'herkunft' => 'Aufzeichnung', 'icon' => 'circle-play', 'url' => route('termine.show', $e), 'zeit' => $e->updated_at]);
        }
        foreach ($begleitung->eventsQuery($user)->where('created_at', '>', $seit)->where('starts_at', '>', now())->get() as $e) {
            $out->push(['titel' => 'Neuer Termin: '.$e->title, 'text' => $e->starts_at->translatedFormat('D, j. F, H:i').' Uhr', 'herkunft' => 'Termin', 'icon' => 'calendar', 'url' => route('termine.show', $e), 'zeit' => $e->created_at]);
        }
        foreach ($begleitung->resourcesQuery($user)->where('resources.created_at', '>', $seit)->get() as $r) {
            $out->push(['titel' => $r->title, 'text' => $r->typeLabel(), 'herkunft' => 'Material', 'icon' => 'folder-open', 'url' => route('material.index'), 'zeit' => $r->created_at]);
        }
        foreach (Task::where('user_id', $user->id)->whereNotNull('assigned_by')->where('created_at', '>', $seit)->open()->get() as $t) {
            $out->push(['titel' => $t->title, 'text' => $t->due_at ? 'bis '.$t->due_at->translatedFormat('j. F') : '', 'herkunft' => 'Aufgabe', 'icon' => 'list-check', 'url' => route('aufgaben.index'), 'zeit' => $t->created_at]);
        }

        return $out->sortByDesc(fn ($e) => $e['zeit']?->getTimestamp() ?? 0)->values()->take($limit);
    }
}
