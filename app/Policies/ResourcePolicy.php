<?php

namespace App\Policies;

use App\Models\Resource;
use App\Models\User;
use App\Programs\Begleitung;

class ResourcePolicy
{
    public function __construct(protected Begleitung $begleitung) {}

    public function view(User $user, Resource $resource): bool
    {
        return $this->begleitung->canViewResource($user, $resource);
    }
}
