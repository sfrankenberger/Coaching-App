<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Eigene Eintraege (Aufgabe, Notiz, Reflexion): nur die Person selbst darf aendern und loeschen. */
class EigenerEintragPolicy
{
    public function update(User $user, Model $item): bool
    {
        return (int) $item->user_id === $user->id;
    }

    public function delete(User $user, Model $item): bool
    {
        return $this->update($user, $item);
    }
}
