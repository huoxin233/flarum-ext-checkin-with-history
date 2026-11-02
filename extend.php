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
use Primexcalibur\Checkin\Model\CheckinRecord;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')->css(__DIR__.'/less/admin.less'),
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')->css(__DIR__.'/less/forum.less')
        ->route('/u/{username}/checkin/history', 'user.checkin.history'),
    new Extend\Locales(__DIR__.'/locale'),
    (new Extend\Model(User::class))
        ->hasMany('checkinRecords', CheckinRecord::class, 'user_id'),
    (new Extend\Policy())->modelPolicy(User::class, UserPolicy::class),
    (new Extend\Routes('api'))
        ->post('/checkin', 'checkin.create', CheckInController::class)
        ->get('/checkin/history', 'checkin.history', ListCheckinHistoryController::class)
        ->post('/supplement/checkin', 'checkin.supplement', PostCheckinHistoryController::class),
    (new Extend\ApiSerializer(UserSerializer::class))->attributes(AddUserAttributes::class),
    (new Extend\ApiSerializer(ForumSerializer::class))->attributes(AddForumAttributes::class),
    (new Extend\Settings())
        ->serializeToForum('forumCheckinRewardMoney', 'primexcalibur-checkin-with-history.checkinRewardMoney', 'floatval', 0)
        ->serializeToForum('forumAutoCheckin', 'primexcalibur-checkin-with-history.autoCheckIn', 'intval', 0)
        ->serializeToForum('forumAutoCheckinDelay', 'primexcalibur-checkin-with-history.autoCheckInDelay', 'intval', 0)
        ->serializeToForum('forumCheckinTimeZone', 'primexcalibur-checkin-with-history.checkinTimeZone', 'intval', 0)
        ->serializeToForum('forumCheckinSuccessPromptType', 'primexcalibur-checkin-with-history.checkinSuccessPromptType', 'intval', 0)
        ->serializeToForum('forumCheckinSuccessPromptText', 'primexcalibur-checkin-with-history.checkinSuccessPromptText', 'strval', '')
        ->serializeToForum('forumCheckinSuccessPromptRewardText', 'primexcalibur-checkin-with-history.checkinSuccessPromptRewardText', 'strval', ''),
    (new Extend\Conditional())
        ->whenExtensionEnabled('mattoid-store', function () {
            return [
                (new StoreExtend('checkin-card'))
                    ->addStoreGoods(CheckinCardGoods::class)
                    ->addUse(CheckinCardUse::class),
            ];
        }),
];
