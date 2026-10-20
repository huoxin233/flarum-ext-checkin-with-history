<?php

namespace Primexcalibur\Checkin\Integration\Store;

use Mattoid\Store\Goods\Goods;

class CheckinCardGoods extends Goods
{
    public $name = 'primexcalibur-checkin-with-history.store.card_title';

    public $usable = true;

    public $className = 'store-buy Modal--small';
}
