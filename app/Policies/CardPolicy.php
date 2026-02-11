<?php

namespace App\Policies;

use App\Models\Card;
use App\Models\Order;
use App\Models\User;

class CardPolicy
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
        // admin قد يحتاج listing للبطاقات، user عادة لا
        return $user->isAdmin();
    }

    public function view(User $user, Card $card): bool
    {
        // admin يرى كل شيء
        if ($user->isAdmin()) {
            return true;
        }

        // user: يرى البطاقة فقط إذا كانت ضمن طلب confirmed له
        return Order::query()
            ->where('card_id', $card->id)
            ->where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Card $card): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Card $card): bool
    {
        return $user->isAdmin();
    }

    /**
     * صلاحية إضافية: عرض بيانات الدخول (username/password) — أكثر تشددًا
     */
    public function viewCredentials(User $user, Card $card): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return Order::query()
            ->where('card_id', $card->id)
            ->where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->exists();
    }
}
