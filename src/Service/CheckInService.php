<?php

namespace Primexcalibur\Checkin\Service;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\Translator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Huoxin\MoneyWithHistory\Service\BalanceManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Primexcalibur\Checkin\Event\CheckedIn;
use Primexcalibur\Checkin\Model\CheckinRecord;
use Primexcalibur\Checkin\Support\CheckInStateResolver;

class CheckInService
{
    public function __construct(
        private ConnectionInterface $connection,
        private CheckInStateResolver $stateResolver,
        private SettingsRepositoryInterface $settings,
        private BalanceManager $balances,
        private Translator $translator,
        private Dispatcher $events
    ) {
    }

    public function checkIn(User $actor): User
    {
        $actor->assertRegistered();
        $actor->assertCan('checkin.allowCheckIn', $actor);

        return $this->perform($actor)->user;
    }

    public function supplement(User $actor, string $date): CheckinRecord
    {
        if ($actor->isGuest() || ! $actor->can('checkin.allowSupplementaryCheckIn')) {
            throw new PermissionDeniedException();
        }

        return $this->perform($actor, $date);
    }

    private function perform(User $actor, ?string $date = null): CheckinRecord
    {
        $supplementary = $date !== null;
        $supplementCount = 0;

        $record = $this->connection->transaction(function () use ($actor, $date, $supplementary, &$supplementCount) {
            $user = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $forumTime = $this->stateResolver->currentForumTime();
            $date = $date ?? $forumTime->toDateString();

            if ($supplementary) {
                $this->validateSupplementDate($date, $forumTime);
            }

            if ($supplementary
                ? $this->stateResolver->hasCheckInOn($user, $date)
                : ! $this->stateResolver->canCheckIn($user, $forumTime)) {
                throw $this->error('api.error.already-checked-in', ['date' => $date]);
            }

            if ($supplementary) {
                $supplementCount = $this->validateSupplementSequence($user, $date, $forumTime) + 1;
                $amount = $this->supplementBalanceDelta($user, $supplementCount);
            } else {
                $amount = (float) $this->settings->get('ziven-forum-checkin.checkinRewardMoney', 0);
            }

            $record = $this->recordCheckin($user, $date, $supplementary, $forumTime);
            $this->applyBalanceChange($user, $amount, $supplementary);
            $user->save();
            $record->setRelation('user', $user);

            return $record;
        });

        $this->events->dispatch(new CheckedIn($record->user, $record, $supplementCount));

        return $record;
    }

