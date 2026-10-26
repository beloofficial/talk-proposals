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

    public function viewReviews(User $user): bool
    {
        return $user->role !== Role::Speaker;
    }

    public function review(User $user): bool
    {
        return $user->role === Role::Reviewer;
    }

    public function updateStatus(User $user): bool
    {
        return $user->role === Role::Admin;
    }
}
