<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Neue Nachricht in einem Gespraech: Benachrichtigungen haengen hier dran, und ueber Reverb
 * erfahren offene Chatfenster sofort davon (Kanal gespraech.{id}, nur die Nummer, den Rest
 * holt der Browser wie beim Nachfragen). Sofort statt ueber die Queue, Reverb laeuft lokal.
 */
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable;

    public int $conversationId;

    public int $messageId;

    public function __construct(public Message $message)
    {
        $this->conversationId = (int) $message->conversation_id;
        $this->messageId = (int) $message->id;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('gespraech.'.$this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'nachricht';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->messageId];
    }
}
