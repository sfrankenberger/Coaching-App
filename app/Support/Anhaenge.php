<?php

namespace App\Support;

use App\Models\Anhang;
use App\Models\Event;
use App\Models\Note;
use App\Models\Reflection;
use App\Models\Resource;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Programs\Begleitung;
use App\Programs\ProgramAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Anhaengen, worum es geht: an eine Notiz, Aufgabe, Reflexion, Frage oder Nachricht kommt eine Aufgabe,
 * eine Notiz, eine Reflexion, ein Termin, eine Aufzeichnung, Material oder eine Lektion.
 * Eine Auswahl fuer alle Formulare, eine Suche, eine Pruefung (nur Eigenes oder Gemeinsames) und eine Karte.
 */
class Anhaenge
{
    /** Art => [Bezeichnung, Symbol] */
    public const ARTEN = [
        'task' => ['Aufgabe', 'list-check'],
        'note' => ['Notiz', 'feather'],
        'reflection' => ['Reflexion', 'pen-to-square'],
        'event' => ['Termin', 'calendar'],
        'resource' => ['Material', 'folder-open'],
        'unit' => ['Lektion', 'graduation-cap'],
    ];

    public function __construct(protected ProgramAccess $access, protected Begleitung $begleitung) {}

    /** Die letzten fuenf je Art, fuer die Auswahl im Formular. */
    public function auswahl(User $user): array
    {
        return $this->bestand($user, null)->map(fn (Model $m) => $this->karte($m))->values()->all();
    }

