<?php

namespace Mattoid\CheckinHistory\Tests\Unit;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\unit\TestCase;
use Mattoid\CheckinHistory\Api\Serializer\CheckinHistorySerializer;
use Mattoid\CheckinHistory\Model\UserCheckinHistory;
use Mockery as m;

class CheckinHistorySerializerTest extends TestCase
{
    /**
     * @test
     */
    public function serializes_regular_checkin_attributes()
    {
        $settings = m::mock(SettingsRepositoryInterface::class);
        $settings->shouldReceive('get')->with('mattoid-forum-checkin.checkin-color')->andReturn('#123456');
        $settings->shouldReceive('get')->with('mattoid-forum-checkin.supplementary-color')->andReturn('#654321');

        $serializer = new CheckinHistorySerializer($settings);

        $history = new UserCheckinHistory();
        $history->setRawAttributes([
            'id' => 1,
            'user_id' => 10,
            'type' => 0,
            'total_checkin_count' => 15,
            'total_continuous_checkin_count' => 3,
            'last_checkin_date' => '2026-08-18',
            'last_checkin_time' => Carbon::parse('2026-08-18 10:00:00'),
        ]);

        $attributes = $serializer->getAttributes($history);

        $this->assertEquals(1, $attributes['id']);
        $this->assertEquals(10, $attributes['userId']);
        $this->assertEquals(0, $attributes['type']);
        $this->assertEquals(15, $attributes['totalCheckinCount']);
        $this->assertEquals(3, $attributes['totalContinuousCheckinCount']);
        $this->assertEquals('2026-08-18', $attributes['start']);
        $this->assertEquals('#123456', $attributes['color']);
    }

    /**
     * @test
     */
    public function serializes_supplementary_checkin_color()
    {
        $settings = m::mock(SettingsRepositoryInterface::class);
        $settings->shouldReceive('get')->with('mattoid-forum-checkin.checkin-color')->andReturn('#123456');
        $settings->shouldReceive('get')->with('mattoid-forum-checkin.supplementary-color')->andReturn('#654321');

        $serializer = new CheckinHistorySerializer($settings);

        $history = new UserCheckinHistory();
        $history->setRawAttributes([
            'id' => 2,
            'user_id' => 10,
            'type' => 1,
            'total_checkin_count' => 16,
            'total_continuous_checkin_count' => 4,
            'last_checkin_date' => '2026-08-17',
            'last_checkin_time' => Carbon::parse('2026-08-18 11:00:00'),
        ]);

        $attributes = $serializer->getAttributes($history);

        $this->assertEquals(1, $attributes['type']);
        $this->assertEquals('#654321', $attributes['color']);
    }
}
