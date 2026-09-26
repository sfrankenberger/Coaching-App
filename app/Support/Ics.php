<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Membership;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Kalenderdatei (iCalendar) fuer Termine: Abo je Person und Datei je Termin. */
class Ics
{
    public static function calendar(Collection $events, string $name, string $tz, string $domain): string
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Coaching-App//DE', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::text($name), 'X-WR-TIMEZONE:'.$tz, 'REFRESH-INTERVAL;VALUE=DURATION:PT1H', 'X-PUBLISHED-TTL:PT1H'];
        foreach ($events as $e) {
            $lines = array_merge($lines, self::vevent($e, $domain));
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'fold'], $lines))."\r\n";
    }

    public static function vevent(Event $e, string $domain): array
    {
        $out = ['BEGIN:VEVENT', 'UID:termin-'.$e->id.'@'.$domain, 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z')];
        if ($e->all_day) {
            $out[] = 'DTSTART;VALUE=DATE:'.$e->starts_at->format('Ymd');
            $out[] = 'DTEND;VALUE=DATE:'.($e->ends_at ?? $e->starts_at)->copy()->addDay()->format('Ymd');
        } else {
            $out[] = 'DTSTART:'.$e->starts_at->copy()->utc()->format('Ymd\THis\Z');
            $out[] = 'DTEND:'.($e->ends_at ?? $e->starts_at->copy()->addHours(2))->copy()->utc()->format('Ymd\THis\Z');
        }
        $out[] = 'SUMMARY:'.self::text($e->title);
        $desc = trim(strip_tags((string) $e->description));
        if ($e->zoom_url) {
            $desc = trim($e->zoom_url."\n\n".$desc);
        }
        $url = route('termine.show', $e);
        $desc = trim($desc."\n\n".$url);
        $out[] = 'DESCRIPTION:'.self::text($desc);
        $out[] = 'URL:'.$url;
        if ($e->location || $e->zoom_url) {
            $out[] = 'LOCATION:'.self::text($e->location ?: $e->zoom_url);
        }
        $out[] = 'END:VEVENT';

        return $out;
    }

    /** Abo-Schluessel der Person (wird beim ersten Mal angelegt). */
    public static function tokenFor(Membership $m): string
    {
        $token = $m->setting('calendar_token');
        if (! $token) {
            $token = Str::random(40);
            $settings = $m->settings ?? [];
            $settings['calendar_token'] = $token;
            $m->forceFill(['settings' => $settings])->save();
        }

        return $token;
    }

    protected static function text(string $s): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $s);
    }

    /** Zeilen laenger als 75 Byte umbrechen (RFC 5545). */
    protected static function fold(string $line): string
    {
        $out = '';
        while (strlen($line) > 75) {
            $cut = 75;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $out .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
        }

        return $out.$line;
    }
}
