<?php

namespace Primexcalibur\Checkin\Event;

use Flarum\User\User;
use Primexcalibur\Checkin\Model\CheckinRecord;

class CheckedIn
{
    public function __construct(
        public User $user,
        public CheckinRecord $record,
        public int $supplementCount = 0
    ) {
    }
}
