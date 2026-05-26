<?php

namespace Ziven\checkin\Event;

use Flarum\User\User;

class CheckinUpdated
{
    public function __construct(public ?User $user = null)
    {
    }
}
