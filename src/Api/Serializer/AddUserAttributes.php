<?php

namespace Primexcalibur\Checkin\Api\Serializer;

use Flarum\Api\Serializer\UserSerializer;
use Flarum\User\User;
use Primexcalibur\Checkin\Support\CheckInStateResolver;

class AddUserAttributes
{
    public function __construct(private CheckInStateResolver $stateResolver)
    {
    }

    public function __invoke(UserSerializer $serializer, User $user, array $attributes = []): array
    {
        $actor = $serializer->getActor();
        $attributes['lastCheckinTime'] = $user->last_checkin_time;
        $attributes['totalContinuousCheckIn'] = (int) $user->total_continuous_checkin_count;
        $attributes['totalCheckinCount'] = (int) $user->total_checkin_count;

        if (! $actor->isGuest() && $actor->id === $user->id) {
            $attributes['canCheckin'] = (bool) ($actor->can('checkin.allowCheckIn', $user) && $this->stateResolver->canCheckIn($user));
        }

        if (! $actor->isGuest() && ($actor->id === $user->id || $actor->can('checkin.issuanceOfSupplementaryCards'))) {
            $attributes['checkinCard'] = (int) $user->checkin_card;
        }

        return $attributes;
    }
}
