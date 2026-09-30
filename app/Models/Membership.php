<?php

namespace App\Models;

use App\Enums\Role;
use App\Support\Papierkorb\ImPapierkorb;
use App\Support\Protokoll\Protokolliert;
use App\Tenancy\Concerns\BelongsToTenant;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Zugehoerigkeit einer Person zu einem Mandanten, mit Rolle.
 * Eine Person (users) kann in mehreren Mandanten sein.
 *
 * Als Pivot ueber tenant->users() bzw. user->tenants() erreichbar, als eigenes
 * Modell (Coach-Bereich, Import) ueber den Mandanten-Scope gefiltert.
 */
class Membership extends Pivot
{
    use BelongsToTenant;
    use ImPapierkorb;
    use Protokolliert;

    protected $table = 'memberships';

    public $incrementing = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'joined_at' => 'datetime',
            'settings' => 'array',
            'digest_sent_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Eindeutig ist (tenant_id, user_id): liegt fuer dieselbe Person noch ein Zugang im Papierkorb,
        // raeumt der neue ihn weg (attach, sync, create). Wer Einstellungen behalten will, nimmt anlegen().
        static::creating(function (Membership $m) {
            static::onlyTrashed()->withoutGlobalScopes()->where('tenant_id', $m->tenant_id ?? app(CurrentTenant::class)->id())
                ->where('user_id', $m->user_id)->get()->each->forceDelete();
        });
    }

    /** Zugang anlegen oder, wenn er im Papierkorb liegt, samt Einstellungen zurueckholen. */
    public static function anlegen(array $attribute): static
    {
        $tenantId = $attribute['tenant_id'] ?? app(CurrentTenant::class)->getOrFail()->id;
        $alt = static::onlyTrashed()->withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenantId)->where('user_id', $attribute['user_id'])->first();
        if ($alt) {
            $alt->restore();
            $alt->fill($attribute)->save();

            return $alt;
        }

        return static::create($attribute);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Einzelne Einstellung, z. B. setting('notifications.abendmail', true). */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public static function statusLabels(): array
    {
        return ['active' => 'Aktiv', 'paused' => 'Pausiert', 'ended' => 'Beendet'];
    }
}
