<?php

namespace Primexcalibur\Checkin\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Primexcalibur\Checkin\Model\CheckinRecord;

class CheckInStateResolver
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function canCheckIn(User $user, ?CarbonImmutable $forumTime = null): bool
    {
        $today = ($forumTime ?? $this->currentForumTime())->toDateString();
        $lastDate = $this->lastCheckInDate($user);

        if ($lastDate !== null && $today <= $lastDate) {
            return false;
        }

        return ! $this->hasCheckInOn($user, $today);
    }

    public function hasCheckInOn(User $user, string $date): bool
    {
        return $this->lastCheckInDate($user) === $date
            || CheckinRecord::query()
                ->where('user_id', $user->id)->where('checkin_date', $date)->exists();
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
        return (int) ($this->settings->get('primexcalibur-checkin-with-history.checkinTimeZone')
            ?? $this->settings->get('ziven-forum-checkin.checkinTimeZone', 0));
    }
}
