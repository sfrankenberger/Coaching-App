<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;

/** Neue Nachricht in einem Gespraech (Benachrichtigungen haengen hier dran). */
class MessageSent
{
    use Dispatchable;

    public function __construct(public Message $message) {}
}
