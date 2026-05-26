<?php

namespace Ziven\checkin\AddAttribute;

use Flarum\Api\Serializer\UserSerializer;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Ziven\checkin\Support\CheckInStateResolver;

class AddUserCheckinAttributes
{
    public function __construct(
        private CheckInStateResolver $stateResolver,
        private ExtensionManager $extensions
    ) {
    }

    public function __invoke(UserSerializer $serializer, User $user)
    {
        $attributes = [
            'lastCheckinTime' => $user->last_checkin_time,
            'totalContinuousCheckIn' => (int) $user->total_continuous_checkin_count,
        ];

        if ($serializer->getActor()->id === $user->id) {
            $state = $this->stateResolver->getCheckInState($user);

            $attributes['checkInCompatibleExtensions'] = $this->compatibleExtensions();
            $attributes['canCheckin'] = $state['canCheckin'];
            $attributes['canCheckinContinuous'] = $state['canCheckinContinuous'];
        }

        return $attributes;
    }

    private function compatibleExtensions(): array
    {
        return $this->extensions->isEnabled('fof-follow-tags')
            ? ['fof-follow-tags']
            : [];
    }
}
