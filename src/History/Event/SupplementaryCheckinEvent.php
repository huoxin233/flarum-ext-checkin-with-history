<?php

namespace Mattoid\CheckinHistory\Event;

use Flarum\User\User;

class SupplementaryCheckinEvent
{
    public function __construct(
        public ?User $user,
        public string $checkinDate,
        public int $totalContinuousCheckinCountHistory = 0,
        public int $checkinCount = 0
    ) {
    }
}
