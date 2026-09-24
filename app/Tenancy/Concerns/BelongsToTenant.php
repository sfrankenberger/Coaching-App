<?php

namespace App\Tenancy\Concerns;

use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pflicht fuer JEDES Modell mit Mandantendaten.
 * - filtert automatisch auf den aktuellen Mandanten
 * - setzt tenant_id beim Anlegen
 * Ausnahme nur bewusst: Model::withoutGlobalScope(TenantScope::class)
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = app(CurrentTenant::class)->getOrFail()->id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
