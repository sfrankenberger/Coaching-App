<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Kontakt: die Stufe unter Gast. Mailadresse, Name, Einwilligung mit Nachweis, Tags statt Listen, kein Login.
 * Mitglieder haben zusaetzlich einen Kontakt (user_id), damit Newsletter und Serien sie erreichen.
 */
class Kontakt extends Model
{
    use BelongsToTenant;

    public const STATUS = ['angemeldet' => 'wartet auf Bestätigung', 'bestaetigt' => 'bestätigt', 'abgemeldet' => 'abgemeldet', 'abgeprallt' => 'unzustellbar'];

    protected $table = 'kontakte';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tags' => 'array', 'einwilligung' => 'array', 'settings' => 'array', 'bestaetigt_at' => 'datetime', 'abgemeldet_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Kontakt $k) {
            $k->token ??= Str::random(40);
            $k->email = Str::lower(trim((string) $k->email));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function versand(): HasMany
    {
        return $this->hasMany(NewsletterVersand::class);
    }

    public function vorname(): string
    {
        return Str::before(trim((string) $this->name), ' ') ?: 'du';
    }

    public function istBestaetigt(): bool
    {
        return $this->status === 'bestaetigt';
    }

    public function hatTag(string $tag): bool
    {
        return in_array(self::tagSauber($tag), $this->tags ?? [], true);
    }

    public static function tagSauber(string $tag): string
    {
        return Str::slug(trim($tag)) ?: 'tag';
    }

    public function scopeBestaetigt($q)
    {
        return $q->where('status', 'bestaetigt');
    }

    /** Kontakte mit einem der Tags; leere Liste = alle. */
    public function scopeMitTags($q, array $tags)
    {
        $tags = array_values(array_filter(array_map([self::class, 'tagSauber'], $tags)));
        if ($tags === []) {
            return $q;
        }

        return $q->where(function ($w) use ($tags) {
            foreach ($tags as $t) {
                $w->orWhereJsonContains('tags', $t);
            }
        });
    }
}
