<?php

namespace App\Models;

use App\Support\Protokoll\Protokolliert;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ein Verkauf an eine Person: Angebot, Preis, Zahlungsart, Stand und Rechnung in der Buchhaltung. */
class Verkauf extends Model
{
    use BelongsToTenant;
    use Protokolliert;

    public const ZAHLUNGSARTEN = ['rechnung' => 'auf Rechnung', 'bezahlt' => 'bereits bezahlt', 'stripe' => 'online bezahlt', 'kostenlos' => 'kostenlos'];

    public const STATUS = ['offen' => 'offen', 'bezahlt' => 'bezahlt', 'storniert' => 'storniert'];

    protected $table = 'verkaeufe';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['betrag' => 'decimal:2', 'faellig_am' => 'date', 'bezahlt_am' => 'datetime', 'settings' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(Entitlement::class);
    }

    public function verkaeufer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function istOffen(): bool
    {
        return $this->status === 'offen';
    }

    public function betragText(): string
    {
        return number_format((float) $this->betrag, 2, '.', "'").' '.$this->waehrung;
    }
}
