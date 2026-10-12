<?php

namespace Primexcalibur\Checkin\Access;

use Carbon\Carbon;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class UserPolicy extends AbstractPolicy
{
    public function allowCheckIn(User $actor, User $user)
    {
        if ($actor->id === $user->id && $actor->hasPermission('checkin.allowCheckIn') && ! $this->isSuspended($user)) {
            return $this->allow();
        }

        return $this->deny();
    }

    public function allowSupplementaryCheckIn(User $actor, User $user)
    {
        if ($actor->id === $user->id && $actor->hasPermission('checkin.allowSupplementaryCheckIn') && ! $this->isSuspended($user)) {
            return $this->allow();
        }

        return $this->deny();
    }

    protected function isSuspended(User $user): bool
    {
        if (empty($user->suspended_until)) {
            return false;
        }

        $suspendedUntil = $user->suspended_until instanceof Carbon
            ? $user->suspended_until
            : Carbon::parse($user->suspended_until);

        return $suspendedUntil->isFuture();
    }
}
