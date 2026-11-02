<?php

namespace Primexcalibur\Checkin\Api\Serializer;

use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Settings\SettingsRepositoryInterface;
use Primexcalibur\Checkin\Support\CheckInStateResolver;

class AddForumAttributes
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected CheckInStateResolver $stateResolver
    ) {
    }

    public function __invoke(ForumSerializer $serializer, array $model, array $attributes = []): array
    {
        $attributes['allowCheckIn'] = $serializer->getActor()->hasPermission('checkin.allowCheckIn');
        $attributes['canQueryOthersHistory'] = $serializer->getActor()->can('checkin.queryOthersHistory');

        $attributes['checkinToday'] = $this->stateResolver->currentForumTime()->toDateString();

        $attributes['checkinCdnResources'] = [
            'fullcalendar' => [
                'url' => (string) ($this->settings->get('primexcalibur-checkin-with-history.cdnFullcalendarUrl') ?: ''),
                'sri' => (string) ($this->settings->get('primexcalibur-checkin-with-history.cdnFullcalendarSri') ?: ''),
            ],
            'locales' => [
                'url' => (string) ($this->settings->get('primexcalibur-checkin-with-history.cdnLocalesUrl') ?: ''),
                'sri' => (string) ($this->settings->get('primexcalibur-checkin-with-history.cdnLocalesSri') ?: ''),
            ],
        ];

        return $attributes;
    }
}
