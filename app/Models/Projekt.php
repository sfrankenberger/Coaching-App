<?php

namespace App\Models;

use App\Support\Papierkorb\ImPapierkorb;
use App\Support\Protokoll\Protokolliert;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Ein Projekt ist ein Vorhaben, das laenger dauert als eine Woche. Es gehoert der Person, nicht einem Kurs.
 * Notizen, Aufgaben und Reflexionen koennen dazugehoeren. Der Prozessschritt sagt, wo das Projekt gerade steht.
 */
class Projekt extends Model
{
    use BelongsToTenant;
    use ImPapierkorb;
    use Protokolliert;

    protected $table = 'projekte';

    /** Die neun Schritte: Schluessel => [Name, Symbol, Farbe, Hinweis] (aus "Vom Kopf und Herz in die Welt"). */
    public const SCHRITTE = [
        'intention' => ['Intention', 'compass', '#B4795F', 'Du weisst, was du willst, und sprichst es aus.'],
        'ressourcen' => ['Ressourcen und Tools', 'toolbox', '#8C6A4F', 'Du sammelst, was dir hilft: Menschen, Wissen, Werkzeuge.'],
        'schock' => ['Altes Ich und Schockpunkt', 'bolt', '#B5544F', 'Das Alte wehrt sich. Genau hier steigen viele aus.'],
        'limitation' => ['Limitation', 'hand', '#9A6A7A', 'Die eigenen Grenzen zeigen sich, innen wie aussen.'],
        'aktion' => ['Volle Aktion', 'rocket', '#6E8B74', 'Du machst, auch wenn es noch nicht perfekt ist.'],
        'neuesich' => ['Das neue Ich', 'seedling', '#5F8C6A', 'Es fühlt sich normal an, was vorher gross war.'],
        'feiern' => ['Feiern', 'champagne-glasses', '#C89A4A', 'Du hältst inne und nimmst wahr, was gelungen ist.'],
        'loslassen' => ['Loslassen und Reflexion', 'wind', '#7C8C9A', 'Du lässt hinter dir, was nicht mehr passt, und schaust zurück.'],
        'traegt' => ['Es trägt', 'mountain-sun', '#5F6F7F', 'Es läuft ohne ständige Anstrengung weiter.'],
    ];

    public const FARBEN = ['#B4795F', '#6E8B74', '#7C8C9A', '#C89A4A', '#9A6A7A', '#B5544F', '#5F8C6A', '#8C6A4F'];

    public const ICONS = ['lightbulb', 'briefcase', 'heart', 'seedling', 'house', 'book', 'paintbrush', 'dumbbell', 'coins', 'people-group', 'compass', 'star'];

    protected $guarded = [];

    protected $attributes = ['visibility' => 'private', 'farbe' => '#B4795F', 'icon' => 'lightbulb'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'project_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    public function reflections(): HasMany
    {
        return $this->hasMany(Reflection::class, 'project_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    /** [Name, Symbol, Farbe, Hinweis] des gesetzten Schritts, oder null. */
    public function schrittInfo(): ?array
    {
        return $this->schritt ? (self::SCHRITTE[$this->schritt] ?? null) : null;
    }

    public function anzahl(): int
    {
        return $this->notes()->count() + $this->tasks()->count() + $this->reflections()->count();
    }
}
