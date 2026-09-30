<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Membership;
use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
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
        if ($e->cancelled_at) {
            $out[] = 'STATUS:CANCELLED';
            $out[] = 'SEQUENCE:1';
        }
        if ($e->all_day) {
            $out[] = 'TRANSP:TRANSPARENT';   // Reflexions- und Fragentag blockieren nichts
        }
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
        if (! $e->all_day && ! $e->cancelled_at) {
            $out = array_merge($out, ['BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:'.self::text($e->title), 'TRIGGER:-PT15M', 'END:VALARM']);
        }
        $out[] = 'END:VEVENT';

        return $out;
    }

    /** Kalenderdatei eines Termins als Text, z. B. fuer den Mailanhang. */
    public static function datei(Event $e): string
    {
        $tz = app(CurrentTenant::class)->get()?->timezone ?: config('app.timezone');

        return self::calendar(collect([$e]), app(Branding::class)->appName(), $tz, self::domain());
    }

    public static function domain(): string
    {
        try {
            return request()?->getHost() ?: (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'app');
        } catch (\Throwable) {
            return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'app';
        }
    }

    /** Termin direkt in Google Calendar eintragen. */
    public static function googleUrl(Event $e): string
    {
        $ende = $e->ends_at ?? $e->starts_at->copy()->addHours(2);
        $dates = $e->all_day ? $e->starts_at->format('Ymd').'/'.$ende->copy()->addDay()->format('Ymd')
            : $e->starts_at->copy()->utc()->format('Ymd\THis\Z').'/'.$ende->copy()->utc()->format('Ymd\THis\Z');

        return 'https://calendar.google.com/calendar/render?'.http_build_query(array_filter(['action' => 'TEMPLATE', 'text' => $e->title, 'dates' => $dates,
            'details' => trim(($e->zoom_url ? $e->zoom_url."\n\n" : '').route('termine.show', $e)), 'location' => $e->location ?: $e->zoom_url]));
    }

    /** Termin in Outlook (Web) eintragen. */
    public static function outlookUrl(Event $e): string
    {
        $ende = $e->ends_at ?? $e->starts_at->copy()->addHours(2);

        return 'https://outlook.live.com/calendar/0/deeplink/compose?'.http_build_query(array_filter(['path' => '/calendar/action/compose', 'rru' => 'addevent', 'subject' => $e->title,
            'startdt' => $e->starts_at->copy()->utc()->toIso8601ZuluString(), 'enddt' => $ende->copy()->utc()->toIso8601ZuluString(), 'allday' => $e->all_day ? 'true' : null,
            'body' => trim(($e->zoom_url ? $e->zoom_url."\n\n" : '').route('termine.show', $e)), 'location' => $e->location ?: $e->zoom_url]));
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
