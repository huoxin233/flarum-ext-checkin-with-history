<?php

namespace Mattoid\CheckinHistory\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $type 0: normal check-in, 1: supplementary check-in
 * @property string $last_checkin_date
 * @property int $total_checkin_count
 * @property int $total_continuous_checkin_count
 * @property Carbon $last_checkin_time
 * @property-read User|null $user
 */
class UserCheckinHistory extends AbstractModel
{
    protected $table = 'user_checkin_history';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $dates = [
        'last_checkin_time',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'type' => 'integer',
        'total_checkin_count' => 'integer',
        'total_continuous_checkin_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
