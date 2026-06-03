<?php

namespace Ziven\DailyCheckin\AddAttribute;

use Flarum\Api\Serializer\UserSerializer;
use Flarum\User\User;
use Ziven\DailyCheckin\Support\CheckInStateResolver;

class AddUserCheckinAttributes
{
    public function __construct(private CheckInStateResolver $stateResolver)
    {
    }

    public function __invoke(UserSerializer $serializer, User $user)
    {
        $attributes = [
            'lastCheckinTime' => $user->last_checkin_time,
            'totalContinuousCheckIn' => (int) $user->total_continuous_checkin_count,
        ];

        if ($serializer->getActor()->id === $user->id) {
            $state = $this->stateResolver->getCheckInState($user);
            $attributes['canCheckin'] = $state['canCheckin'];
        }

        return $attributes;
    }
}
