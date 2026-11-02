<?php

namespace Primexcalibur\Checkin\Tests\integration;

use Flarum\Testing\integration\TestCase;
use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;

class UpgradeMigrationTest extends TestCase
{
    private ?MySqlConnection $upgradeConnection = null;

    protected function tearDown(): void
    {
        if ($this->upgradeConnection) {
            $schema = $this->upgradeConnection->getSchemaBuilder();
            foreach (['checkin_records', 'user_checkin_history', 'users', 'settings', 'group_permission', 'store_cart'] as $table) {
                $schema->dropIfExists($table);
            }
        }
        parent::tearDown();
    }

    public function installationStates(): array
    {
        return ['fresh' => [false, false], 'checkin only' => [true, false], 'both legacy extensions' => [true, true]];
    }

    /** @test @dataProvider installationStates */
    public function migration_preserves_existing_data_and_can_be_repeated(bool $checkin, bool $history): void
    {
        $this->app();
        $config = $this->database()->getConfig();
        // A separate connection isolates MySQL's implicit DDL commits from Flarum's test transaction.
        $this->upgradeConnection = new MySqlConnection((new MySqlConnector())->connect($config), $config['database'], 'checkin_upgrade_', $config);
        $db = $this->upgradeConnection;
        $schema = $db->getSchemaBuilder();
        $schema->create('users', function (Blueprint $table) use ($checkin, $history) {
            $table->increments('id');
            if ($checkin) {
                $table->integer('total_checkin_count')->default(0);
                $table->integer('total_continuous_checkin_count')->default(0);
                $table->dateTime('last_checkin_time')->nullable();
            }
            if ($history) {
                $table->integer('checkin_card')->default(0);
            }
        });
        $schema->create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
        });
        $schema->create('group_permission', function (Blueprint $table) {
            $table->unsignedInteger('group_id');
            $table->string('permission');
            $table->primary(['group_id', 'permission']);
        });
        $values = ['id' => 2];
        if ($checkin) {
            $values += ['last_checkin_time' => '2026-04-10 20:00:00', 'total_checkin_count' => 40, 'total_continuous_checkin_count' => 8];
        }
        if ($history) {
            $schema->create('store_cart', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->string('title');
                $table->string('code');
                $table->tinyInteger('status')->default(1);
                $table->string('type')->default('permanent');
                $table->dateTime('outtime')->nullable();
                $table->timestamps();
            });
            $values['checkin_card'] = 3;
            $schema->create('user_checkin_history', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('user_id')->index();
                $table->tinyInteger('type')->default(0);
                $table->date('last_checkin_date');
                $table->dateTime('last_checkin_time');
                $table->integer('total_checkin_count');
                $table->integer('total_continuous_checkin_count');
            });
            $db->table('user_checkin_history')->insert([
                'id' => 99, 'user_id' => 2, 'type' => 1, 'last_checkin_date' => '2026-04-10',
                'last_checkin_time' => '2026-04-11 01:00:00', 'total_checkin_count' => 40, 'total_continuous_checkin_count' => 8,
            ]);
        }
        $db->table('users')->insert($values);
        $db->table('settings')->insert(['key' => 'ziven-forum-checkin.checkinSuccessPromptType', 'value' => '2']);
        $migration = require __DIR__.'/../../migrations/2026_10_10_000000_merge_checkin_data.php';
        $migration['up']($schema);
        $migration['up']($schema);
        $migration['down']($schema);

        $user = $db->table('users')->first();
        $this->assertSame($checkin ? 40 : 0, (int) $user->total_checkin_count);
        $this->assertSame($checkin ? 8 : 0, (int) $user->total_continuous_checkin_count);
        $this->assertFalse($schema->hasColumn('users', 'checkin_card'));
        $this->assertFalse($schema->hasTable('user_checkin_history'));
        $this->assertTrue($schema->hasTable('checkin_records'));
        $this->assertSame($checkin ? 1 : 0, $db->table('checkin_records')->count());
        $this->assertSame('2', $db->table('settings')->where('key', 'primexcalibur-checkin-with-history.checkinSuccessPromptType')->value('value'));
        $this->assertNull($db->table('settings')->where('key', 'ziven-forum-checkin.checkinSuccessPromptType')->value('value'));
        $this->assertSame($checkin ? 0 : 4, $db->table('group_permission')->count());
        if ($history) {
            $record = $db->table('checkin_records')->first();
            $this->assertSame(1, (int) $record->type);
            $this->assertSame('2026-04-10', $record->checkin_date);
            $this->assertSame(8, (int) $record->streak);
            $this->assertSame(3, $db->table('store_cart')->where('user_id', 2)->where('code', 'checkin-card')->count());
        }
    }
}
