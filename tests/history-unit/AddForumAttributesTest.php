<?php

namespace Mattoid\CheckinHistory\Tests\Unit;

use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\unit\TestCase;
use Flarum\User\User;
use Mattoid\CheckinHistory\Api\Serializer\AddForumAttributes;
use Mockery as m;

class AddForumAttributesTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function adds_can_query_others_history_and_cdn_resources_when_allowed()
    {
        $actor = m::mock(User::class);
        $actor->shouldReceive('can')->with('checkin.queryOthersHistory')->andReturn(true);

        $serializer = m::mock(ForumSerializer::class);
        $serializer->shouldReceive('getActor')->andReturn($actor);

        $settings = m::mock(SettingsRepositoryInterface::class);
        $settings->shouldReceive('get')->with('mattoid-forum-checkin.cdn-fullcalendar-url')->andReturn('https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js');
        $settings->shouldReceive('get')->with('mattoid-forum-checkin.cdn-fullcalendar-sri')->andReturn('sha384-abc');
        $settings->shouldReceive('get')->with(m::pattern('/^mattoid-forum-checkin\.cdn-/'))->andReturn(null);

        $settings->shouldReceive('get')->with('ziven-forum-checkin.checkinTimeZone', 0)->andReturn(0);
        $adder = new AddForumAttributes($settings, new \Ziven\DailyCheckin\Support\CheckInStateResolver($settings));
        $attributes = $adder($serializer, []);

        $this->assertArrayHasKey('canQueryOthersHistory', $attributes);
        $this->assertTrue($attributes['canQueryOthersHistory']);
        $this->assertArrayHasKey('checkinCdnResources', $attributes);
        $this->assertEquals('https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', $attributes['checkinCdnResources']['fullcalendar']['url']);
        $this->assertEquals('sha384-abc', $attributes['checkinCdnResources']['fullcalendar']['sri']);
    }

    /**
     * @test
     */
    public function adds_can_query_others_history_and_cdn_resources_when_disallowed()
    {
        $actor = m::mock(User::class);
        $actor->shouldReceive('can')->with('checkin.queryOthersHistory')->andReturn(false);

        $serializer = m::mock(ForumSerializer::class);
        $serializer->shouldReceive('getActor')->andReturn($actor);

        $settings = m::mock(SettingsRepositoryInterface::class);
        $settings->shouldReceive('get')->with(m::pattern('/^mattoid-forum-checkin\.cdn-/'))->andReturn(null);

        $settings->shouldReceive('get')->with('ziven-forum-checkin.checkinTimeZone', 0)->andReturn(0);
        $adder = new AddForumAttributes($settings, new \Ziven\DailyCheckin\Support\CheckInStateResolver($settings));
        $attributes = $adder($serializer, []);

        $this->assertArrayHasKey('canQueryOthersHistory', $attributes);
        $this->assertFalse($attributes['canQueryOthersHistory']);
        $this->assertArrayHasKey('checkinCdnResources', $attributes);
        $this->assertEquals('', $attributes['checkinCdnResources']['fullcalendar']['url']);
        $this->assertEquals('', $attributes['checkinCdnResources']['fullcalendar']['sri']);
    }
}
