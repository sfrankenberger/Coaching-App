<?php

namespace App\Coach;

use App\Chat\Chat;
use App\Filament\Coach\Resources\Memberships\MembershipResource;
use App\Models\Membership;
use App\Models\Note;
use App\Models\Program;
use App\Models\Reflection;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Programs\ProgramAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Geteiltes aus dem Kurs (wie lea-community-geteilt): was jemand fuer den Kurs oder die Community freigibt
 * (Notiz, Aufgabe, Reflexion), sehen die anderen in der Community, mit Reaktionen und Kommentaren.
 * Dazu: wer darf einen geteilten Eintrag sehen, und das Team erfaehrt, wenn etwas mit ihm geteilt wird.
 */
class Geteilt
{
    public const TYPEN = ['note' => Note::class, 'task' => Task::class, 'reflection' => Reflection::class];

    public function __construct(protected ProgramAccess $access, protected Chat $chat, protected Notifier $notifier, protected Kommentare $kommentare) {}

    public function finden(string $typ, int $id): ?Model
    {
        $klasse = self::TYPEN[$typ] ?? null;

        return $klasse ? $klasse::find($id) : null;
    }

    /** Eigenes immer, das Team alles Geteilte, der Kurs was "im Kurs" steht, alle was "in der Community" steht. */
    public function darfSehen(User $user, Model $item): bool
    {
        if ((int) $item->user_id === $user->id) {
            return true;
        }
        $sicht = (string) ($item->visibility ?? 'private');
        if ($sicht === 'private') {
            return false;
        }
        if ($user->canManageCurrentTenant()) {
            return $this->kommentare->geteilt($item);
        }
        if ($sicht === 'all') {
            return true;
        }

        return $sicht === 'program' && $item->program_id && $this->access->programIdsFor($user)->contains($item->program_id);
    }

    /** Der Strom fuer die Community: Geteiltes der anderen, neueste zuerst, ohne das Team. */
    public function stream(User $user, ?Program $kurs = null, int $limit = 12): Collection
    {
        $team = $this->chat->teamIds();
        $kurse = $this->access->gemeinschaftFor($user)->pluck('id');
        $aus = collect();
        foreach (self::TYPEN as $klasse) {
            $aus = $aus->merge($klasse::query()
                ->whereIn('visibility', ['program', 'all'])
                ->whereNotIn('user_id', $team->push($user->id))
                ->when($kurs, fn (Builder $q) => $q->where('program_id', $kurs->id), fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('visibility', 'all')->orWhereIn('program_id', $kurse)))
                ->with(['user:id,name,avatar_path', 'program:id,title,color', 'anhaenge.ziel', 'reactions', 'comments.user:id,name'])
                ->latest()->limit($limit)->get());
        }

        return $aus->sortByDesc('created_at')->take($limit)->values();
    }

    /** Bezeichnung und Symbol je Art. */
    public function label(Model $item): array
    {
        return match (true) {
            $item instanceof Task => ['Aufgabe', 'list-check'],
            $item instanceof Reflection => ['Reflexion', 'pen-to-square'],
            default => ['Notiz', 'feather'],
        };
    }

    /** Die Person teilt etwas: das Team erfaehrt es (Glocke, Push oder Mail je nach Einstellung). */
    public function melden(User $user, Model $item): void
    {
        [$label] = $this->label($item);
        $membership = Membership::where('user_id', $user->id)->first();
        $text = $item instanceof Reflection ? ($item->week_label ?: 'Reflexion') : ($item->title ?: Str::limit((string) $item->body, 120));
        $this->notifier->send($this->chat->teamIds()->reject(fn ($id) => $id === $user->id), new Nachricht(
            titel: $user->vorname().' teilt eine '.$label.' mit dir',
            text: (string) $text,
            url: $membership ? MembershipResource::getUrl('dossier', ['record' => $membership], panel: 'coach') : null,
            anlass: 'geteilt',
            tag: 'geteilt-'.$item->getMorphClass().'-'.$item->getKey(),
        ));
    }
}
