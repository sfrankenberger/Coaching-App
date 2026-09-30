<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Google- oder Apple-Konto, das mit einer Person verknuepft ist. Ohne tenant_id: gehoert zur Person wie ein Passkey. */
class SocialAccount extends Model
{
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Apple gibt bei "E-Mail verbergen" eine Weiterleitungsadresse heraus. */
    public static function versteckt(?string $email): bool
    {
        return (bool) preg_match('~@privaterelay\.appleid\.com$~i', (string) $email);
    }
}
