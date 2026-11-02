<?php

namespace Primexcalibur\Checkin\Tests\unit;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\unit\TestCase;
use Primexcalibur\Checkin\Api\Serializer\CheckinHistorySerializer;
use Primexcalibur\Checkin\Model\CheckinRecord;
use Mockery as m;

class CheckinHistorySerializerTest extends TestCase
{
    /**
     * @test
     */
    public function serializes_regular_checkin_attributes()
    {
        $settings = m::mock(SettingsRepositoryInterface::class);
        $settings->shouldReceive('get')->with('primexcalibur-checkin-with-history.checkinColor')->andReturn('#123456');
        $settings->shouldReceive('get')->with('mattoid-forum-checkin.checkin-color')->andReturn('#123456');
        $settings->shouldReceive('get')->with('primexcalibur-checkin-with-history.supplementaryColor')->andReturn('#654321');
        $settings->shouldReceive('get')->with('mattoid-forum-checkin.supplementary-color')->andReturn('#654321');

        $serializer = new CheckinHistorySerializer($settings);

        $history = new CheckinRecord();
        $history->setRawAttributes([
            'id' => 1,
            'user_id' => 10,
            'type' => 0,
            'streak' => 3,
            'checkin_date' => '2026-08-18',
            'checked_in_at' => Carbon::parse('2026-08-18 10:00:00'),
        ]);

        $attributes = $serializer->getAttributes($history);

        $this->assertEquals(1, $attributes['id']);
        $this->assertEquals(10, $attributes['userId']);
        $this->assertEquals(0, $attributes['type']);
        $this->assertEquals(3, $attributes['streak']);
        $this->assertEquals(3, $attributes['totalContinuousCheckinCount']);
        $this->assertEquals('2026-08-18', $attributes['date']);
        $this->assertEquals('2026-08-18', $attributes['start']);
        $this->assertEquals('#123456', $attributes['color']);
    }

    /**
     * @test
     */
    public function serializes_supplementary_checkin_color()
    {
        $settings = m::mock(SettingsRepositoryInterface::class);
        $settings->shouldReceive('get')->with('primexcalibur-checkin-with-history.checkinColor')->andReturn('#123456');
        $settings->shouldReceive('get')->with('mattoid-forum-checkin.checkin-color')->andReturn('#123456');
        $settings->shouldReceive('get')->with('primexcalibur-checkin-with-history.supplementaryColor')->andReturn('#654321');
        $settings->shouldReceive('get')->with('mattoid-forum-checkin.supplementary-color')->andReturn('#654321');

        $serializer = new CheckinHistorySerializer($settings);

        $history = new CheckinRecord();
        $history->setRawAttributes([
            'id' => 2,
            'user_id' => 10,
            'type' => 1,
            'streak' => 4,
            'checkin_date' => '2026-08-17',
            'checked_in_at' => Carbon::parse('2026-08-18 11:00:00'),
        ]);

        $attributes = $serializer->getAttributes($history);

        $this->assertEquals(1, $attributes['type']);
        $this->assertEquals(4, $attributes['streak']);
        $this->assertEquals('#654321', $attributes['color']);
    }
}
