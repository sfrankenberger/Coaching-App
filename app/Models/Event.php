<?php

namespace App\Models;

use App\Content\Concerns\HasTopics;
use App\Support\Suche;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;

/**
 * Termin: Gruppencall, 1:1-Sitzung, Q&A, Webinar, oder ein ganztaegiger
 * Reflexions- bzw. Fragentag.
 */
class Event extends Model
{
    use BelongsToTenant, HasTopics, Searchable;

    public const TYPES = [
        'group_call' => 'Gruppencall',
        'one_on_one' => '1:1-Sitzung',
        'qa' => 'Fragen und Antworten',
        'webinar' => 'Webinar',
        'reflection_day' => 'Reflexionstag',
        'question_day' => 'Fragentag',
    ];

    public const ALL_DAY_TYPES = ['reflection_day', 'question_day'];

    /** Stand der Aufzeichnung (Wache und Freigabe). */
    public const RECORDING_STATUS = [
        'wartet' => 'Wird gesucht',
        'gefunden' => 'Gefunden, Abschrift folgt',
        'abschrift' => 'Abschrift da, Zusammenfassung folgt',
        'bereit' => 'Bereit, geht raus',
        'freigegeben' => 'Verschickt',
        'nicht_gefunden' => 'Nicht gefunden',
        'ohne_abschrift' => 'Ohne Abschrift',
    ];

    protected $guarded = [];

    protected $attributes = ['type' => 'group_call', 'all_day' => false, 'is_published' => true];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'is_published' => 'boolean',
            'reminded_day_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reminded_hour_at' => 'datetime',
            'recording_notified_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ProgramStep::class, 'step_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    /** Weitere Personen, die das Team an den Termin gehaengt hat (Paar-Coaching, Gast im Call): sehen Termin, Kalender und Aufzeichnung. */
    public function gaeste(): HasMany
    {
        return $this->hasMany(EventAttendee::class)->whereNotNull('invited_at');
    }

    /** Alle, die zum Termin gehoeren: Hauptperson (1:1) plus Gaeste. */
    public function personenIds(): Collection
    {
        return collect(array_filter([$this->user_id]))->concat($this->gaeste()->pluck('user_id'))->unique()->values();
    }

    /** Gaeste setzen (Team): fehlende einladen, entfernte wieder auf normale Teilnahme setzen. */
    public function gaesteSetzen(array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn ($id) => $id && $id !== (int) $this->user_id)));
        $this->attendees()->whereNotNull('invited_at')->whereNotIn('user_id', $userIds)->update(['invited_at' => null]);
        foreach ($userIds as $id) {
            EventAttendee::firstOrNew(['event_id' => $this->id, 'user_id' => $id])->forceFill(['invited_at' => now()])->save();
        }
    }

    public function resources(): MorphToMany
    {
        return $this->morphToMany(Resource::class, 'resourceable')->withPivot('shared_by');
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at');
    }

    public function scopePast(Builder $q): Builder
    {
        return $q->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at');
    }

    public function isLive(): bool
    {
        $end = $this->ends_at ?? $this->starts_at->copy()->addHours(2);

        return ! $this->all_day && now()->gte($this->starts_at->copy()->subMinutes(15)) && now()->lt($end);
    }

    public function isPast(): bool
    {
        return ($this->ends_at ?? $this->starts_at->copy()->addHours($this->all_day ? 24 : 2))->isPast();
    }

    public function isOneOnOne(): bool
    {
        return $this->type === 'one_on_one' || $this->user_id !== null;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function hasRecording(): bool
    {
        return filled($this->recording_url);
    }

    /** Suche (Scout, Datenbank): Felder als reiner Text. */
    public function toSearchableArray(): array
    {
        return [
            'title' => Suche::text($this->title),
            'description' => Suche::text($this->description),
            'summary' => Suche::text($this->summary),
            'transcript' => Suche::text($this->transcript),
        ];
    }
}
