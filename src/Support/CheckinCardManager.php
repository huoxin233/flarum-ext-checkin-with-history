<?php

namespace Primexcalibur\Checkin\Support;

use Carbon\Carbon;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

class CheckinCardManager
{
    public function __construct(
        private ExtensionManager $extensions,
        private ConnectionInterface $connection
    ) {
    }

    public function isStoreEnabled(): bool
    {
        return $this->extensions->isEnabled('mattoid-store');
    }

    /**
     * Get the total available check-in cards for the user.
     * Combines valid unused cards in the store backpack and legacy user table counters.
     */
    public function getAvailableCards(User $user): int
    {
        $cards = 0;

        if ($this->isStoreEnabled() && $this->connection->getSchemaBuilder()->hasTable('store_cart')) {
            $cards += (int) $this->connection->table('store_cart')
                ->where('user_id', $user->id)
                ->where('code', 'checkin-card')
                ->where('status', 1)
                ->where(function ($query) {
                    $query->whereNull('outtime')->orWhere('outtime', '>', Carbon::now());
                })
                ->count();
        }

        $cards += max(0, (int) $user->checkin_card);

        return $cards;
    }

    /**
     * Consume one check-in card.
     * Consumes from the store backpack first if store is enabled, otherwise decrements users.checkin_card.
     * Returns true if a card was consumed, false if insufficient cards.
     */
    public function consumeCard(User $user): bool
    {
        if ($this->isStoreEnabled() && $this->connection->getSchemaBuilder()->hasTable('store_cart')) {
            $cartRow = $this->connection->table('store_cart')
                ->where('user_id', $user->id)
                ->where('code', 'checkin-card')
                ->where('status', 1)
                ->where(function ($query) {
                    $query->whereNull('outtime')->orWhere('outtime', '>', Carbon::now());
                })
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->first();

            if ($cartRow) {
                $this->connection->table('store_cart')
                    ->where('id', $cartRow->id)
                    ->update([
                        'status' => 3, // consumed
                        'updated_at' => Carbon::now(),
                    ]);

                return true;
            }
        }

        if ((int) $user->checkin_card > 0) {
            $user->checkin_card = (int) $user->checkin_card - 1;

            return true;
        }

        return false;
    }
}
