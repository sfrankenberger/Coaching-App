<?php

namespace App\Models;

use App\Support\Protokoll\Protokolliert;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Externes Produkt (WooCommerce, Stripe), das ein Angebot ausloest. */
class OfferProduct extends Model
{
    use BelongsToTenant;
    use Protokolliert;

    protected $guarded = [];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
