<?php

namespace Ziven\DailyCheckin\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Mattoid\CheckinHistory\Model\UserCheckinHistory;

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
            'canCheckin' => ($lastCheckInDay === null || $currentForumDay->greaterThan($lastCheckInDay))
                && ! $this->hasCheckInOn($user, $currentForumDay->toDateString()),
            'canCheckinContinuous' => $lastCheckInDay !== null && $lastCheckInDay->equalTo($currentForumDay->subDay()),
        ];
    }

    public function hasCheckInOn(User $user, string $date): bool
    {
        return $this->lastCheckInDate($user) === $date
            || UserCheckinHistory::query()
                ->where('user_id', $user->id)->where('last_checkin_date', $date)->exists();
    }

    public function lastCheckInDate(User $user): ?string
    {
        return $this->normalizeStoredDateTime($user->last_checkin_time)?->toDateString();
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
