<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/** Nachricht in einem Gespraech: Text, Sprachnachricht, Datei, angehaengtes Element. */
class Message extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $attributes = ['source' => 'app'];

    protected function casts(): array
    {
        return ['nudged_at' => 'datetime', 'meta' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ref(): MorphTo
    {
        return $this->morphTo();
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    public function hasAudio(): bool
    {
        return filled($this->audio_path);
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    public function attachmentIsImage(): bool
    {
        return (bool) preg_match('~\.(jpe?g|png|gif|webp|heic)$~i', (string) $this->attachment_path);
    }

    public function excerpt(int $words = 24): string
    {
        if (filled($this->body)) {
            return Str::words(strip_tags($this->body), $words, ' ...');
        }
        if ($this->hasAudio()) {
            return 'Sprachnachricht'.($this->transcript ? ': '.Str::words($this->transcript, $words, ' ...') : '');
        }
        if ($this->hasAttachment()) {
            return 'Datei: '.($this->attachment_name ?: basename($this->attachment_path));
        }

        return 'Etwas geteilt';
    }

    /** Terminvorschlaege als Zeitpunkte in Ortszeit. */
    public function vorschlaege(): \Illuminate\Support\Collection
    {
        $tz = static::ortszone() ?? config('app.timezone');

        return collect($this->meta['vorschlaege'] ?? [])->map(fn ($iso) => \Illuminate\Support\Carbon::parse($iso)->setTimezone($tz));
    }

    public function istVorschlag(): bool
    {
        return ! empty($this->meta['vorschlaege']);
    }
}
