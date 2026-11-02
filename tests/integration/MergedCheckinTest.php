<?php

namespace Primexcalibur\Checkin\Tests\integration;

use Carbon\CarbonImmutable;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Primexcalibur\Checkin\Event\CheckedIn;
use Primexcalibur\Checkin\Model\CheckinRecord;

class MergedCheckinTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-11 12:00:00', 'UTC'));
        $this->extension('huoxin-money-with-history', 'primexcalibur-checkin-with-history');
        $this->setting('primexcalibur-checkin-with-history.spanDayCheckin', '1');
        $this->setting('primexcalibur-checkin-with-history.rewardMoney', '5');
        $this->setting('primexcalibur-checkin-with-history.checkinRewardMoney', '7');
        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'group_permission' => [
                ['group_id' => 3, 'permission' => 'checkin.allowCheckIn'],
                ['group_id' => 3, 'permission' => 'checkin.allowSupplementaryCheckIn'],
            ],
        ]);
        $schema = $this->database()->getSchemaBuilder();
        if (! $schema->hasTable('store_cart')) {
            $schema->create('store_cart', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->string('title');
                $table->string('code');
                $table->tinyInteger('status')->default(1);
                $table->string('type')->default('permanent');
                $table->dateTime('outtime')->nullable();
                $table->timestamps();
            });
        }
        $this->database()->table('store_cart')->truncate();
        for ($i = 0; $i < 10; $i++) {
            $this->database()->table('store_cart')->insert([
                'user_id' => 2,
                'title' => 'Check-in Card',
                'code' => 'checkin-card',
                'status' => 1,
                'type' => 'permanent',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->app();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function supplement(string $date)
    {
        return $this->send($this->request('POST', '/api/supplement/checkin', ['authenticatedAs' => 2])
            ->withParsedBody(['date' => $date]));
    }

    private function checkin()
    {
        return $this->send($this->request('POST', '/api/checkin', ['authenticatedAs' => 2]));
    }

    private function record(string $date, int $continuous): void
    {
        CheckinRecord::query()->create([
            'user_id' => 2,
            'type' => 0,
            'checkin_date' => $date,
            'checked_in_at' => $date.' 12:00:00',
            'streak' => $continuous,
        ]);
    }

    /** @test */
    public function supplementing_today_prevents_a_second_reward_through_normal_checkin(): void
    {
        $response = $this->supplement('2026-04-11');
        $this->assertSame(201, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertFalse($payload['included'][0]['attributes']['canCheckin']);
        $this->assertSame(422, $this->checkin()->getStatusCode());
        $this->assertSame(1, (int) User::find(2)->total_checkin_count);
        $this->assertEquals(5, User::find(2)->money);
        $this->assertSame(1, CheckinRecord::query()->count());
        $this->assertSame(1, CheckinRecord::first()->type);
        $this->assertSame(1, $this->database()->table('user_money_history')->count());
    }

    /** @test */
    public function normal_checkin_prevents_supplementing_the_same_day(): void
    {
        $this->assertSame(200, $this->checkin()->getStatusCode());
        $this->assertSame(422, $this->supplement('2026-04-11')->getStatusCode());
        $this->assertEquals(7, User::find(2)->money);
        $this->assertSame(1, CheckinRecord::query()->count());
    }

    /** @test */
    public function repairing_an_old_streak_does_not_replace_the_current_streak(): void
    {
        $this->record('2026-04-01', 8);
        $this->record('2026-04-03', 1);
        $this->record('2026-04-10', 1);
        User::whereKey(2)->update(['last_checkin_time' => '2026-04-10 12:00:00', 'total_continuous_checkin_count' => 1]);
        $this->assertSame(201, $this->supplement('2026-04-02')->getStatusCode());
        $this->assertSame(10, CheckinRecord::where('checkin_date', '2026-04-03')->first()->streak);
        $this->assertSame(1, (int) User::find(2)->total_continuous_checkin_count);
        $this->assertSame(200, $this->checkin()->getStatusCode());
        $this->assertSame(2, (int) User::find(2)->total_continuous_checkin_count);
    }

    /** @test */
    public function supplementing_yesterday_extends_the_next_normal_checkin(): void
    {
        User::whereKey(2)->update(['last_checkin_time' => '2026-04-09 12:00:00', 'total_continuous_checkin_count' => 4]);
        $this->assertSame(201, $this->supplement('2026-04-10')->getStatusCode());
        $this->assertSame(200, $this->checkin()->getStatusCode());
        $this->assertSame(6, (int) User::find(2)->total_continuous_checkin_count);
    }

    /** @test */
    public function a_failed_user_save_rolls_back_the_history_and_reward_together(): void
    {
        User::saved(function (User $user) {
            if ($user->id === 2) {
                throw new \RuntimeException('Simulated save failure');
            }
        });
        $this->assertSame(500, $this->checkin()->getStatusCode());
        $this->assertSame(0, (int) User::find(2)->total_checkin_count);
        $this->assertEquals(0, User::find(2)->money);
        $this->assertSame(0, CheckinRecord::query()->count());
        $this->assertSame(0, $this->database()->table('user_money_history')->count());
    }

    /** @test */
    public function failed_supplement_rolls_back_the_card_reward_and_record_without_emitting_an_event(): void
    {
        User::whereKey(2)->update(['money' => 10]);
        $initialUnusedCards = $this->database()->table('store_cart')->where('user_id', 2)->where('status', 1)->count();
        $dispatched = false;
        $this->app()->getContainer()->make(Dispatcher::class)->listen(CheckedIn::class, function () use (&$dispatched) {
            $dispatched = true;
        });
        User::saved(function (User $user) {
            if ($user->id === 2) {
                throw new \RuntimeException('Simulated save failure');
            }
        });

        $this->assertSame(500, $this->supplement('2026-04-10')->getStatusCode());
        $user = User::find(2);
        $this->assertSame($initialUnusedCards, $this->database()->table('store_cart')->where('user_id', 2)->where('status', 1)->count());
        $this->assertEquals(10, $user->money);
        $this->assertSame(0, (int) $user->total_checkin_count);
        $this->assertSame(0, CheckinRecord::query()->count());
        $this->assertSame(0, $this->database()->table('user_money_history')->count());
        $this->assertFalse($dispatched);
    }

    /** @test */
    public function normal_and_supplementary_checkin_publish_the_same_event_with_the_committed_record(): void
    {
        $types = [];
        $this->app()->getContainer()->make(Dispatcher::class)->listen(CheckedIn::class, function (CheckedIn $event) use (&$types) {
            $types[] = $event->record->type;
            $this->assertTrue(CheckinRecord::whereKey($event->record->id)->exists());
            $this->assertEquals($event->user->money, User::find($event->user->id)->money);
        });

        $this->assertSame(200, $this->checkin()->getStatusCode());
        $this->assertSame(201, $this->supplement('2026-04-10')->getStatusCode());
        $this->assertSame([0, 1], $types);
        $this->assertSame(2, (int) User::find(2)->total_continuous_checkin_count);
    }

    /** @test */
    public function forum_day_and_history_use_the_same_timezone_at_midnight(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-11 16:01:00', 'UTC'));
        $this->app()->getContainer()->make(SettingsRepositoryInterface::class)->set('primexcalibur-checkin-with-history.checkinTimeZone', 8);
        $this->assertSame(200, $this->checkin()->getStatusCode());
        $this->assertSame('2026-04-12', CheckinRecord::first()->checkin_date);
        $response = $this->send($this->request('GET', '/api', ['authenticatedAs' => 2]));
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('2026-04-12', $payload['data']['attributes']['checkinToday']);
        $this->assertArrayNotHasKey('actor', $payload['data']['attributes']);
    }
}
