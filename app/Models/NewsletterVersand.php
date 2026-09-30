<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Ein Newsletter an einen Kontakt: Stand, Oeffnung, Klick, Token fuer Zaehlung und Webversion. */
class NewsletterVersand extends Model
{
    use BelongsToTenant;

    protected $table = 'newsletter_versand';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['gesendet_at' => 'datetime', 'geoeffnet_at' => 'datetime', 'geklickt_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (NewsletterVersand $v) => $v->token ??= Str::random(40));
    }

    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    public function kontakt(): BelongsTo
    {
        return $this->belongsTo(Kontakt::class);
    }
}
