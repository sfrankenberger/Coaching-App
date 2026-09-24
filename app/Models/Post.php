<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Impuls oder Neuigkeit (Beitrag). Kommt per RSS aus WordPress oder wird
 * im Coach-Bereich geschrieben. Tabelle folgt in Etappe 4.
 */
class Post extends Model
{
    use BelongsToTenant;

    protected $guarded = [];
}
