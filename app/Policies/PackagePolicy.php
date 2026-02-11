<?php

namespace App\Policies;

use App\Models\Package;
use App\Models\User;

class PackagePolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function viewAny(User $user): bool
    {
        if ($user->role == "user") {
            return $user->can('user view packages');
        } else if ($user->isAdmin()) {
            return $user->can('manage packages');
        }
        return false;
    }

    public function view(User $user, Package $package): bool
    {
        if ($user->role == "user") {
            return $user->can('user view packages');
        } else if ($user->isAdmin()) {
            return $user->can('manage packages');
        }
        return false;
    }

    public function show(User $user): bool
    {
        if ($user->role == "user") {
            return $user->can('user show cards package');
        } else if ($user->isAdmin()) {
            return $user->can('manage packages');
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Package $package): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Package $package): bool
    {
        return $user->isAdmin();
    }
}
