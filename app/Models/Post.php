<?php

namespace App\Models;

use App\Content\Concerns\HasTopics;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Impuls oder Neuigkeit (Beitrag). Kommt aus WordPress (Import oder Feed)
 * oder wird im Coach-Bereich geschrieben.
 */
class Post extends Model
{
    use BelongsToTenant, HasTopics;

    public const TYPES = ['impuls' => 'Impuls', 'neuigkeit' => 'Neuigkeit'];

    public const VISIBILITIES = ['members' => 'Alle Mitglieder', 'program' => 'Nur ein Programm', 'team' => 'Nur das Team'];

    protected $guarded = [];

    protected $attributes = ['type' => 'impuls', 'source' => 'app', 'visibility' => 'members', 'is_published' => true];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'notify_channels' => 'array',
            'notified_at' => 'datetime',
            'published_at' => 'datetime',
            'is_published' => 'boolean',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            if (blank($post->slug)) {
                $post->slug = self::uniqueSlug($post->title, $post->id);
            }
        });
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'beitrag';
        $slug = $base;
        $n = 2;
        while (self::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true)->where(fn ($w) => $w->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function excerptText(int $limit = 200): string
    {
        return Str::limit(trim(html_entity_decode(strip_tags((string) ($this->excerpt ?: $this->body)), ENT_QUOTES, 'UTF-8')), $limit);
    }
}
