<?php

namespace App\Models;

use App\Support\Protokoll\Protokolliert;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Ein angehaengtes Element: haengt an einer Notiz, Aufgabe, Reflexion oder Frage und zeigt auf ein Ziel. */
class Anhang extends Model
{
    use BelongsToTenant;
    use Protokolliert;

    protected $table = 'anhaenge';

    protected $guarded = [];

    public function anhangAn(): MorphTo
    {
        return $this->morphTo('anhang_an');
    }

    public function ziel(): MorphTo
    {
        return $this->morphTo('ziel');
    }
}
