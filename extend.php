<?php

use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Api\Serializer\UserSerializer;
use Flarum\Extend;
use Flarum\User\User;
use Ziven\DailyCheckin\Access\UserPolicy;
use Ziven\DailyCheckin\AddAttribute\AddUserCheckinAttributes;
use Ziven\DailyCheckin\Api\Controller\CheckInController;

$extend = [
    (new Extend\Frontend('admin'))->js(__DIR__.'/js/dist/admin.js')->css(__DIR__.'/less/admin.less'),
    (new Extend\Frontend('forum'))->js(__DIR__.'/js/dist/forum.js')->css(__DIR__.'/less/forum.less')
        ->route('/u/{username}/checkin/history', 'mattoid-daily-check-in-history.forum.page.link-name'),

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


$extend[] = (new Extend\ApiSerializer(ForumSerializer::class))
    ->attributes(\Mattoid\CheckinHistory\Api\Serializer\AddForumAttributes::class);
$extend[] = (new Extend\ApiSerializer(UserSerializer::class))
    ->attributes(\Mattoid\CheckinHistory\Api\Serializer\AddUserAttributes::class);
$extend[] = (new Extend\Routes('api'))
    ->get('/checkin/history', 'checkin.history', \Mattoid\CheckinHistory\Api\Controller\ListCheckinHistoryController::class)
    ->post('/supplement/checkin', 'supplement.checkin', \Mattoid\CheckinHistory\Api\Controller\PostCheckinHistoryController::class)
    ->post('/give/checkin/card', 'give.checkin-card', \Mattoid\CheckinHistory\Api\Controller\PostGiveCheckinCardController::class);

return $extend;
