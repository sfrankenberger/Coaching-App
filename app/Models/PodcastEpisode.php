<?php

namespace App\Models;

use App\Content\Concerns\HasTopics;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Podcastfolge: eigener Podcast oder gespiegelte Fremd-Sendung (per Feed).
 * Audio bleibt extern (audio_url), dazu Kapitel, FAQ, Zusammenfassung, Abschrift.
 */
class PodcastEpisode extends Model
{
    use BelongsToTenant, HasTopics;

    protected $guarded = [];

    protected $attributes = ['is_published' => true];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'chapters' => 'array',
            'faq' => 'array',
            'keywords' => 'array',
            'is_published' => 'boolean',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PodcastEpisode $e) {
            if (blank($e->slug)) {
                $e->slug = Str::slug($e->title) ?: 'folge';
            }
        });
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true)->where(fn ($w) => $w->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function durationLabel(): ?string
    {
        if (! $this->duration_seconds) {
            return null;
        }
        $m = intdiv($this->duration_seconds, 60);

        return $m >= 60 ? sprintf('%d:%02d Std.', intdiv($m, 60), $m % 60) : "{$m} Min.";
    }

    public function excerptText(int $limit = 200): string
    {
        return Str::limit(trim(html_entity_decode(strip_tags((string) ($this->summary ?: $this->excerpt ?: $this->body)), ENT_QUOTES, 'UTF-8')), $limit);
    }

    /** Podcast-Folge in Sekunden aus "1:02:03", "62:03" oder "3723". */
    public static function parseDuration(?string $raw): ?int
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        if (ctype_digit($raw)) {
            return (int) $raw;
        }
        $parts = array_reverse(array_map('intval', explode(':', $raw)));
        $sec = 0;
        foreach ($parts as $i => $p) {
            $sec += $p * (60 ** $i);
        }

        return $sec ?: null;
    }
}
