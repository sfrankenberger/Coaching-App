<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Angebot: was man kaufen oder bekommen kann. Schaltet Programme frei.
 */
class Offer extends Model
{
    use BelongsToTenant;

    public const TYPES = [
        'course' => 'Einzelkurs',
        'club' => 'Club (Abo)',
        'hybrid' => 'Hybrid-Coaching',
        'one_on_one' => '1:1',
        'free' => 'Gratis-Einstieg',
    ];

    protected $guarded = [];

    protected $attributes = ['type' => 'course', 'is_free' => false, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Offer $offer) {
            if (blank($offer->slug)) {
                $basis = Str::slug($offer->title) ?: 'angebot';
                $slug = $basis;
                $n = 2;
                while (static::query()->where('slug', $slug)->where('id', '!=', $offer->id ?? 0)->exists()) {
                    $slug = $basis.'-'.$n++;
                }
                $offer->slug = $slug;
            }
        });
    }

    /* ---------- Verkauf ---------- */

    /** Preise je Waehrung (settings.preis_chf, preis_eur), mit Aktionspreis, solange die Aktion laeuft. */
    public function preise(): array
    {
        $s = $this->settings ?? [];
        $out = [];
        foreach (['CHF' => 'preis_chf', 'EUR' => 'preis_eur'] as $w => $k) {
            if (isset($s[$k]) && $s[$k] !== '' && $s[$k] !== null) {
                $out[$w] = round((float) $s[$k], 2);
            }
        }
        if ($this->aktionLaeuft()) {
            foreach (['CHF' => 'aktion_preis_chf', 'EUR' => 'aktion_preis_eur'] as $w => $k) {
                if (isset($out[$w]) && filled($s[$k] ?? null)) {
                    $out[$w] = round((float) $s[$k], 2);
                }
            }
        }

        return $out;
    }

    public function aktionLaeuft(): bool
    {
        $bis = $this->settings['aktion_bis'] ?? null;

        return filled($bis) && now()->lte(Carbon::parse($bis)->endOfDay());
    }

    public function preis(string $waehrung): ?float
    {
        return $this->preise()[strtoupper($waehrung)] ?? null;
    }

    /** Regulaerer Preis (ohne Aktion), um ihn durchgestrichen zu zeigen. */
    public function preisRegulaer(string $waehrung): ?float
    {
        $k = strtoupper($waehrung) === 'EUR' ? 'preis_eur' : 'preis_chf';

        return filled($this->settings[$k] ?? null) ? round((float) $this->settings[$k], 2) : null;
    }

    /** Oeffentlich sichtbar (Liste auf der Website und in der App). Ohne Haken nur ueber den Link. */
    public function sichtbar(): bool
    {
        return $this->is_active && (bool) ($this->settings['sichtbar'] ?? false);
    }

    /** Kaufbar: aktiv, mit Preis oder gratis. */
    public function kaufbar(): bool
    {
        return $this->is_active && ($this->is_free || $this->preise() !== []);
    }

    /** Abos gehen nur ueber Stripe (Karte oder Twint), nie auf Rechnung. */
    public function kaufAufRechnung(): bool
    {
        return ! $this->istAbo() && (bool) ($this->settings['kauf_rechnung'] ?? true);
    }

    /* ---------- Abo ---------- */

    public const ABO_INTERVALLE = ['monat' => 'monatlich', 'jahr' => 'jährlich'];

    public function istAbo(): bool
    {
        return array_key_exists((string) ($this->settings['abo_intervall'] ?? ''), self::ABO_INTERVALLE);
    }

    /** monat | jahr, null wenn kein Abo. */
    public function aboIntervall(): ?string
    {
        return $this->istAbo() ? $this->settings['abo_intervall'] : null;
    }

    /** "monatlich" oder "jaehrlich" fuer die Preisangabe, leer bei Einmalkauf. */
    public function intervallText(): string
    {
        return self::ABO_INTERVALLE[$this->aboIntervall()] ?? '';
    }

    /** Preis mit Zusatz: "49.00 CHF monatlich". */
    public function preisMitIntervall(float $betrag, string $waehrung): string
    {
        return self::preisText($betrag, $waehrung).($this->istAbo() ? ' '.$this->intervallText() : '');
    }

    public function kaufUrl(?string $ref = null): string
    {
        return route('kaufen', array_filter(['angebot' => $this->slug, 'ref' => $ref]));
    }

    public static function preisText(float $betrag, string $waehrung): string
    {
        return number_format($betrag, 2, '.', "'").' '.$waehrung;
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'offer_program');
    }

    public function products(): HasMany
    {
        return $this->hasMany(OfferProduct::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }
}
