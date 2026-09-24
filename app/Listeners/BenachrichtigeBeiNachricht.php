<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;

/** Neue Nachricht im Gespraech: die anderen Beteiligten erfahren es. */
class BenachrichtigeBeiNachricht
{
    public function __construct(protected Notifier $notifier) {}

    public function handle(MessageSent $event): void
    {
        $msg = $event->message;
        $conv = $msg->conversation()->with(['participants', 'program:id,title'])->first();
        if (! $conv || ! $msg->user) {
            return;
        }

        $andere = $conv->participants->pluck('user_id')->reject(fn ($id) => $id === $msg->user_id);
        if ($andere->isEmpty()) {
            return;
        }

        $von = $msg->user->vorname();
        $titel = $conv->isDirect() ? "{$von} hat dir geschrieben" : "{$von} in {$conv->program?->title}";

        $this->notifier->send($andere, new Nachricht(
            titel: $titel,
            text: $msg->excerpt(),
            url: route('gespraech.show', $conv),
            anlass: 'chat',
            tag: 'chat-'.$msg->id,
            mailWennKeinPush: $conv->isDirect(),
            knopf: 'Antworten',
        ));
    }
}
