<?php

use Flarum\Group\Group;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $connection = $schema->getConnection();
        $hadCheckin = $schema->hasColumn('users', 'total_checkin_count');

        foreach (['total_checkin_count', 'total_continuous_checkin_count'] as $column) {
            if (! $schema->hasColumn('users', $column)) {
                $schema->table('users', function (Blueprint $table) use ($column) {
                    $table->integer($column)->default(0);
                });
            }
        }

        if (! $schema->hasColumn('users', 'last_checkin_time')) {
            $schema->table('users', function (Blueprint $table) {
                $table->dateTime('last_checkin_time')->nullable();
            });
        }

        if (! $schema->hasTable('checkin_records')) {
            $schema->create('checkin_records', function (Blueprint $table) use ($connection) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->tinyInteger('type')->default(0);
                $table->date('checkin_date');
                $table->integer('streak')->default(1);
                $table->dateTime('checked_in_at');
                $table->unique(['user_id', 'checkin_date']);
                $prefix = $connection->getTablePrefix();
                $table->foreign('user_id', $prefix.'checkin_records_user_id_foreign')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // Migrate legacy history rows if present
        if ($schema->hasTable('user_checkin_history')) {
            $connection->table('user_checkin_history')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($connection) {
                    foreach ($rows as $row) {
                        $date = $row->last_checkin_date ?? substr($row->last_checkin_time, 0, 10);
                        if (! $connection->table('checkin_records')->where('user_id', $row->user_id)->where('checkin_date', $date)->exists()) {
                            $connection->table('checkin_records')->insert([
                                'user_id' => $row->user_id,
                                'type' => $row->type ?? 0,
                                'checkin_date' => $date,
                                'streak' => $row->total_continuous_checkin_count ?? 1,
                                'checked_in_at' => $row->last_checkin_time ?? $date.' 00:00:00',
                            ]);
                        }
                    }
                });
            $schema->dropIfExists('user_checkin_history');
        }

        // Only the last recorded day is known when upgrading without the history extension.
        $connection->table('users')->whereNotNull('last_checkin_time')->where('total_checkin_count', '>', 0)
            ->select(['id', 'last_checkin_time', 'total_checkin_count', 'total_continuous_checkin_count'])
            ->orderBy('id')->chunkById(500, function ($users) use ($connection) {
                foreach ($users as $user) {
                    $date = substr($user->last_checkin_time, 0, 10);
                    if (! $connection->table('checkin_records')->where('user_id', $user->id)->where('checkin_date', $date)->exists()) {
                        $connection->table('checkin_records')->insert([
                            'user_id' => $user->id,
                            'type' => 0,
                            'checkin_date' => $date,
                            'streak' => $user->total_continuous_checkin_count ?? 1,
                            'checked_in_at' => $user->last_checkin_time,
                        ]);
                    }
                }
            });

        // Migrate legacy checkin_card column to store_cart if present, then drop it
        if ($schema->hasColumn('users', 'checkin_card')) {
            if ($schema->hasTable('store_cart')) {
                $now = date('Y-m-d H:i:s');
                $connection->table('users')->where('checkin_card', '>', 0)
                    ->select(['id', 'checkin_card'])
                    ->orderBy('id')
                    ->chunkById(500, function ($users) use ($connection, $now) {
                        foreach ($users as $user) {
                            $inserts = [];
                            for ($i = 0; $i < (int) $user->checkin_card; $i++) {
                                $inserts[] = [
                                    'user_id' => $user->id,
                                    'title' => 'Check-in Card',
                                    'code' => 'checkin-card',
                                    'status' => 1,
                                    'type' => 'permanent',
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            }
                            if (! empty($inserts)) {
                                $connection->table('store_cart')->insert($inserts);
                            }
                        }
                    });
            }
            $schema->table('users', function (Blueprint $table) {
                $table->dropColumn('checkin_card');
            });
        }

        if (! $hadCheckin) {
            $connection->table('group_permission')->insertOrIgnore([
                ['group_id' => Group::MEMBER_ID, 'permission' => 'checkin.allowCheckIn'],
            ]);
        }

        $defaultSettings = [
            'primexcalibur-checkin-with-history.checkinRewardMoney' => '0',
            'primexcalibur-checkin-with-history.checkinTimeZone' => '0',
            'primexcalibur-checkin-with-history.autoCheckIn' => '0',
            'primexcalibur-checkin-with-history.autoCheckInDelay' => '0',
            'primexcalibur-checkin-with-history.checkinSuccessPromptType' => '0',
            'primexcalibur-checkin-with-history.checkinSuccessPromptText' => '',
            'primexcalibur-checkin-with-history.checkinSuccessPromptRewardText' => '',
            'primexcalibur-checkin-with-history.checkinRange' => '0',
            'primexcalibur-checkin-with-history.maxSupplementaryCheckin' => '0',
            'primexcalibur-checkin-with-history.spanDayCheckin' => '1',
            'primexcalibur-checkin-with-history.rewardMoney' => '0',
            'primexcalibur-checkin-with-history.checkinColor' => '#2756c6',
            'primexcalibur-checkin-with-history.supplementaryColor' => '#ff9900',
        ];

        $legacyKeyMap = [
            'ziven-forum-checkin.checkinRewardMoney' => 'primexcalibur-checkin-with-history.checkinRewardMoney',
            'ziven-forum-checkin.checkinTimeZone' => 'primexcalibur-checkin-with-history.checkinTimeZone',
            'ziven-forum-checkin.autoCheckIn' => 'primexcalibur-checkin-with-history.autoCheckIn',
            'ziven-forum-checkin.autoCheckInDelay' => 'primexcalibur-checkin-with-history.autoCheckInDelay',
            'ziven-forum-checkin.checkinSuccessPromptType' => 'primexcalibur-checkin-with-history.checkinSuccessPromptType',
            'ziven-forum-checkin.checkinSuccessPromptText' => 'primexcalibur-checkin-with-history.checkinSuccessPromptText',
            'ziven-forum-checkin.checkinSuccessPromptRewardText' => 'primexcalibur-checkin-with-history.checkinSuccessPromptRewardText',
            'mattoid-forum-checkin.checkin-range' => 'primexcalibur-checkin-with-history.checkinRange',
            'mattoid-forum-checkin.max-supplementary-checkin' => 'primexcalibur-checkin-with-history.maxSupplementaryCheckin',
            'mattoid-forum-checkin.min-supplementary-date' => 'primexcalibur-checkin-with-history.minSupplementaryDate',
            'mattoid-forum-checkin.span-day-checkin' => 'primexcalibur-checkin-with-history.spanDayCheckin',
            'mattoid-forum-checkin.reward-money' => 'primexcalibur-checkin-with-history.rewardMoney',
            'mattoid-forum-checkin.checkin-color' => 'primexcalibur-checkin-with-history.checkinColor',
            'mattoid-forum-checkin.supplementary-color' => 'primexcalibur-checkin-with-history.supplementaryColor',
            'mattoid-forum-checkin.cdn-fullcalendar-url' => 'primexcalibur-checkin-with-history.cdnFullcalendarUrl',
            'mattoid-forum-checkin.cdn-fullcalendar-sri' => 'primexcalibur-checkin-with-history.cdnFullcalendarSri',
            'mattoid-forum-checkin.cdn-locales-url' => 'primexcalibur-checkin-with-history.cdnLocalesUrl',
            'mattoid-forum-checkin.cdn-locales-sri' => 'primexcalibur-checkin-with-history.cdnLocalesSri',
        ];

        foreach ($legacyKeyMap as $oldKey => $newKey) {
            $existing = $connection->table('settings')->where('key', $oldKey)->value('value');
            if ($existing !== null) {
                $connection->table('settings')->insertOrIgnore(['key' => $newKey, 'value' => $existing]);
                $connection->table('settings')->where('key', $oldKey)->delete();
            }
        }

        $connection->table('settings')->whereIn('key', [
            'mattoid-forum-checkin.consumption',
            'mattoid-forum-checkin.checkin-increase',
            'mattoid-forum-checkin.checkin-card',
            'mattoid-forum-checkin.checkin-position',
        ])->delete();

        foreach ($defaultSettings as $key => $value) {
            $connection->table('settings')->insertOrIgnore(compact('key', 'value'));
        }
    },
    'down' => function (Builder $schema) {},
];
