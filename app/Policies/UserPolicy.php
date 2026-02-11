<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->can('manage users');
    }

    public function view(User $auth, User $user): bool
    {
        return $auth->can('manage users');
    }

    public function create(User $auth): bool
    {
        return $auth->can('manage users');
    }

    public function update(User $auth, User $user): bool
    {
        return $auth->can('manage users');
    }

    public function delete(User $auth, User $user): bool
    {
        return $auth->can('manage users');
    }
}
