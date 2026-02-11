<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
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
        return true; // سنفلتر في الكنترولر: admin=all, user=own
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->role == "user") {
            return $user->can('user own orders');
        } else if ($user->isAdmin()) {
            return $user->can('manage orders');
        }

        return false;
        // return $user->isAdmin() || $order->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        if ($user->role == "user") {
            return $user->can('user create order');
        } else if ($user->isAdmin()) {
            return $user->can('manage orders');
        }

        return false;
    }

    public function confirm(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    public function cancel(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // المستخدم العادي: يسمح بالإلغاء فقط إذا كان الطلب pending وهو صاحبه
        return $order->user_id === $user->id && $order->status === 'pending';
    }
}
