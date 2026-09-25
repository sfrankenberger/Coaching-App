<?php

namespace App\Support;

/**
 * Aus einer Video-Adresse die Einbettung machen: Vimeo, YouTube, Bunny, mp4.
 */
class Video
{
    /** ['kind' => 'iframe'|'file', 'src' => ...] oder null. */
    public static function embed(?string $url): ?array
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $start = 0;
        if (preg_match('~#t=(\d+)~', $url, $m)) {
            $start = (int) $m[1];
            $url = preg_replace('~#t=\d+~', '', $url);
        }

        if (preg_match('~vimeo\.com/(?:video/)?(\d+)(?:/([0-9a-zA-Z]+))?~', $url, $m)) {
            $src = 'https://player.vimeo.com/video/'.$m[1].'?dnt=1';
            if (! empty($m[2])) {
                $src .= '&h='.$m[2];
            }
            if ($start) {
                $src .= '#t='.$start.'s';
            }

            return ['kind' => 'iframe', 'src' => $src];
        }

        if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{6,})~', $url, $m)) {
            return ['kind' => 'iframe', 'src' => 'https://www.youtube-nocookie.com/embed/'.$m[1].'?enablejsapi=1'.($start ? '&start='.$start : '')];
        }

        if (preg_match('~iframe\.mediadelivery\.net/(?:play|embed)/(\d+)/([0-9a-f-]{36})~i', $url, $m)) {
            return ['kind' => 'iframe', 'src' => 'https://iframe.mediadelivery.net/embed/'.$m[1].'/'.$m[2].'?autoplay=false&preload=false'];
        }

        if (preg_match('~\.(mp4|webm|mov|m4v)(\?|$)~i', $url)) {
            return ['kind' => 'file', 'src' => $url];
        }

        return null;
    }

    public static function isAudio(?string $url): bool
    {
        return (bool) preg_match('~\.(mp3|m4a|ogg|wav)(\?|$)~i', (string) $url);
    }
}
