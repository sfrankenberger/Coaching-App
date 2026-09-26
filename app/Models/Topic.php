<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Thema fuer den Themenfinder, quer ueber alle Inhalte. */
class Topic extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $attributes = ['position' => 0, 'is_visible' => true];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Topic $t) {
            if (blank($t->slug)) {
                $t->slug = Str::slug($t->name) ?: 'thema';
            }
        });
    }

    public function taggables(): HasMany
    {
        return $this->hasMany(Taggable::class);
    }

    public static function findOrCreateByName(string $name): self
    {
        $slug = Str::slug($name);

        return self::firstOrCreate(['slug' => $slug], ['name' => trim($name)]);
    }
}
