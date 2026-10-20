<?php

namespace Primexcalibur\Checkin\Tests\unit;

use Flarum\Api\Serializer\UserSerializer;
use Flarum\Testing\unit\TestCase;
use Flarum\User\User;
use Mockery as m;
use Primexcalibur\Checkin\Api\Serializer\AddUserAttributes;
use Primexcalibur\Checkin\Support\CheckinCardManager;
use Primexcalibur\Checkin\Support\CheckInStateResolver;

class AddUserAttributesTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
    /**
     * @test
     */
    public function adds_checkin_card_for_self()
    {
        $user = new User();
        $user->setRawAttributes(['id' => 1, 'checkin_card' => 5]);

        $actor = m::mock(User::class);
        $actor->shouldReceive('isGuest')->andReturn(false);
        $actor->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $actor->shouldReceive('can')->with('checkin.allowCheckIn', $user)->andReturn(true);

        $serializer = m::mock(UserSerializer::class);
        $serializer->shouldReceive('getActor')->andReturn($actor);

        $resolver = m::mock(CheckInStateResolver::class);
        $resolver->shouldReceive('canCheckIn')->andReturn(true);

        $cardManager = m::mock(CheckinCardManager::class);
        $cardManager->shouldReceive('getAvailableCards')->with($user)->andReturn(5);

        $adder = new AddUserAttributes($resolver, $cardManager);
        $attributes = $adder($serializer, $user, []);

        $this->assertArrayHasKey('checkinCard', $attributes);
        $this->assertEquals(5, $attributes['checkinCard']);
        $this->assertTrue($attributes['canCheckin']);
    }

    /**
     * @test
     */
    public function omits_checkin_card_when_viewing_other_user()
    {
        $actor = m::mock(User::class);
        $actor->shouldReceive('isGuest')->andReturn(false);
        $actor->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $user = new User();
        $user->setRawAttributes(['id' => 2, 'checkin_card' => 10]);

        $serializer = m::mock(UserSerializer::class);
        $serializer->shouldReceive('getActor')->andReturn($actor);

        $resolver = m::mock(CheckInStateResolver::class);
        $cardManager = m::mock(CheckinCardManager::class);

        $adder = new AddUserAttributes($resolver, $cardManager);
        $attributes = $adder($serializer, $user, []);

        $this->assertArrayNotHasKey('checkinCard', $attributes);
    }

    /**
     * @test
     */
    public function omits_checkin_card_for_guest()
    {
        $actor = m::mock(User::class);
        $actor->shouldReceive('isGuest')->andReturn(true);

        $user = new User();
        $user->setRawAttributes(['id' => 1, 'checkin_card' => 10]);

        $serializer = m::mock(UserSerializer::class);
        $serializer->shouldReceive('getActor')->andReturn($actor);

        $resolver = m::mock(CheckInStateResolver::class);
        $cardManager = m::mock(CheckinCardManager::class);

        $adder = new AddUserAttributes($resolver, $cardManager);
        $attributes = $adder($serializer, $user, []);

        $this->assertArrayNotHasKey('checkinCard', $attributes);
    }

    /**
     * @test
     */
    public function disallows_can_checkin_when_actor_permission_denied()
    {
        $user = new User();
        $user->setRawAttributes(['id' => 1]);

        $actor = m::mock(User::class);
        $actor->shouldReceive('isGuest')->andReturn(false);
        $actor->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $actor->shouldReceive('can')->with('checkin.allowCheckIn', $user)->andReturn(false);

        $serializer = m::mock(UserSerializer::class);
        $serializer->shouldReceive('getActor')->andReturn($actor);

        $resolver = m::mock(CheckInStateResolver::class);
        $cardManager = m::mock(CheckinCardManager::class);
        $cardManager->shouldReceive('getAvailableCards')->with($user)->andReturn(0);

        $adder = new AddUserAttributes($resolver, $cardManager);
        $attributes = $adder($serializer, $user, []);

        $this->assertFalse($attributes['canCheckin']);
    }
}
