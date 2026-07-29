<?php

namespace App\Policies;

use App\Models\DeadLetter;
use App\Models\User;

class RecoveryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view dead letters') || $user->hasRole('admin');
    }

    public function view(User $user, DeadLetter $deadLetter): bool
    {
        return $this->viewAny($user);
    }

    public function retry(User $user, DeadLetter $deadLetter): bool
    {
        return $user->hasPermissionTo('retry dead letters') || $user->hasRole('admin');
    }

    public function skip(User $user, DeadLetter $deadLetter): bool
    {
        return $this->retry($user, $deadLetter);
    }

    public function reassign(User $user, DeadLetter $deadLetter): bool
    {
        return $this->retry($user, $deadLetter);
    }

    public function dismiss(User $user, DeadLetter $deadLetter): bool
    {
        return $user->hasPermissionTo('dismiss dead letters') || $user->hasRole('admin');
    }
}
