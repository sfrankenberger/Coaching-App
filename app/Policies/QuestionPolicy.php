<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class QuestionPolicy
{
    /** Im Kurs sichtbar, oder eigene Frage, oder Coachin und Team. */
    public function view(User $user, Question $frage): bool
    {
        if (! $frage->program || ! Gate::forUser($user)->allows('view', $frage->program)) {
            return false;
        }

        return $frage->visibility === 'program' || $frage->user_id === $user->id || $user->canManageCurrentTenant();
    }

    public function status(User $user, Question $frage): bool
    {
        return $user->canManageCurrentTenant();
    }

    public function delete(User $user, Question $frage): bool
    {
        return $frage->user_id === $user->id || $user->canManageCurrentTenant();
    }
}
