<?php

namespace App\Notifications;

use App\Models\TelegramLink;
use App\Models\Tenant;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Telegram-Bot je Mandant (tenants.settings.telegram.bot_token).
 * Merkt sich die Telegram-Nachrichten-ID, damit Antworten aus Telegram
 * ins richtige Gespraech finden (settings.map am Link).
 */
class TelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof AppNotification) {
            return;
        }

        $notification->withTenant(function (?Tenant $tenant) use ($notifiable, $notification) {
            $token = $tenant?->setting('telegram.bot_token');
            $link = TelegramLink::where('user_id', $notifiable->id)->where('active', true)->first();
            if (! $token || ! $link?->chat_id) {
                return;
            }

            $data = $notification->toTelegram($notifiable);
            $text = '*'.self::escape($data['titel']).'*'."\n".self::escape(Str::words(strip_tags($data['text']), 60, ' ...'));
            $params = ['chat_id' => $link->chat_id, 'text' => $text, 'parse_mode' => 'MarkdownV2', 'disable_web_page_preview' => true];
            if ($data['url']) {
                $params['reply_markup'] = json_encode(['inline_keyboard' => [[['text' => 'Öffnen', 'url' => $data['url']]]]]);
            }

            try {
                $res = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", $params);
                $mid = $res->json('result.message_id');
                if ($mid && str_starts_with((string) $data['tag'], 'chat-')) {
                    $settings = $link->settings ?? [];
                    $settings['map'][(string) $mid] = (int) substr($data['tag'], 5);
                    $settings['map'] = array_slice($settings['map'], -300, null, true);
                    $link->forceFill(['settings' => $settings])->save();
                }
            } catch (\Throwable $e) {
                Log::warning('Telegram-Versand fehlgeschlagen: '.$e->getMessage());
            }
        });
    }

    public static function escape(string $text): string
    {
        return preg_replace('/([_*\[\]()~`>#+\-=|{}.!\\\\])/', '\\\\$1', $text);
    }
}
