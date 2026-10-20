<?php

use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Api\Serializer\UserSerializer;
use Flarum\Extend;
use Flarum\User\User;
use Mattoid\Store\Extend\StoreExtend;
use Primexcalibur\Checkin\Access\UserPolicy;
use Primexcalibur\Checkin\Api\Controller\CheckInController;
use Primexcalibur\Checkin\Api\Controller\ListCheckinHistoryController;
use Primexcalibur\Checkin\Api\Controller\PostCheckinHistoryController;
use Primexcalibur\Checkin\Api\Serializer\AddForumAttributes;
use Primexcalibur\Checkin\Api\Serializer\AddUserAttributes;
use Primexcalibur\Checkin\Integration\Store\CheckinCardGoods;
use Primexcalibur\Checkin\Integration\Store\CheckinCardUse;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')->css(__DIR__.'/less/admin.less'),
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')->css(__DIR__.'/less/forum.less')
        ->route('/u/{username}/checkin/history', 'user.checkin.history'),
    new Extend\Locales(__DIR__.'/locale'),
    (new Extend\Policy())->modelPolicy(User::class, UserPolicy::class),
    (new Extend\Routes('api'))
        ->post('/checkin', 'checkin.create', CheckInController::class)
        ->get('/checkin/history', 'checkin.history', ListCheckinHistoryController::class)
        ->post('/supplement/checkin', 'checkin.supplement', PostCheckinHistoryController::class),
    (new Extend\ApiSerializer(UserSerializer::class))->attributes(AddUserAttributes::class),
    (new Extend\ApiSerializer(ForumSerializer::class))->attributes(AddForumAttributes::class),
    // Persisted keys are shared with the original extensions so upgrades retain their settings.
    (new Extend\Settings())
        ->serializeToForum('forumCheckinRewardMoney', 'ziven-forum-checkin.checkinRewardMoney', 'floatval', 0)
        ->serializeToForum('forumAutoCheckin', 'ziven-forum-checkin.autoCheckIn', 'intval', 0)
        ->serializeToForum('forumAutoCheckinDelay', 'ziven-forum-checkin.autoCheckInDelay', 'intval', 0)
        ->serializeToForum('forumCheckinTimeZone', 'ziven-forum-checkin.checkinTimeZone', 'intval', 0)
        ->serializeToForum('forumCheckinSuccessPromptType', 'ziven-forum-checkin.checkinSuccessPromptType', 'intval', 0)
        ->serializeToForum('forumCheckinSuccessPromptText', 'ziven-forum-checkin.checkinSuccessPromptText', 'strval', '')
        ->serializeToForum('forumCheckinSuccessPromptRewardText', 'ziven-forum-checkin.checkinSuccessPromptRewardText', 'strval', ''),
    (new Extend\Conditional())
        ->whenExtensionEnabled('mattoid-store', function () {
            return [
                (new StoreExtend('checkin-card'))
                    ->addStoreGoods(CheckinCardGoods::class)
                    ->addUse(CheckinCardUse::class),
            ];
        }),
];
