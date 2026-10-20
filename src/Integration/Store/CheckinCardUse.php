<?php

namespace Primexcalibur\Checkin\Integration\Store;

use Flarum\Foundation\ValidationException;
use Flarum\Locale\Translator;
use Flarum\User\User;
use Mattoid\Store\Model\StoreCartModel;
use Mattoid\Store\Model\StoreModel;

class CheckinCardUse
{
    public static function use(User $actor, StoreModel $store, StoreCartModel $cart): bool
    {
        /** @var Translator $translator */
        $translator = resolve(Translator::class);

        throw new ValidationException([
            'message' => $translator->trans('primexcalibur-checkin-with-history.store.use_instruction'),
        ]);
    }
}
