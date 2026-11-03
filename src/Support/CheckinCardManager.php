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
        return $this->extensions->isEnabled('mattoid-store') || $this->hasStoreCartTable();
    }

    public function hasStoreCartTable(): bool
    {
        return $this->connection->getSchemaBuilder()->hasTable('store_cart');
    }

    /**
     * Get the total available check-in cards for the user from the store backpack.
     */
    public function getAvailableCards(User $user): int
    {
        if (! $this->isStoreEnabled() || ! $this->hasStoreCartTable()) {
            return 0;
        }

        return (int) $this->connection->table('store_cart')
            ->where('user_id', $user->id)
            ->where('code', 'checkin-card')
            ->where('status', 1)
            ->where(function ($query) {
                $query->whereNull('outtime')->orWhere('outtime', '>', Carbon::now());
            })
            ->count();
    }

    /**
     * Consume one check-in card from the store backpack.
     * Returns true if a card was consumed, false if insufficient cards.
     */
    public function consumeCard(User $user): bool
    {
        if (! $this->isStoreEnabled() || ! $this->hasStoreCartTable()) {
            return false;
        }

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

        return false;
    }
}
