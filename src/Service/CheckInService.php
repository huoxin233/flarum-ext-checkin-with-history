<?php

namespace Ziven\checkin\Service;

use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\Translator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Ziven\checkin\Support\CheckInStateResolver;
use Huoxin\MoneyWithHistory\Service\BalanceManager;
use Illuminate\Contracts\Events\Dispatcher;
use Ziven\checkin\Event\CheckinUpdated;

class CheckInService
{
    public function __construct(
        private ConnectionInterface $connection,
        private CheckInStateResolver $stateResolver,
        private SettingsRepositoryInterface $settings,
        private ExtensionManager $extensions,
        private Container $container,
        private Translator $translator,
        private Dispatcher $events
    ) {
    }

    public function checkIn(User $actor): User
    {
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

            $user->last_checkin_time = $state['currentForumTime']->toDateTimeString();
            $user->total_checkin_count = (int) $user->total_checkin_count + 1;
            $user->total_continuous_checkin_count = $state['canCheckinContinuous']
                ? (int) $user->total_continuous_checkin_count + 1
                : 1;

            $reward = $this->rewardAmount();

            if ($this->shouldApplyReward($reward)) {
                $balanceManager = $this->container->make(BalanceManager::class);

                if (method_exists($balanceManager, 'applyBalanceChange')) {
                    $balanceManager->applyBalanceChange(
                        $user,
                        $reward,
                        'DAILY_CHECKIN_REWARD',
                        'ziven-checkin.forum.money-history.checkin-reward',
                        [
                            'continuousDays' => (int) $user->total_continuous_checkin_count,
                        ],
                        $user
                    );
                }
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

    private function shouldApplyReward(float $reward): bool
    {
        return $reward !== 0.0
            && (
                $this->extensions->isEnabled('antoinefr-money')
                || $this->extensions->isEnabled('huoxin-money-with-history')
            );
    }

    private function validationException(string $messageKey): ValidationException
    {
        return new ValidationException([
            'message' => $this->translator->trans("ziven-checkin.forum.$messageKey"),
        ]);
    }
}
