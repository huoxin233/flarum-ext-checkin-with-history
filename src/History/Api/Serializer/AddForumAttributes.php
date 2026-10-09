<?php

namespace Mattoid\CheckinHistory\Api\Serializer;

use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Settings\SettingsRepositoryInterface;

class AddForumAttributes
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected \Ziven\DailyCheckin\Support\CheckInStateResolver $stateResolver
    ) {
    }

    public function __invoke(ForumSerializer $serializer, array $model, array $attributes = []): array
    {
        $attributes['canQueryOthersHistory'] = $serializer->getActor()->can('checkin.queryOthersHistory');

        $attributes['checkinToday'] = $this->stateResolver->currentForumTime()->toDateString();

        $attributes['checkinCdnResources'] = [
            'fullcalendar' => [
                'url' => (string) ($this->settings->get('mattoid-forum-checkin.cdn-fullcalendar-url') ?: ''),
                'sri' => (string) ($this->settings->get('mattoid-forum-checkin.cdn-fullcalendar-sri') ?: ''),
            ],
            'locales' => [
                'url' => (string) ($this->settings->get('mattoid-forum-checkin.cdn-locales-url') ?: ''),
                'sri' => (string) ($this->settings->get('mattoid-forum-checkin.cdn-locales-sri') ?: ''),
            ],
        ];

        return $attributes;
    }
}
