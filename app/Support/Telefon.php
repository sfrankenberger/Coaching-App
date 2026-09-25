<?php

namespace App\Support;

use App\Models\Tenant;

/** Telefonnummern fuer Links (WhatsApp, tel:) in internationale Form bringen. */
class Telefon
{
    protected const VORWAHL = ['CH' => '41', 'DE' => '49', 'AT' => '43', 'LI' => '423', 'IT' => '39', 'FR' => '33'];

    /** Ziffern mit Landesvorwahl ohne "+", z. B. "079 123 45 67" -> "41791234567". */
    public static function international(?string $nummer, ?Tenant $tenant = null): ?string
    {
        $roh = trim((string) $nummer);
        if ($roh === '') {
            return null;
        }
        $plus = str_starts_with($roh, '+');
        $ziffern = preg_replace('~\D+~', '', $roh);
        if ($ziffern === '') {
            return null;
        }
        if ($plus) {
            return $ziffern;
        }
        if (str_starts_with($ziffern, '00')) {
            return substr($ziffern, 2);
        }
        if (str_starts_with($ziffern, '0')) {
            return self::landesvorwahl($tenant).substr($ziffern, 1);
        }

        return $ziffern;
    }

    public static function whatsapp(?string $nummer, ?Tenant $tenant = null): ?string
    {
        $n = self::international($nummer, $tenant);

        return $n ? 'https://wa.me/'.$n : null;
    }

    protected static function landesvorwahl(?Tenant $tenant): string
    {
        $land = strtoupper((string) ($tenant?->setting('phone_country') ?: substr((string) ($tenant?->locale ?? 'de_CH'), -2)));

        return self::VORWAHL[$land] ?? '41';
    }
}
