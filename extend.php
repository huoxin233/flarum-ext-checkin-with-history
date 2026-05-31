<?php

use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Api\Serializer\UserSerializer;
use Flarum\Extend;
use Flarum\User\User;
use Ziven\checkin\Access\UserPolicy;
use Ziven\checkin\AddAttribute\AddUserCheckinAttributes;
use Ziven\checkin\Api\Controller\CheckInController;

$extend = [
    (new Extend\Frontend('admin'))->js(__DIR__.'/js/dist/admin.js'),
    (new Extend\Frontend('forum'))->js(__DIR__.'/js/dist/forum.js')->css(__DIR__.'/less/forum.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Policy())->modelPolicy(User::class, UserPolicy::class),

    (new Extend\Routes('api'))
        ->post('/checkin', 'ziven-checkin.check-in', CheckInController::class),

    (new Extend\ApiSerializer(UserSerializer::class))->attributes(AddUserCheckinAttributes::class),
    (new Extend\Settings())
        ->serializeToForum('forumCheckinRewardMoney', 'ziven-forum-checkin.checkinRewardMoney', function ($raw) {
            return (float) $raw;
        })
        ->serializeToForum('forumAutoCheckin', 'ziven-forum-checkin.autoCheckIn', 'intval', 0)
        ->serializeToForum('forumAutoCheckinDelay', 'ziven-forum-checkin.autoCheckInDelay', 'intval', 0)
        ->serializeToForum('forumCheckinTimeZone', 'ziven-forum-checkin.checkinTimeZone', 'intval', 0)
        ->serializeToForum('forumCheckinSuccessPromptType', 'ziven-forum-checkin.checkinSuccessPromptType', 'intval', 0)
        ->serializeToForum('forumCheckinSuccessPromptText', 'ziven-forum-checkin.checkinSuccessPromptText', 'strval')
        ->serializeToForum('forumCheckinSuccessPromptRewardText', 'ziven-forum-checkin.checkinSuccessPromptRewardText', 'strval'),

    (new Extend\ApiSerializer(ForumSerializer::class))
        ->attribute('allowCheckIn', function (ForumSerializer $serializer) {
            return $serializer->getActor()->hasPermission('checkin.allowCheckIn');
        }),
];

return $extend;