    private function validateSupplementDate(string $date, CarbonImmutable $forumTime): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw $this->error('api.error.invalid-date');
        }

        if ($date > $forumTime->toDateString()) {
            throw $this->error('api.error.greater-than-today');
        }

        $minDate = (string) $this->settings->get('mattoid-forum-checkin.min-supplementary-date', '');
        if ($minDate !== '' && $date < $minDate) {
            throw $this->error('api.error.min-supplementary-date', ['day' => $minDate]);
        }

        $range = (int) $this->settings->get('mattoid-forum-checkin.checkin-range', 0);
        $days = CarbonImmutable::parse($date, 'UTC')->diffInDays($forumTime->startOfDay());
        if ($range > 0 && $days > $range) {
            throw $this->error('api.error.checkin-range', ['day' => $range]);
        }
    }

    private function validateSupplementSequence(User $user, string $date, CarbonImmutable $forumTime): int
    {
        $day = CarbonImmutable::parse($date, 'UTC');
        if (! (bool) $this->settings->get('mattoid-forum-checkin.span-day-checkin', false)) {
            $signedDays = CheckinRecord::query()->where('user_id', $user->id)
                ->where('last_checkin_date', '>', $date)
                ->where('last_checkin_date', '<=', $forumTime->toDateString())
                ->distinct()->count('last_checkin_date');
            if ($signedDays < $day->diffInDays($forumTime->startOfDay())) {
                throw $this->error('api.error.span-day-checkin', ['date' => $date]);
            }
        }

        $count = 0;
        foreach ([-1, 1] as $direction) {
            $cursor = $day->addDays($direction);
            while (CheckinRecord::query()->where('user_id', $user->id)
                ->where('last_checkin_date', $cursor->toDateString())->where('type', 1)->exists()) {
                $count++;
                $cursor = $cursor->addDays($direction);
            }
        }

        $max = (int) $this->settings->get('mattoid-forum-checkin.max-supplementary-checkin', 0);
        if ($max > 0 && $count + 1 > $max) {
            throw $this->error('api.error.max-supplementary-checkin', ['day' => $max]);
        }

        return $count;
    }

    private function supplementBalanceDelta(User $user, int $supplementCount): float
    {
        $reward = (float) $this->settings->get('mattoid-forum-checkin.reward-money', 0);
        if ((int) $user->checkin_card > 0) {
            $user->checkin_card = (int) $user->checkin_card - 1;

            return $reward;
        }

        if ((bool) $this->settings->get('mattoid-forum-checkin.checkin-card', false)) {
            throw $this->error('api.error.insufficient-checkin-card');
        }

        $cost = (float) $this->settings->get('mattoid-forum-checkin.consumption', 0);
        $increase = (float) $this->settings->get('mattoid-forum-checkin.checkin-increase', 0);

        return $reward - $cost * (1 + $increase * ($supplementCount - 1) / 100);
    }

    private function applyBalanceChange(User $user, float $amount, bool $supplementary): void
    {
        if ($amount === 0.0) {
            return;
        }

        $source = 'DAILY_CHECKIN_REWARD';
        $description = 'checkin-reward';
        if ($supplementary) {
            $source = $amount < 0 ? 'SUPPLEMENTARY_CHECKIN_COST' : 'SUPPLEMENTARY_CHECKIN_REWARD';
            $description = $amount < 0 ? 'supplementary-checkin-cost' : 'supplementary-checkin-reward';
        }

        if (! $this->balances->applyBalanceChange(
            $user, $amount, $source,
            'primexcalibur-checkin-with-history.forum.money-history.'.$description,
            ['continuousDays' => (int) $user->total_continuous_checkin_count],
            $user, preventOverdraft: $supplementary
        )) {
            throw $this->error('api.error.insufficient-balance');
        }
    }

    private function recordCheckin(User $user, string $date, bool $supplementary, CarbonImmutable $forumTime): CheckinRecord
    {
        $lastDate = $this->stateResolver->lastCheckInDate($user);
        $previousDate = CarbonImmutable::parse($date, 'UTC')->subDay()->toDateString();
        $previous = CheckinRecord::query()->where('user_id', $user->id)
            ->where('last_checkin_date', $previousDate)->first();
        // Aggregate-only legacy installations have a known streak but no earlier daily records.
        $continuous = 1 + ($previous ? (int) $previous->total_continuous_checkin_count
            : ($lastDate === $previousDate ? (int) $user->total_continuous_checkin_count : 0));

        $user->total_checkin_count = (int) $user->total_checkin_count + 1;
        $record = new CheckinRecord();
        $record->user_id = $user->id;
        $record->type = (int) $supplementary;
        $record->last_checkin_date = $date;
        $record->last_checkin_time = CarbonImmutable::now('UTC');
        $record->total_checkin_count = $user->total_checkin_count;
        $record->total_continuous_checkin_count = $continuous;
        $record->save();

        $endDate = $date;
        $nextDate = CarbonImmutable::parse($date, 'UTC')->addDay();
        while ($next = CheckinRecord::query()->where('user_id', $user->id)
            ->where('last_checkin_date', $nextDate->toDateString())->first()) {
            $next->total_continuous_checkin_count = ++$continuous;
            $next->save();
            $endDate = $nextDate->toDateString();
            $nextDate = $nextDate->addDay();
        }

        if ($lastDate === null || $endDate >= $lastDate) {
            $user->total_continuous_checkin_count = $continuous;
            if ($lastDate === null || $endDate > $lastDate) {
                $user->last_checkin_time = $endDate.' '.$forumTime->format('H:i:s');
            }
        }

        return $record;
    }

    private function error(string $key, array $parameters = []): ValidationException
    {
        return new ValidationException([
            'message' => $this->translator->trans('primexcalibur-checkin-with-history.'.$key, $parameters),
        ]);
    }
}
