<?php

namespace Primexcalibur\Checkin\Api\Serializer;

use DateTimeInterface;
use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\UserSerializer;
use Flarum\Settings\SettingsRepositoryInterface;
use Primexcalibur\Checkin\Model\CheckinRecord;
use Tobscure\JsonApi\Relationship;

class CheckinHistorySerializer extends AbstractSerializer
{
    protected $type = 'checkin/history';

    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    /**
     * @param CheckinRecord $history
     */
    protected function getDefaultAttributes($history): array
    {
        $checkinColor = $this->settings->get('primexcalibur-checkin-with-history.checkinColor') ?: '#2756c6';
        $supplementaryColor = $this->settings->get('primexcalibur-checkin-with-history.supplementaryColor') ?: '#ff9900';

        $checkinDate = $history->checkin_date instanceof DateTimeInterface
            ? $history->checkin_date->format('Y-m-d')
            : (string) $history->checkin_date;

        $checkedInAt = $history->checked_in_at instanceof DateTimeInterface
            ? $this->formatDate($history->checked_in_at)
            : (string) $history->checked_in_at;

        return [
            'id' => (int) $history->id,
            'userId' => (int) $history->user_id,
            'type' => (int) $history->type,
            'streak' => (int) $history->streak,
            'date' => $checkinDate,
            'checkedInAt' => $checkedInAt,
            'color' => (int) $history->type === 1 ? $supplementaryColor : $checkinColor,
        ];
    }

    protected function user($history): ?Relationship
    {
        return $this->hasOne($history, UserSerializer::class);
    }
}
