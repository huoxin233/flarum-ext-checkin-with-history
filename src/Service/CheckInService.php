<?php

namespace Ziven\DailyCheckin\Service;

use Flarum\Foundation\ValidationException;
use Flarum\Locale\Translator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Huoxin\MoneyWithHistory\Service\BalanceManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Mattoid\CheckinHistory\CheckinHistoryManager;
use Ziven\DailyCheckin\Event\CheckinUpdated;
use Ziven\DailyCheckin\Support\CheckInStateResolver;

class CheckInService
{
    public function __construct(
        private ConnectionInterface $connection,
        private CheckInStateResolver $stateResolver,
        private SettingsRepositoryInterface $settings,
        private BalanceManager $balances,
        private CheckinHistoryManager $history,
        private Translator $translator,
        private Dispatcher $events
    ) {
    }

    public function checkIn(User $actor): User
    {
        $actor->assertRegistered();
        $actor->assertCan('checkin.allowCheckIn', $actor);

        /** @var User $checkedInUser */
        $checkedInUser = $this->connection->transaction(function () use ($actor) {
            $user = User::query()
                ->whereKey($actor->id)
                ->lockForUpdate()
                ->first();

            if (! $user instanceof User) {
                throw $this->validationException('check-in-failed');
            }

            $state = $this->stateResolver->getCheckInState($user);

            if (! $state['canCheckin']) {
                throw $this->validationException('try-again-later');
            }

            $this->history->recordCheckin($user, $state['currentForumTime']->toDateString(), 0, $state['currentForumTime']);

            $reward = $this->rewardAmount();
            if ($reward !== 0.0) {
                $this->balances->applyBalanceChange(
                    $user, $reward, 'DAILY_CHECKIN_REWARD',
                    'ziven-checkin.forum.money-history.checkin-reward',
                    ['continuousDays' => (int) $user->total_continuous_checkin_count], $user
                );
            }

            $user->save();

            return $user;
        });

        $this->events->dispatch(new CheckinUpdated($checkedInUser));

        return $checkedInUser;
    }

    private function rewardAmount(): float
    {
        return (float) $this->settings->get('ziven-forum-checkin.checkinRewardMoney', 0);
    }

    private function validationException(string $messageKey): ValidationException
    {
        return new ValidationException([
            'message' => $this->translator->trans("ziven-checkin.forum.$messageKey"),
        ]);
    }
}
