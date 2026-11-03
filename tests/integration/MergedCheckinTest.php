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
use Primexcalibur\Checkin\Support\CheckinCardManager;
use Primexcalibur\Checkin\Tests\fixtures\FakeCheckinCardManager;

class MergedCheckinTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private FakeCheckinCardManager $cardManager;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-11 12:00:00', 'UTC'));

        $this->extension('flarum-suspend');
        $this->extension('huoxin-money-with-history');
        $this->extension('primexcalibur-checkin-with-history');

        $this->setting('primexcalibur-checkin-with-history.spanDayCheckin', '1');
        $this->setting('primexcalibur-checkin-with-history.rewardMoney', '5');
        $this->setting('primexcalibur-checkin-with-history.checkinRewardMoney', '7');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
            'group_permission' => [
                ['group_id' => 3, 'permission' => 'checkin.allowCheckIn'],
                ['group_id' => 3, 'permission' => 'checkin.allowSupplementaryCheckIn'],
            ],
        ]);

        $this->cardManager = new FakeCheckinCardManager(10);
        $this->app()->getContainer()->instance(CheckinCardManager::class, $this->cardManager);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    protected function setting($key, $value): void
    {
        parent::setting($key, (string) $value);

        if ($this->app) {
            $this->app()->getContainer()->make(SettingsRepositoryInterface::class)->set($key, (string) $value);
        }
    }

    private function supplement(string $date)
    {
        return $this->send(
            $this->request('POST', '/api/supplement/checkin', ['authenticatedAs' => 2])
                ->withParsedBody(['date' => $date])
        );
    }

    private function checkin()
    {
        return $this->send(
            $this->request('POST', '/api/checkin', ['authenticatedAs' => 2])
        );
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
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertFalse($payload['included'][0]['attributes']['canCheckin']);

        $checkinResponse = $this->checkin();
        $this->assertSame(422, $checkinResponse->getStatusCode(), (string) $checkinResponse->getBody());

        $user = User::query()->findOrFail(2);
        $this->assertSame(1, (int) $user->total_checkin_count);
        $this->assertEquals(5, $user->money);
        $this->assertSame(1, CheckinRecord::query()->count());
        $this->assertSame(1, CheckinRecord::first()->type);
        $this->assertSame(1, $this->database()->table('user_money_history')->count());
        $this->assertSame(9, $this->cardManager->available);
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

        $response = $this->supplement('2026-04-02');
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(10, CheckinRecord::where('checkin_date', '2026-04-03')->first()->streak);
        $this->assertSame(1, (int) User::find(2)->total_continuous_checkin_count);

        $this->assertSame(200, $this->checkin()->getStatusCode());
        $this->assertSame(2, (int) User::find(2)->total_continuous_checkin_count);
    }

    /** @test */
    public function supplementing_yesterday_extends_the_next_normal_checkin(): void
    {
        User::whereKey(2)->update(['last_checkin_time' => '2026-04-09 12:00:00', 'total_continuous_checkin_count' => 4]);

        $response = $this->supplement('2026-04-10');
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());

        $this->assertSame(200, $this->checkin()->getStatusCode());
        $this->assertSame(6, (int) User::find(2)->total_continuous_checkin_count);
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
        $response = $this->supplement('2026-04-10');
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame([0, 1], $types);
        $this->assertSame(2, (int) User::find(2)->total_continuous_checkin_count);
    }

    /** @test */
    public function forum_day_and_history_use_the_same_timezone_at_midnight(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-11 16:01:00', 'UTC'));
        $this->app()->getContainer()->make(SettingsRepositoryInterface::class)->set('primexcalibur-checkin-with-history.checkinTimeZone', 8);

        $this->assertSame(200, $this->checkin()->getStatusCode());
        $this->assertSame('2026-04-12', CheckinRecord::where('user_id', 2)->latest('id')->first()->checkin_date);

        $response = $this->send($this->request('GET', '/api', ['authenticatedAs' => 2]));
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('2026-04-12', $payload['data']['attributes']['checkinToday']);
        $this->assertArrayNotHasKey('actor', $payload['data']['attributes']);
    }
}
