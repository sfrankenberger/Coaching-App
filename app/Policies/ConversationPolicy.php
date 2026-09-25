<?php

namespace App\Policies;

use App\Chat\Chat;
use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function __construct(protected Chat $chat) {}

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->chat->canAccess($user, $conversation);
    }
}
