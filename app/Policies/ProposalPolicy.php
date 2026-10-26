<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class ProposalPolicy
{
    public function create(User $user): bool
    {
        return $user->role === Role::Speaker;
    }
}
