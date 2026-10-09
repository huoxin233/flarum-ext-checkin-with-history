<?php

namespace Mattoid\CheckinHistory\Api\Serializer;

use DateTimeInterface;
use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\UserSerializer;
use Flarum\Settings\SettingsRepositoryInterface;
use Mattoid\CheckinHistory\Model\UserCheckinHistory;
use Tobscure\JsonApi\Relationship;

class CheckinHistorySerializer extends AbstractSerializer
{
    protected $type = 'checkin/history';

    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    /**
     * @param UserCheckinHistory $history
     */
    protected function getDefaultAttributes($history): array
    {
        $checkinColor = $this->settings->get('mattoid-forum-checkin.checkin-color') ?: '#2756c6';
        $supplementaryColor = $this->settings->get('mattoid-forum-checkin.supplementary-color') ?: '#ff9900';

        return [
            'id' => (int) $history->id,
            'userId' => (int) $history->user_id,
            'type' => (int) $history->type,
            'totalCheckinCount' => (int) $history->total_checkin_count,
            'totalContinuousCheckinCount' => (int) $history->total_continuous_checkin_count,
            'start' => $history->last_checkin_date instanceof DateTimeInterface
                ? $history->last_checkin_date->format('Y-m-d')
                : (string) $history->last_checkin_date,
            'time' => $history->last_checkin_time instanceof DateTimeInterface
                ? $this->formatDate($history->last_checkin_time)
                : (string) $history->last_checkin_time,
            'color' => (int) $history->type === 1 ? $supplementaryColor : $checkinColor,
        ];
    }

    protected function user($history): ?Relationship
    {
        return $this->hasOne($history, UserSerializer::class);
    }
}
