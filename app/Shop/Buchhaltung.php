<?php

namespace App\Shop;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Buchhaltung je Mandant: jede Coachin hat ihr eigenes System (bexio in der Schweiz, spaeter
 * sevdesk oder Lexware). Die App liest daraus die Rechnungen einer Person und holt das PDF.
 * Welches System, steht in tenants.settings.buchhaltung.anbieter.
 */
abstract class Buchhaltung
{
    public const ANBIETER = ['bexio' => 'bexio (Schweiz)'];

    /** Rechnungsstatus, wie die App ihn zeigt. */
    public const STATUS = ['entwurf', 'offen', 'bezahlt', 'teilweise', 'storniert', 'gemahnt'];

    public function __construct(protected Tenant $tenant) {}

    /** Die Buchhaltung des Mandanten, oder null, wenn keine eingerichtet ist. */
    public static function fuer(?Tenant $tenant): ?static
    {
        if (! $tenant) {
            return null;
        }

        return match ((string) $tenant->setting('buchhaltung.anbieter', '')) {
            'bexio' => new Bexio($tenant),
            default => null,
        };
    }

    abstract public function name(): string;

    /** Zugang hinterlegt (Token oder dauerhafte Verbindung). */
    abstract public function verbunden(): bool;

    /**
     * Alle Rechnungen einer Person, neueste zuerst, ohne Entwuerfe. Jede Rechnung:
     * id, nr, titel, datum (Y-m-d), faellig, betrag (float), waehrung, status (self::STATUS), link (online bezahlen).
     */
    abstract public function rechnungen(User $user, bool $frisch = false): Collection;

    /** Das PDF einer Rechnung als Bytes, null wenn nicht ladbar. */
    abstract public function pdf(int $id): ?string;

    public static function statusText(string $status): string
    {
        return ['entwurf' => 'Entwurf', 'offen' => 'offen', 'bezahlt' => 'bezahlt', 'teilweise' => 'teilweise bezahlt', 'storniert' => 'storniert', 'gemahnt' => 'gemahnt'][$status] ?? $status;
    }

    /** Summe der offenen Rechnungen einer Person je Waehrung. */
    public function offen(User $user): array
    {
        $summen = [];
        foreach ($this->rechnungen($user) as $r) {
            if (in_array($r['status'], ['offen', 'teilweise', 'gemahnt'], true)) {
                $summen[$r['waehrung']] = ($summen[$r['waehrung']] ?? 0) + $r['betrag'];
            }
        }

        return $summen;
    }
}
