<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Proposal;
use App\Models\User;

class ProposalPolicy
{
    public function view(User $user, Proposal $proposal): bool
    {
        return $user->role !== Role::Speaker || $proposal->user()->is($user);
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Speaker;
    }
}
