<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /** Die Autorin 15 Minuten lang, das Team jederzeit. */
    public function update(User $user, Comment $comment): bool
    {
        return $comment->bearbeitbarFuer($user);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id || $user->canManageCurrentTenant();
    }
}