    /** Alles, was zum Suchwort passt, quer durch alle Arten. */
    public function suche(User $user, string $q, int $limit = 30): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return $this->auswahl($user);
        }

        return $this->bestand($user, $q)->take($limit)->map(fn (Model $m) => $this->karte($m))->values()->all();
    }

    /** "art:nummer" auf das Ziel aufloesen, nur wenn die Person es anhaengen darf. */
    public function finden(string $ref, User $user): ?Model
    {
        [$art, $id] = array_pad(explode(':', $ref, 2), 2, null);
        if (! isset(self::ARTEN[$art]) || ! ctype_digit((string) $id)) {
            return null;
        }
        $klasse = Relation::getMorphedModel($art);
        $ziel = $klasse ? $klasse::query()->find((int) $id) : null;

        return $ziel && $this->darf($user, $ziel) ? $ziel : null;
    }

    /** Anhaengen darf man nur, was einem gehoert oder was mit einem geteilt ist. */
    public function darf(User $user, Model $ziel): bool
    {
        if ($ziel instanceof Task || $ziel instanceof Note || $ziel instanceof Reflection) {
            if ((int) $ziel->user_id === $user->id) {
                return true;
            }
            $sicht = (string) ($ziel->visibility ?? 'private');
            if ($sicht === 'private') {
                return false;
            }
            if ($user->canManageCurrentTenant()) {
                return true;
            }

            return in_array($sicht, ['program', 'all'], true) && $ziel->program_id && $this->access->programIdsFor($user)->contains($ziel->program_id);
        }
        if ($ziel instanceof Event || $ziel instanceof Resource) {
            return Gate::forUser($user)->allows('view', $ziel);
        }
        if ($ziel instanceof Unit) {
            return $ziel->program && $this->access->canView($user, $ziel->program);
        }

        return false;
    }

    /** Die Anhaenge eines Eintrags setzen (ersetzt die bisherigen). Unerlaubtes wird still weggelassen. */
    public function speichern(Model $item, ?array $refs, User $user): void
    {
        $refs = collect($refs ?? [])->filter(fn ($r) => is_string($r))->unique()->values();
        $item->anhaenge()->delete();
        $position = 0;
        foreach ($refs as $ref) {
            if ($ziel = $this->finden($ref, $user)) {
                Anhang::create([
                    'anhang_an_type' => $item->getMorphClass(),
                    'anhang_an_id' => $item->getKey(),
                    'ziel_type' => $ziel->getMorphClass(),
                    'ziel_id' => $ziel->getKey(),
                    'position' => $position++,
                ]);
            }
        }
        $item->unsetRelation('anhaenge');
    }

    /** Karte zum Anzeigen: Art, Bezeichnung, Symbol, Titel, Zusatz und Ziel. */
    public function karte(Model $ziel): array
    {
        $art = $ziel->getMorphClass();
        [$label, $icon] = self::ARTEN[$art] ?? ['Anhang', 'paperclip'];
        $karte = ['ref' => $art.':'.$ziel->getKey(), 'typ' => $art, 'label' => $label, 'icon' => $icon, 'titel' => '', 'zusatz' => '', 'url' => null];

        if ($ziel instanceof Task) {
            $karte['titel'] = $ziel->title;
            $karte['zusatz'] = $ziel->due_at ? 'bis '.$ziel->due_at->translatedFormat('j. M') : $ziel->created_at?->translatedFormat('j. M');
            $karte['url'] = route('aufgaben.index').'#aufgabe-'.$ziel->id;
        } elseif ($ziel instanceof Note) {
            $karte['titel'] = $ziel->title ?: Str::limit(trim((string) $ziel->body), 70);
            $karte['zusatz'] = $ziel->created_at?->translatedFormat('j. M');
            $karte['url'] = route('notizen.index').'#notiz-'.$ziel->id;
        } elseif ($ziel instanceof Reflection) {
            $karte['titel'] = $ziel->week_label ?: 'Reflexion vom '.$ziel->created_at?->translatedFormat('j. F');
            $karte['zusatz'] = $ziel->created_at?->translatedFormat('j. M');
            $karte['url'] = route('reflexion.index').'#reflexion-'.$ziel->id;
        } elseif ($ziel instanceof Event) {
            $aufzeichnung = $ziel->hasRecording() && $ziel->isPast();
            $karte['label'] = $aufzeichnung ? 'Aufzeichnung' : 'Termin';
            $karte['icon'] = $aufzeichnung ? 'circle-play' : 'calendar';
            $karte['titel'] = $ziel->title;
            $karte['zusatz'] = $aufzeichnung ? $ziel->starts_at->translatedFormat('j. M Y') : Zeit::wannKurz($ziel->starts_at);
            $karte['url'] = route('termine.show', $ziel);
        } elseif ($ziel instanceof Resource) {
            $karte['titel'] = $ziel->title;
            $karte['zusatz'] = $ziel->typeLabel();
            $karte['url'] = $ziel->hatSeite() ? route('material.show', $ziel) : ($ziel->target() ?: route('material.index'));
        } elseif ($ziel instanceof Unit) {
            $karte['titel'] = $ziel->title;
            $karte['zusatz'] = $ziel->program?->title ?? '';
            $karte['url'] = $ziel->program ? route('kurse.einheit', [$ziel->program, $ziel]) : null;
        }

        return $karte;
    }

    /** Karte zu "art:nummer", oder null, wenn es das nicht (mehr) gibt oder nicht erlaubt ist. */
    public function karteRef(string $ref, User $user): ?array
    {
        $ziel = $this->finden($ref, $user);

        return $ziel ? $this->karte($ziel) : null;
    }

    /** Die Kandidaten der Person, ohne Suchwort die letzten fuenf je Art, mit Suchwort alles Passende. */
    protected function bestand(User $user, ?string $q): Collection
    {
        $wie = $q !== null ? '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%' : null;
        $n = $q !== null ? 20 : 5;
        $aus = collect();

        foreach ([Task::class, Note::class, Reflection::class] as $klasse) {
            $aus = $aus->merge($klasse::query()->where('user_id', $user->id)
                ->when($klasse === Task::class, fn (Builder $b) => $b->whereNull('done_at'))
                ->when($wie, fn (Builder $b) => $b->where(fn (Builder $w) => $w->where($klasse === Reflection::class ? 'week_label' : 'title', 'like', $wie)
                    ->when($klasse !== Task::class, fn (Builder $x) => $x->orWhere($klasse === Reflection::class ? 'went_well' : 'body', 'like', $wie))))
                ->latest()->limit($n)->get());
        }

        $termine = $this->begleitung->eventsQuery($user)->with('program:id,title');
        if ($wie) {
            $aus = $aus->merge((clone $termine)->where('title', 'like', $wie)->orderByDesc('starts_at')->limit($n)->get());
        } else {
            $aus = $aus->merge((clone $termine)->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get());
            $aus = $aus->merge((clone $termine)->where('starts_at', '<', now())->whereNotNull('recording_url')->orderByDesc('starts_at')->limit(5)->get());
        }

        $aus = $aus->merge($this->begleitung->resourcesQuery($user)
            ->when($wie, fn (Builder $b) => $b->where('title', 'like', $wie))
            ->orderByDesc('created_at')->limit($n)->get());

        if ($wie) {
            $aus = $aus->merge(Unit::query()->whereIn('program_id', $this->access->programIdsFor($user))
                ->where('is_published', true)->where('title', 'like', $wie)->with('program:id,title,slug')->orderBy('title')->limit($n)->get());
        }

        return $aus;
    }
}
