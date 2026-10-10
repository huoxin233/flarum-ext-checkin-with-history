<?php

namespace Primexcalibur\Checkin\Service;

use Flarum\Foundation\ValidationException;
use Flarum\Locale\Translator;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;

class CheckinCardService
{
    public function __construct(private Translator $translator)
    {
    }

    public function giveCheckinCards(User $actor, ?string $usernamesInput, int $amount, bool $allUsers = false): int
    {
        if (! $actor->can('checkin.issuanceOfSupplementaryCards')) {
            throw new PermissionDeniedException();
        }

        if ($amount <= 0) {
            throw new ValidationException([
                'message' => $this->translator->trans('primexcalibur-checkin-with-history.api.error.invalid-amount'),
            ]);
        }

        if ($allUsers) {
            User::query()->whereNull('checkin_card')->update(['checkin_card' => 0]);

            return User::query()->increment('checkin_card', $amount);
        }

        if (empty($usernamesInput)) {
            throw new ValidationException([
                'message' => $this->translator->trans('primexcalibur-checkin-with-history.api.error.empty-usernames'),
            ]);
        }

        $separators = [',', '，', '|', ' ', "\n", "\r", "\t"];
        $normalized = str_replace($separators, ',', $usernamesInput);
        $usernames = array_values(array_filter(array_map('trim', explode(',', $normalized))));

        if (empty($usernames)) {
            throw new ValidationException([
                'message' => $this->translator->trans('primexcalibur-checkin-with-history.api.error.invalid-usernames'),
            ]);
        }

        User::query()->whereIn('username', $usernames)->whereNull('checkin_card')->update(['checkin_card' => 0]);
        $count = User::query()->whereIn('username', $usernames)->increment('checkin_card', $amount);

        if ($count === 0) {
            throw new ValidationException([
                'message' => $this->translator->trans('primexcalibur-checkin-with-history.api.error.invalid-usernames'),
            ]);
        }

        return $count;
    }
}
