<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin']) || $user->can('users.viewAny') || $user->can('users.manage');
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $this->viewAny($user);
    }
}
