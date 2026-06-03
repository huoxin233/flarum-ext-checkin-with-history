<?php

namespace Ziven\DailyCheckin\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

class CheckInStateResolver
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function getCheckInState(User $user): array
    {
        $currentForumTime = $this->currentForumTime();
        $currentForumDay = $currentForumTime->startOfDay();
        $lastCheckInTime = $this->normalizeStoredDateTime($user->last_checkin_time);
        $lastCheckInDay = $lastCheckInTime?->startOfDay();

        return [
            'currentForumTime' => $currentForumTime,
            'lastCheckInTime' => $lastCheckInTime,
            'canCheckin' => $lastCheckInDay === null || $currentForumDay->greaterThan($lastCheckInDay),
            'canCheckinContinuous' => $lastCheckInDay !== null && $lastCheckInDay->equalTo($currentForumDay->subDay()),
        ];
    }

    public function currentForumTime(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->addHours($this->timezoneOffsetHours());
    }

    private function normalizeStoredDateTime($value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value)->setTimezone('UTC');
        }

        return CarbonImmutable::parse((string) $value, 'UTC');
    }

    private function timezoneOffsetHours(): int
    {
        return (int) $this->settings->get('ziven-forum-checkin.checkinTimeZone', 0);
    }
}
