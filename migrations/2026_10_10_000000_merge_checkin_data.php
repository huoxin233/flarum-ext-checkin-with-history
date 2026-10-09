<?php

use Flarum\Group\Group;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $connection = $schema->getConnection();
        $hadCheckin = $schema->hasColumn('users', 'total_checkin_count');

        foreach (['total_checkin_count', 'total_continuous_checkin_count', 'checkin_card'] as $column) {
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

        if (! $schema->hasTable('user_checkin_history')) {
            $schema->create('user_checkin_history', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->tinyInteger('type')->default(0);
                $table->date('last_checkin_date');
                $table->integer('total_checkin_count');
                $table->integer('total_continuous_checkin_count');
                $table->dateTime('last_checkin_time');
                $table->unique(['user_id', 'last_checkin_date']);
            });
        }

        // Only the last recorded day is known when upgrading without the history extension.
        $connection->table('users')->whereNotNull('last_checkin_time')->where('total_checkin_count', '>', 0)
            ->select(['id', 'last_checkin_time', 'total_checkin_count', 'total_continuous_checkin_count'])
            ->orderBy('id')->chunkById(500, function ($users) use ($connection) {
                foreach ($users as $user) {
                    $date = substr($user->last_checkin_time, 0, 10);
                    if (! $connection->table('user_checkin_history')->where('user_id', $user->id)->where('last_checkin_date', $date)->exists()) {
                        $connection->table('user_checkin_history')->insert([
                            'user_id' => $user->id,
                            'type' => 0,
                            'last_checkin_date' => $date,
                            'last_checkin_time' => $user->last_checkin_time,
                            'total_checkin_count' => $user->total_checkin_count,
                            'total_continuous_checkin_count' => $user->total_continuous_checkin_count,
                        ]);
                    }
                }
            });

        if (! $hadCheckin) {
            $connection->table('group_permission')->insertOrIgnore([
                'group_id' => Group::MEMBER_ID,
                'permission' => 'checkin.allowCheckIn',
            ]);
        }

        foreach ([
            'ziven-forum-checkin.checkinSuccessPromptType' => '0',
            'ziven-forum-checkin.checkinSuccessPromptText' => '',
            'ziven-forum-checkin.checkinSuccessPromptRewardText' => '',
            'ziven-forum-checkin.autoCheckIn' => '0',
            'ziven-forum-checkin.autoCheckInDelay' => '0',
        ] as $key => $value) {
            $connection->table('settings')->insertOrIgnore(compact('key', 'value'));
        }
    },
    // These tables and columns also belong to the legacy extensions. Purging must preserve them.
    'down' => function (Builder $schema) {},
];
