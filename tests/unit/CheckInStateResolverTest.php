<?php

namespace Primexcalibur\Checkin\Tests\unit;

use Carbon\CarbonImmutable;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\unit\TestCase;
use Flarum\User\User;
use Mockery as m;
use Primexcalibur\Checkin\Support\CheckInStateResolver;

class CheckInStateResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function last_check_in_date_returns_null_when_empty()
    {
        $settings = m::mock(SettingsRepositoryInterface::class);
        $resolver = new CheckInStateResolver($settings);

        $user = new User();
        $user->last_checkin_time = null;

        $this->assertNull($resolver->lastCheckInDate($user));
    }

    /**
     * @test
     */
    public function last_check_in_date_returns_date_string_when_set()
    {
        $settings = m::mock(SettingsRepositoryInterface::class);
        $resolver = new CheckInStateResolver($settings);

        $user = new User();
        $user->last_checkin_time = '2026-11-03 14:25:30';

        $this->assertEquals('2026-11-03', $resolver->lastCheckInDate($user));
    }

    /**
     * @test
     */
    public function can_check_in_returns_false_when_today_matches_last_check_in()
    {
        $settings = m::mock(SettingsRepositoryInterface::class);
        $resolver = new CheckInStateResolver($settings);

        $user = new User();
        $user->last_checkin_time = '2026-11-03 08:00:00';

        $forumTime = CarbonImmutable::parse('2026-11-03 16:00:00', 'UTC');

        $this->assertFalse($resolver->canCheckIn($user, $forumTime));
    }

    /**
     * @test
     */
    public function current_forum_time_applies_timezone_offset()
    {
        $settings = m::mock(SettingsRepositoryInterface::class);
        $settings->shouldReceive('get')->with('primexcalibur-checkin-with-history.checkinTimeZone', 0)->andReturn(8);

        $resolver = new CheckInStateResolver($settings);

        $utcNow = CarbonImmutable::now('UTC');
        $forumTime = $resolver->currentForumTime();

        $this->assertEquals($utcNow->addHours(8)->format('Y-m-d H'), $forumTime->format('Y-m-d H'));
    }
}
