<?php

namespace App\Models;

use App\Models\Concerns\HatAnhaenge;
use App\Tenancy\Branding;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Notiz einer Person, optional an etwas gehaengt (Einheit, Termin ...).
 * Sichtbarkeit: private | coach | program | all.
 */
class Note extends Model
{
    use BelongsToTenant;
    use HatAnhaenge;

    public const VISIBILITIES = [
        'private' => 'Nur ich',
        'coach' => 'Mit der Coachin geteilt',
        'program' => 'Im Kurs sichtbar',
        'all' => 'In der Community',
    ];

    /** Auswahl im Formular, mit dem Namen der Coachin statt "Meine Coachin". */
    public static function sichtbarkeiten(): array
    {
        $coach = app(Branding::class)->coachName();

        return ['private' => 'Nur ich', 'coach' => $coach, 'program' => 'Mein Kurs', 'all' => 'In der Community'];
    }

    /** Anzeige am Eintrag. */
    public static function sichtbarkeitText(?string $v): string
    {
        $coach = app(Branding::class)->coachName();

        return match ($v) {
            'coach' => 'Mit '.$coach.' geteilt', 'program' => 'Im Kurs sichtbar', 'all' => 'In der Community', default => 'Nur ich'
        };
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean'];
    }

    public function projekt(): BelongsTo
    {
        return $this->belongsTo(Projekt::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->oldest();
    }
}
