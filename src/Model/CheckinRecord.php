<?php

namespace Primexcalibur\Checkin\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $type 0: normal check-in, 1: supplementary check-in
 * @property string $checkin_date
 * @property int $streak
 * @property Carbon $checked_in_at
 * @property-read User|null $user
 */
class CheckinRecord extends AbstractModel
{
    protected $table = 'checkin_records';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $dates = [
        'checked_in_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'type' => 'integer',
        'streak' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
