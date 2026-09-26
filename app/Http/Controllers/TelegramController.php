<?php

namespace App\Http\Controllers;

use App\Chat\Chat;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TelegramLink;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Telegram: Verbinden aus dem Profil (Code per /start), Webhook fuer
 * Antworten. Bot-Token und Webhook-Geheimnis je Mandant in
 * tenants.settings.telegram (bot_token, bot_username, webhook_secret).
 */
class TelegramController extends Controller
{
    public function __construct(protected CurrentTenant $current, protected Chat $chat) {}

    public static function configured(?Tenant $tenant): bool
    {
        return filled($tenant?->setting('telegram.bot_token')) && filled($tenant?->setting('telegram.bot_username'));
    }

    /** Verbindungscode erzeugen und den Link zum Bot zeigen. */
    public function verbinden(Request $request): RedirectResponse
    {
        $tenant = $this->current->getOrFail();
        abort_unless(self::configured($tenant), 404);

        $link = TelegramLink::firstOrNew(['user_id' => $request->user()->id]);
        $link->fill(['code' => Str::upper(Str::random(8)), 'active' => false])->save();

        return back()->with('meldung', 'Tippe auf «Bot öffnen» und dann in Telegram auf «Start». Danach bist du verbunden.');
    }

    public function trennen(Request $request): RedirectResponse
    {
        TelegramLink::where('user_id', $request->user()->id)->delete();

        return back()->with('meldung', 'Telegram getrennt.');
    }

    /** Webhook des Bots (ohne Anmeldung, geschuetzt ueber das Geheimnis in der Adresse). */
    public function webhook(Request $request, string $secret): JsonResponse
    {
        $tenant = $this->current->getOrFail();
        $token = $tenant->setting('telegram.bot_token');
        abort_unless($token && hash_equals((string) $tenant->setting('telegram.webhook_secret'), $secret), 403);

        $m = $request->input('message') ?? $request->input('edited_message');
        if (! is_array($m) || empty($m['chat']['id'])) {
            return response()->json(['ok' => true]);
        }
        $chatId = (string) $m['chat']['id'];
        $text = trim((string) ($m['text'] ?? ''));
        $username = $m['from']['username'] ?? null;

        // /start CODE: Verbindung herstellen
        if (preg_match('~^/start(?:\s+([A-Za-z0-9]{6,12}))?$~', $text, $mm)) {
            $code = strtoupper($mm[1] ?? '');
            $link = $code ? TelegramLink::where('code', $code)->where('active', false)->first() : null;
            if (! $link) {
                $this->reply($token, $chatId, 'Um dich zu verbinden, öffne dein Profil in der App und tippe auf «Telegram verbinden».');

                return response()->json(['ok' => true]);
            }
            $link->forceFill(['chat_id' => $chatId, 'username' => $username, 'active' => true, 'code' => null])->save();
            $this->reply($token, $chatId, 'Verbunden. Du bekommst Erinnerungen und Nachrichten jetzt auch hier. Antworten kannst du direkt in diesem Chat.');

            return response()->json(['ok' => true]);
        }

        $link = TelegramLink::where('chat_id', $chatId)->where('active', true)->first();
        if (! $link || $text === '') {
            return response()->json(['ok' => true]);
        }
        $user = User::find($link->user_id);
        if (! $user) {
            return response()->json(['ok' => true]);
        }

        // Coachin oder Team: per Antworten auf die gemeldete Nachricht ins richtige Gespraech
        if ($user->canManageCurrentTenant()) {
            $replyId = (string) ($m['reply_to_message']['message_id'] ?? '');
            $msgId = $replyId ? ($link->settings['map'][$replyId] ?? null) : null;
            $conv = $msgId ? Message::find($msgId)?->conversation : null;
            if (! $conv) {
                $letzte = $link->settings['letzte'] ?? null;
                if ($letzte && now()->diffInMinutes(Carbon::parse($letzte['zeit'])) < 30) {
                    $conv = Conversation::find($letzte['conversation']);
                }
            }
            if (! $conv) {
                $this->reply($token, $chatId, 'An wen soll das gehen? Tippe auf die Nachricht der Person und wähle «Antworten», dann landet es im richtigen Gespräch.');

                return response()->json(['ok' => true]);
            }
            $this->chat->send($conv, $user, ['body' => $text, 'source' => 'telegram']);
            $settings = $link->settings ?? [];
            $settings['letzte'] = ['conversation' => $conv->id, 'zeit' => now()->toIso8601String()];
            $link->forceFill(['settings' => $settings])->save();
            $wer = $conv->isDirect() ? ($conv->user?->vorname() ?: 'die Person') : ($conv->program?->title ?: 'die Gruppe');
            $this->reply($token, $chatId, "An {$wer} geschickt.");

            return response()->json(['ok' => true]);
        }

        // Alle anderen: ins eigene 1:1-Gespraech
        $conv = $this->chat->directFor($user);
        $this->chat->send($conv, $user, ['body' => $text, 'source' => 'telegram']);
        $this->reply($token, $chatId, 'Angekommen. Deine Coachin liest es in eurem Gespräch.');

        return response()->json(['ok' => true]);
    }

    protected function reply(string $token, string $chatId, string $text): void
    {
        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", ['chat_id' => $chatId, 'text' => $text]);
        } catch (\Throwable) {
            // Telegram nicht erreichbar: still weiter, die Nachricht ist gespeichert
        }
    }
}
