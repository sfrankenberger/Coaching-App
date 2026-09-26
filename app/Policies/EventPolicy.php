<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Programs\Begleitung;

class EventPolicy
{
    public function __construct(protected Begleitung $begleitung) {}

    public function view(User $user, Event $event): bool
    {
        return $this->begleitung->canViewEvent($user, $event);
    }

    /** Absagen und Dabei-Status: nur die Person des 1:1-Termins bzw. Teilnehmerinnen. */
    public function respond(User $user, Event $event): bool
    {
        return $this->view($user, $event) && ! $event->isOneOnOne();
    }
}
