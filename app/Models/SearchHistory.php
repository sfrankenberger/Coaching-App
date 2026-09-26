<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Suchverlauf im Nachschlagen: was gesucht wurde und was dabei herauskam. */
class SearchHistory extends Model
{
    use BelongsToTenant;

    public const MAX = 40;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['items' => 'array'];
    }

    /** Eine Suche merken, dieselbe nicht doppelt fuehren, hoechstens MAX je Person. */
    public static function merken(User $user, string $text, string $kind, ?string $answer, array $items): ?self
    {
        $text = trim(mb_substr($text, 0, 200));
        if ($text === '') {
            return null;
        }
        static::where('user_id', $user->id)->whereRaw('LOWER(text) = ?', [mb_strtolower($text)])->delete();
        $h = static::create(['user_id' => $user->id, 'text' => $text, 'kind' => $kind, 'answer' => $answer ? mb_substr($answer, 0, 600) : null, 'items' => array_slice($items, 0, 10)]);
        $alt = static::where('user_id', $user->id)->orderByDesc('id')->skip(self::MAX)->take(100)->pluck('id');
        if ($alt->isNotEmpty()) {
            static::whereIn('id', $alt)->delete();
        }

        return $h;
    }
}
