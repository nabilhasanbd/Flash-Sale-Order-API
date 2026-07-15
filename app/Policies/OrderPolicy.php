<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id || $user->role === UserRole::Admin;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Customer;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->role === UserRole::Admin;
    }
}
