<?php

namespace Primexcalibur\Checkin\Api\Serializer;

use Flarum\Api\Serializer\UserSerializer;
use Flarum\User\User;
use Primexcalibur\Checkin\Support\CheckinCardManager;
use Primexcalibur\Checkin\Support\CheckInStateResolver;

class AddUserAttributes
{
    public function __construct(
        private CheckInStateResolver $stateResolver,
        private CheckinCardManager $cardManager
    ) {
    }

    public function __invoke(UserSerializer $serializer, User $user, array $attributes = []): array
    {
        $actor = $serializer->getActor();
        $attributes['lastCheckinTime'] = $user->last_checkin_time;
        $attributes['totalContinuousCheckIn'] = (int) $user->total_continuous_checkin_count;
        $attributes['totalCheckinCount'] = (int) $user->total_checkin_count;

        if (! $actor->isGuest() && $actor->id === $user->id) {
            $attributes['canCheckin'] = (bool) ($actor->can('checkin.allowCheckIn', $user) && $this->stateResolver->canCheckIn($user));
            $attributes['checkinCard'] = $this->cardManager->getAvailableCards($user);
        }

        return $attributes;
    }
}
