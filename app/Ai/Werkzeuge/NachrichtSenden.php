<?php

namespace App\Ai\Werkzeuge;

use App\Chat\Chat;
use App\Models\User;

class NachrichtSenden extends Werkzeug
{
    public function __construct(protected Chat $chat) {}

    public function name(): string
    {
        return 'nachricht_senden';
    }

    public function beschreibung(): string
    {
        return 'Eine Nachricht ins 1:1-Gespräch mit einer Person schicken (im Namen der Coachin). Die Person bekommt Push oder Mail.';
    }

    public function schreibt(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return $this->str($this->wer() + ['text' => ['type' => 'string', 'description' => 'Die Nachricht, Du-Form, warm, kurz']], ['text']);
    }

    public function ausfuehren(array $args, User $von): array
    {
        $m = $this->person($args);
        abort_if(trim((string) ($args['text'] ?? '')) === '', 422, 'Kein Text.');
        $msg = $this->chat->send($this->chat->directFor($m->user), $von, ['body' => $args['text']]);

        return ['ok' => true, 'message_id' => $msg->id, 'gespraech' => route('gespraech.show', $msg->conversation_id)] + $this->kurz($m);
    }
}
