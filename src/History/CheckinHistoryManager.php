<?php

namespace Mattoid\CheckinHistory;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\Translator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Huoxin\MoneyWithHistory\Service\BalanceManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Mattoid\CheckinHistory\Event\SupplementaryCheckinEvent;
use Mattoid\CheckinHistory\Model\UserCheckinHistory;
use Ziven\DailyCheckin\Support\CheckInStateResolver;

class CheckinHistoryManager
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Translator $translator,
        protected Dispatcher $events,
        protected ConnectionInterface $connection,
        protected BalanceManager $balances,
        protected CheckInStateResolver $stateResolver
    ) {
    }

    /**
     * Get today's date formatted as YYYY-MM-DD according to configured timezone.
     */
    public function getTodayDate(): string
    {
        return $this->stateResolver->currentForumTime()->toDateString();
    }

    /**
     * Perform supplementary check-in for a user on a given date.
     */
    public function supplementCheckin(User $actor, string $dateStr): UserCheckinHistory
    {
        if ($actor->isGuest() || ! $actor->can('checkin.allowSupplementaryCheckIn')) {
            throw new PermissionDeniedException();
        }

        // Validate date format YYYY-MM-DD
        $dateObj = DateTime::createFromFormat('Y-m-d', $dateStr);
        if (! $dateObj || $dateObj->format('Y-m-d') !== $dateStr) {
            throw new ValidationException([
                'message' => $this->translator->trans('mattoid-daily-check-in-history.api.error.invalid-date'),
            ]);
        }

        $forumTime = $this->stateResolver->currentForumTime();
        $todayStr = $forumTime->toDateString();
        if ($dateStr > $todayStr) {
            throw new ValidationException([
                'message' => $this->translator->trans('mattoid-daily-check-in-history.api.error.greater-than-today'),
            ]);
        }

        // Check earliest allowed date
        $minDate = (string) $this->settings->get('mattoid-forum-checkin.min-supplementary-date', '');
        if (! empty($minDate) && $dateStr < $minDate) {
            throw new ValidationException([
                'message' => $this->translator->trans('mattoid-daily-check-in-history.api.error.min-supplementary-date', ['day' => $minDate]),
            ]);
        }

        // Check allowable date range
        $checkinRange = (int) $this->settings->get('mattoid-forum-checkin.checkin-range', 0);
        $diffDays = (new DateTime($todayStr))->diff(new DateTime($dateStr))->days;
        if ($checkinRange > 0 && ($diffDays - 1) >= $checkinRange) {
            throw new ValidationException([
                'message' => $this->translator->trans('mattoid-daily-check-in-history.api.error.checkin-range', ['day' => $checkinRange]),
            ]);
        }

        $rewardMoney = (float) ($this->settings->get('mattoid-forum-checkin.reward-money') ?? 0);
        $consumption = (float) ($this->settings->get('mattoid-forum-checkin.consumption') ?? 0);
        $onlyCheckinCard = (bool) $this->settings->get('mattoid-forum-checkin.checkin-card', false);
        $checkinIncrease = (float) ($this->settings->get('mattoid-forum-checkin.checkin-increase') ?? 0);
        $maxSupplementaryCheckin = (int) $this->settings->get('mattoid-forum-checkin.max-supplementary-checkin', 0);
        $spanDayCheckin = (bool) $this->settings->get('mattoid-forum-checkin.span-day-checkin', false);

        $supplementCount = 0;
        /** @var UserCheckinHistory $history */
        $history = $this->connection->transaction(function () use (
            $actor,
            $dateStr,
            $todayStr,
            $forumTime,
            $diffDays,
            $rewardMoney,
            $consumption,
            $onlyCheckinCard,
            $checkinIncrease,
            $maxSupplementaryCheckin,
            $spanDayCheckin,
            &$supplementCount
        ) {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($actor->id)->lockForUpdate()->first();
            if (! $lockedUser) {
                throw new PermissionDeniedException();
            }

            // Check if already checked in on this date
            if ($this->stateResolver->hasCheckInOn($lockedUser, $dateStr)) {
                throw new ValidationException([
                    'message' => $this->translator->trans('mattoid-daily-check-in-history.api.error.already-checked-in', ['date' => $dateStr]),
                ]);
            }

            // Check span-day constraint if disabled
            if (! $spanDayCheckin) {
                $tomorrow = date('Y-m-d', strtotime('+1 day', strtotime($dateStr)));
                $signedCountBetween = UserCheckinHistory::query()
                    ->where('user_id', $lockedUser->id)
                    ->where('last_checkin_date', '>=', $tomorrow)
                    ->where('last_checkin_date', '<=', $todayStr)
                    ->count();

                if ($signedCountBetween < $diffDays) {
                    throw new ValidationException([
                        'message' => $this->translator->trans('mattoid-daily-check-in-history.api.error.span-day-checkin', ['date' => $dateStr]),
                    ]);
                }
            }

            // Calculate consecutive supplementary checkins surrounding this date
            $consecutiveSupplementCount = $this->calculateConsecutiveSupplementCount($lockedUser->id, $dateStr);
            $supplementCount = $consecutiveSupplementCount + 1;
            if ($maxSupplementaryCheckin > 0 && ($consecutiveSupplementCount + 1) > $maxSupplementaryCheckin) {
                throw new ValidationException([
                    'message' => $this->translator->trans('mattoid-daily-check-in-history.api.error.max-supplementary-checkin', ['day' => $maxSupplementaryCheckin]),
                ]);
            }

            // Check check-in card or money deductions
            $consumptionMoney = $consumption * ($checkinIncrease * $consecutiveSupplementCount / 100 + 1);

            if ($onlyCheckinCard && (int) $lockedUser->checkin_card <= 0) {
                throw new ValidationException([
                    'message' => $this->translator->trans('mattoid-daily-check-in-history.api.error.insufficient-checkin-card'),
                ]);
            }

            $netBalanceDelta = $rewardMoney;
            if ((int) $lockedUser->checkin_card > 0) {
                $lockedUser->checkin_card = (int) $lockedUser->checkin_card - 1;
            } else {
                $netBalanceDelta -= $consumptionMoney;
            }

            if ($netBalanceDelta !== 0.0) {
                $this->applyBalanceChange($lockedUser, $netBalanceDelta);
            }

            $record = $this->recordCheckin($lockedUser, $dateStr, 1, $forumTime);

            $lockedUser->save();

            $record->setRelation('user', $lockedUser);

            return $record;
        });

        $this->events->dispatch(new SupplementaryCheckinEvent($history->user, $dateStr, (int) $history->total_continuous_checkin_count, $supplementCount));

        return $history;
    }

    /** The caller holds the user row lock and saves the user in the same transaction. */
    public function recordCheckin(User $user, string $date, int $type, CarbonImmutable $forumTime): UserCheckinHistory
    {
        $lastDate = $this->stateResolver->lastCheckInDate($user);
        $previousDate = CarbonImmutable::parse($date, 'UTC')->subDay()->toDateString();
        $previous = UserCheckinHistory::query()->where('user_id', $user->id)
            ->where('last_checkin_date', $previousDate)->first();
        // Preserve the known legacy streak when daily records were not installed yet.
        $continuous = 1 + ($previous ? (int) $previous->total_continuous_checkin_count
            : ($lastDate === $previousDate ? (int) $user->total_continuous_checkin_count : 0));

        $user->total_checkin_count = (int) $user->total_checkin_count + 1;
        $record = new UserCheckinHistory();
        $record->user_id = $user->id;
        $record->type = $type;
        $record->last_checkin_date = $date;
        $record->last_checkin_time = Carbon::now('UTC');
        $record->total_checkin_count = $user->total_checkin_count;
        $record->total_continuous_checkin_count = $continuous;
        $record->save();

        $endDate = $date;
        $nextDate = CarbonImmutable::parse($date, 'UTC')->addDay();
        while ($next = UserCheckinHistory::query()->where('user_id', $user->id)
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

    /**
     * Calculate how many consecutive supplementary check-ins are linked to this date.
     */
    protected function calculateConsecutiveSupplementCount(int $userId, string $dateStr): int
    {
        $count = 0;

        // Check backwards
        $current = $dateStr;
        while (true) {
            $prev = date('Y-m-d', strtotime('-1 day', strtotime($current)));
            $hasSupplement = UserCheckinHistory::query()
                ->where('user_id', $userId)
                ->where('last_checkin_date', $prev)
                ->where('type', 1)
                ->exists();

            if (! $hasSupplement) {
                break;
            }
            $count++;
            $current = $prev;
        }

        // Check forwards
        $current = $dateStr;
        while (true) {
            $next = date('Y-m-d', strtotime('+1 day', strtotime($current)));
            $hasSupplement = UserCheckinHistory::query()
                ->where('user_id', $userId)
                ->where('last_checkin_date', $next)
                ->where('type', 1)
                ->exists();

            if (! $hasSupplement) {
                break;
            }
            $count++;
            $current = $next;
        }

        return $count;
    }

    /**
     * Apply money changes through Money with History within the caller transaction.
     */
    protected function applyBalanceChange(User $user, float $amount): void
    {
        $isCost = $amount < 0;
        $source = $isCost ? 'SUPPLEMENTARY_CHECKIN_COST' : 'SUPPLEMENTARY_CHECKIN_REWARD';
        $sourceKey = $isCost
            ? 'mattoid-daily-check-in-history.forum.money-history.supplementary-checkin-cost'
            : 'mattoid-daily-check-in-history.forum.money-history.supplementary-checkin-reward';

        if (! $this->balances->applyBalanceChange($user, $amount, $source, $sourceKey, [], $user, preventOverdraft: true)) {
            throw new ValidationException([
                'message' => $this->translator->trans('mattoid-daily-check-in-history.api.error.insufficient-balance'),
            ]);
        }
    }

    /**
     * Give supplementary check-in cards to users.
     */
    public function giveCheckinCards(User $actor, ?string $usernamesInput, int $amount, bool $allUsers = false): int
    {
        if (! $actor->can('checkin.issuanceOfSupplementaryCards')) {
            throw new PermissionDeniedException();
        }

        if ($amount <= 0) {
            throw new ValidationException([
                'message' => $this->translator->trans('mattoid-daily-check-in-history.api.error.invalid-amount'),
            ]);
        }

        if ($allUsers) {
            User::query()->whereNull('checkin_card')->update(['checkin_card' => 0]);

            return User::query()->increment('checkin_card', $amount);
        }

        if (empty($usernamesInput)) {
            throw new ValidationException([
                'message' => $this->translator->trans('mattoid-daily-check-in-history.api.error.empty-usernames'),
            ]);
        }

        $separators = [',', '，', '|', ' ', "\n", "\r", "\t"];
        $normalized = str_replace($separators, ',', $usernamesInput);
        $usernames = array_values(array_filter(array_map('trim', explode(',', $normalized))));

        if (empty($usernames)) {
            throw new ValidationException([
                'message' => $this->translator->trans('mattoid-daily-check-in-history.api.error.invalid-usernames'),
            ]);
        }

        User::query()->whereIn('username', $usernames)->whereNull('checkin_card')->update(['checkin_card' => 0]);
        $count = User::query()->whereIn('username', $usernames)->increment('checkin_card', $amount);

        if ($count === 0) {
            throw new ValidationException([
                'message' => $this->translator->trans('mattoid-daily-check-in-history.api.error.invalid-usernames'),
            ]);
        }

        return $count;
    }
}
