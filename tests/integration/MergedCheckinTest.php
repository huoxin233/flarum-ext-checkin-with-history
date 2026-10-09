<?php

namespace Ziven\DailyCheckin\Tests\integration;

use Carbon\CarbonImmutable;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Mattoid\CheckinHistory\Model\UserCheckinHistory;

class MergedCheckinTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-11 12:00:00', 'UTC'));
        $this->extension('huoxin-money-with-history', 'primexcalibur-checkin-with-history');
        $this->setting('mattoid-forum-checkin.span-day-checkin', '1');
        $this->setting('mattoid-forum-checkin.reward-money', '5');
        $this->setting('ziven-forum-checkin.checkinRewardMoney', '7');
        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'group_permission' => [
                ['group_id' => 3, 'permission' => 'checkin.allowCheckIn'],
                ['group_id' => 3, 'permission' => 'checkin.allowSupplementaryCheckIn'],
            ],
        ]);
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
        UserCheckinHistory::query()->create([
            'user_id' => 2, 'type' => 0, 'last_checkin_date' => $date,
            'last_checkin_time' => $date.' 12:00:00', 'total_checkin_count' => $continuous,
            'total_continuous_checkin_count' => $continuous,
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
        $this->assertSame(1, UserCheckinHistory::query()->count());
        $this->assertSame(1, UserCheckinHistory::first()->type);
        $this->assertSame(1, $this->database()->table('user_money_history')->count());
    }

    /** @test */
    public function normal_checkin_prevents_supplementing_the_same_day(): void
    {
        $this->assertSame(200, $this->checkin()->getStatusCode());
        $this->assertSame(422, $this->supplement('2026-04-11')->getStatusCode());
        $this->assertEquals(7, User::find(2)->money);
        $this->assertSame(1, UserCheckinHistory::query()->count());
    }

    /** @test */
    public function repairing_an_old_streak_does_not_replace_the_current_streak(): void
    {
        $this->record('2026-04-01', 8);
        $this->record('2026-04-03', 1);
        $this->record('2026-04-10', 1);
        User::whereKey(2)->update(['last_checkin_time' => '2026-04-10 12:00:00', 'total_continuous_checkin_count' => 1]);
        $this->assertSame(201, $this->supplement('2026-04-02')->getStatusCode());
        $this->assertSame(10, UserCheckinHistory::where('last_checkin_date', '2026-04-03')->first()->total_continuous_checkin_count);
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
        $this->assertSame(0, UserCheckinHistory::query()->count());
        $this->assertSame(0, $this->database()->table('user_money_history')->count());
    }

    /** @test */
    public function forum_day_and_history_use_the_same_timezone_at_midnight(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-11 16:01:00', 'UTC'));
        $this->app()->getContainer()->make(SettingsRepositoryInterface::class)->set('ziven-forum-checkin.checkinTimeZone', 8);
        $this->assertSame(200, $this->checkin()->getStatusCode());
        $this->assertSame('2026-04-12', UserCheckinHistory::first()->last_checkin_date);
        $response = $this->send($this->request('GET', '/api', ['authenticatedAs' => 2]));
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('2026-04-12', $payload['data']['attributes']['checkinToday']);
        $this->assertArrayNotHasKey('actor', $payload['data']['attributes']);
    }
}
