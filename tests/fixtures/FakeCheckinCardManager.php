<?php

namespace Primexcalibur\Checkin\Tests\fixtures;

use Flarum\User\User;
use Primexcalibur\Checkin\Support\CheckinCardManager;

class FakeCheckinCardManager extends CheckinCardManager
{
    public int $available;
    public int $consumed = 0;

    public function __construct(int $available = 10)
    {
        $this->available = $available;
    }

    public function isStoreEnabled(): bool
    {
        return true;
    }

    public function hasStoreCartTable(): bool
    {
        return true;
    }

    public function getAvailableCards(User $user): int
    {
        return $this->available;
    }

    public function consumeCard(User $user): bool
    {
        if ($this->available > 0) {
            $this->available--;
            $this->consumed++;

            return true;
        }

        return false;
    }

    public function setAvailableCards(int $count): void
    {
        $this->available = $count;
    }
}
