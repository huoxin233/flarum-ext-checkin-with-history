<?php

namespace Mattoid\CheckinHistory\Api\Serializer;

use Flarum\Api\Serializer\UserSerializer;
use Flarum\User\User;

class AddUserAttributes
{
    public function __invoke(UserSerializer $serializer, User $user, array $attributes): array
    {
        $actor = $serializer->getActor();

        if (! $actor->isGuest() && ($actor->id === $user->id || $actor->can('checkin.issuanceOfSupplementaryCards'))) {
            $attributes['checkinCard'] = (int) $user->checkin_card;
        }

        return $attributes;
    }
}
