<?php

namespace App\Coach;

use App\Chat\Chat;
use App\Filament\Coach\Resources\Memberships\MembershipResource;
use App\Models\Answer;
use App\Models\Comment;
use App\Models\Membership;
use App\Models\Note;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Zurueckschreiben auf Geteiltes (wie der Kommentarfuss in lea-coach-kurs und lea-aufgaben):
 * Die Person kommentiert ihre eigenen Eintraege, die Coachin und das Team alles, was geteilt ist.
 * Die jeweils andere Seite bekommt Bescheid.
 */
class Kommentare
{
    public const TYPEN = [
        'reflection' => Reflection::class,
        'note' => Note::class,
        'task' => Task::class,
        'answer' => Answer::class,
    ];

    public function __construct(protected Notifier $notifier, protected Chat $chat) {}

    public function finden(string $typ, int $id): ?Model
    {
        $klasse = self::TYPEN[$typ] ?? null;

        return $klasse ? $klasse::find($id) : null;
    }

    public function typ(Model $item): string
    {
        return array_search($item::class, self::TYPEN, true);
    }

    /** Ist der Eintrag mit der Coachin geteilt? */
    public function geteilt(Model $item): bool
    {
        return match (true) {
            $item instanceof Reflection => $item->visibility !== 'private',
            $item instanceof Note => in_array($item->visibility, ['coach', 'program', 'all'], true),
            $item instanceof Task => $item->visibility !== 'private' || $item->assigned_by !== null,
            $item instanceof Answer => (bool) $item->shared_with_coach,
            default => false,
        };
    }

    public function darf(User $user, Model $item): bool
    {
        return $item->user_id === $user->id || ($user->canManageCurrentTenant() && $this->geteilt($item));
    }

    public function schreiben(User $user, Model $item, string $text): Comment
    {
        abort_unless($this->darf($user, $item), 403);
        $kommentar = Comment::create([
            'user_id' => $user->id,
            'commentable_type' => $this->typ($item),
            'commentable_id' => $item->getKey(),
            'body' => trim($text),
        ]);

        if ($item->user_id === $user->id) {
            // Die Person schreibt: das Team erfaehrt es, falls der Eintrag geteilt ist
            if ($this->geteilt($item)) {
                $membership = Membership::where('user_id', $user->id)->first();
                $this->notifier->send($this->chat->teamIds()->reject(fn ($id) => $id === $user->id), new Nachricht(
                    titel: $user->vorname().' hat kommentiert',
                    text: Str::limit($kommentar->body, 140),
                    url: $membership ? MembershipResource::getUrl('dossier', ['record' => $membership], panel: 'coach') : null,
                    anlass: 'kommentar',
                    tag: 'kommentar-'.$this->typ($item).'-'.$item->getKey(),
                ));
            }
        } else {
            $this->notifier->send([$item->user_id], new Nachricht(
                titel: $user->vorname().' hat dir zurückgeschrieben',
                text: Str::limit($kommentar->body, 140),
                url: $this->urlFuerPerson($item),
                anlass: 'kommentar',
                tag: 'kommentar-'.$this->typ($item).'-'.$item->getKey(),
                knopf: 'Ansehen',
            ));
        }

        return $kommentar;
    }

    /** Wo die Person den Eintrag sieht. */
    public function urlFuerPerson(Model $item): string
    {
        return match (true) {
            $item instanceof Reflection => route('reflexion.index').'#reflexion-'.$item->id,
            $item instanceof Note => route('notizen.index').'#notiz-'.$item->id,
            $item instanceof Task => route('aufgaben.index').'#aufgabe-'.$item->id,
            $item instanceof Answer && $item->exercise?->unit?->program => route('kurse.einheit', [$item->exercise->unit->program, $item->exercise->unit]).'#uebung-'.$item->exercise_id,
            default => route('home'),
        };
    }
}
