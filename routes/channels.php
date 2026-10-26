<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('reviewers', fn (User $user) => $user->role === Role::Reviewer);

Broadcast::channel('admins', fn (User $user) => $user->role === Role::Admin);

Broadcast::channel('App.Models.User.{id}', fn (User $user, $id) => (int) $user->id === (int) $id);
