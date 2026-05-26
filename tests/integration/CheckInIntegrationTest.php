<?php

namespace Ziven\checkin\Tests\integration;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

class CheckInIntegrationTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-11 12:00:00', 'UTC'));

        $this->extension('ziiven-daily-check-in');
        $this->extension('antoinefr-money');
        $this->extension('mattoid-money-history');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
            'group_permission' => [
                ['group_id' => 3, 'permission' => 'checkin.allowCheckIn'],
            ],
        ]);

        $this->app();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /** @test */
    public function it_allows_a_first_time_check_in_via_the_api(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/checkin', ['authenticatedAs' => 2])
        );

        $payload = json_decode((string) $response->getBody(), true);
        $user = User::query()->findOrFail(2);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, (int) $user->total_checkin_count);
        $this->assertSame(1, (int) $user->total_continuous_checkin_count);
        $this->assertNotNull($user->last_checkin_time);
        $this->assertFalse($payload['data']['attributes']['canCheckin']);
        $this->assertSame(1, $payload['data']['attributes']['totalContinuousCheckIn']);
    }

    /** @test */
    public function it_rejects_a_second_check_in_on_the_same_forum_day(): void
    {
        $this->send($this->request('POST', '/api/checkin', ['authenticatedAs' => 2]));

        $secondResponse = $this->send(
            $this->request('POST', '/api/checkin', ['authenticatedAs' => 2])
        );

        $user = User::query()->findOrFail(2);

        $this->assertSame(422, $secondResponse->getStatusCode());
        $this->assertSame(1, (int) $user->total_checkin_count);
        $this->assertSame(1, (int) $user->total_continuous_checkin_count);
    }

    /** @test */
    public function it_increments_the_streak_when_the_previous_check_in_was_yesterday(): void
    {
        $user = User::query()->findOrFail(2);
        $user->last_checkin_time = CarbonImmutable::now('UTC')->subDay()->toDateTimeString();
        $user->total_continuous_checkin_count = 4;
        $user->save();

        $response = $this->send(
            $this->request('POST', '/api/checkin', ['authenticatedAs' => 2])
        );

        $user->refresh();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(5, (int) $user->total_continuous_checkin_count);
    }

    /** @test */
    public function it_resets_the_streak_after_a_gap_longer_than_one_day(): void
    {
        $user = User::query()->findOrFail(2);
        $user->last_checkin_time = CarbonImmutable::now('UTC')->subDays(3)->toDateTimeString();
        $user->total_continuous_checkin_count = 4;
        $user->save();

        $response = $this->send(
            $this->request('POST', '/api/checkin', ['authenticatedAs' => 2])
        );

        $user->refresh();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, (int) $user->total_continuous_checkin_count);
    }

    /** @test */
    public function it_records_reward_history_when_money_extensions_are_enabled(): void
    {
        $this->settings()->set('ziven-forum-checkin.checkinRewardMoney', '5.5');

        $response = $this->send(
            $this->request('POST', '/api/checkin', ['authenticatedAs' => 2])
        );

        $user = User::query()->findOrFail(2);
        $historyEntry = $this->connection()->table('user_money_history')->where('user_id', $user->id)->first();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals(5.5, (float) $user->money);
        $this->assertNotNull($historyEntry);
        $this->assertSame('DAILY_CHECKIN_REWARD', $historyEntry->source);
        $this->assertSame('ziven-checkin.forum.history.checkin-reward', $historyEntry->source_key);
        $this->assertEquals(0.0, (float) $historyEntry->balance_before);
        $this->assertEquals(5.5, (float) $historyEntry->balance_after);
    }

    /** @test */
    public function it_skips_money_history_when_the_reward_amount_is_zero(): void
    {
        $this->settings()->set('ziven-forum-checkin.checkinRewardMoney', '0');

        $response = $this->send(
            $this->request('POST', '/api/checkin', ['authenticatedAs' => 2])
        );

        $user = User::query()->findOrFail(2);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals(0.0, (float) $user->money);
        $this->assertSame(0, $this->connection()->table('user_money_history')->count());
    }

    /** @test */
    public function guest_cannot_check_in(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/checkin')
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    /** @test */
    public function user_without_permission_is_denied(): void
    {
        // Remove the check-in permission for the members group
        $this->database()->table('group_permission')
            ->where('permission', 'checkin.allowCheckIn')
            ->delete();

        $response = $this->send(
            $this->request('POST', '/api/checkin', ['authenticatedAs' => 2])
        );

        $this->assertSame(403, $response->getStatusCode());

        $user = User::query()->findOrFail(2);
        $this->assertSame(0, (int) $user->total_checkin_count);
    }

    /** @test */
    public function suspended_user_is_denied_check_in(): void
    {
        // The extension's UserPolicy checks suspended_until directly,
        // so we set it on the user without needing flarum-suspend enabled.
        $this->database()->table('users')
            ->where('id', 2)
            ->update(['suspended_until' => Carbon::now()->addDay()]);

        $response = $this->send(
            $this->request('POST', '/api/checkin', ['authenticatedAs' => 2])
        );

        $this->assertSame(403, $response->getStatusCode());

        $user = User::query()->findOrFail(2);
        $this->assertSame(0, (int) $user->total_checkin_count);
    }

    /** @test */
    public function it_respects_timezone_offset_at_day_boundary(): void
    {
        // Forum time is UTC+8, so UTC 16:00 = forum 00:00 (next day).
        // Set "now" to UTC 2026-04-11 15:59:00 → forum time = 2026-04-11 23:59:00
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-11 15:59:00', 'UTC'));
        $this->settings()->set('ziven-forum-checkin.checkinTimeZone', '8');

        // First check-in at forum 23:59 on April 11
        $response1 = $this->send(
            $this->request('POST', '/api/checkin', ['authenticatedAs' => 2])
        );

        $this->assertSame(200, $response1->getStatusCode());

        // Advance 2 minutes → UTC 16:01 → forum time = 2026-04-12 00:01:00 (next forum day)
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-11 16:01:00', 'UTC'));

        $response2 = $this->send(
            $this->request('POST', '/api/checkin', ['authenticatedAs' => 2])
        );

        // Should succeed because it's a new forum day
        $this->assertSame(200, $response2->getStatusCode());

        $user = User::query()->findOrFail(2);
        $this->assertSame(2, (int) $user->total_checkin_count);
        // Should be continuous since forum yesterday → forum today
        $this->assertSame(2, (int) $user->total_continuous_checkin_count);
    }

    private function connection(): ConnectionInterface
    {
        return $this->app()->getContainer()->make(ConnectionInterface::class);
    }

    private function settings(): SettingsRepositoryInterface
    {
        return $this->app()->getContainer()->make(SettingsRepositoryInterface::class);
    }
}

