<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;
use App\Programs\ProgramAccess;

/** Wer ein Programm sehen darf, entscheidet an genau einer Stelle ProgramAccess. */
class ProgramPolicy
{
    public function __construct(protected ProgramAccess $access) {}

    public function view(User $user, Program $program): bool
    {
        return $this->access->canView($user, $program);
    }
}
