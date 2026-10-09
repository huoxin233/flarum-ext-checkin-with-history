<?php

namespace Mattoid\CheckinHistory\Tests\Unit;

use Flarum\Api\Serializer\UserSerializer;
use Flarum\Testing\unit\TestCase;
use Flarum\User\User;
use Mattoid\CheckinHistory\Api\Serializer\AddUserAttributes;
use Mockery as m;

class AddUserAttributesTest extends TestCase
{
    /**
     * @test
     */
    public function adds_checkin_card_for_self()
    {
        $actor = m::mock(User::class);
        $actor->shouldReceive('isGuest')->andReturn(false);
        $actor->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $user = new User();
        $user->setRawAttributes(['id' => 1, 'checkin_card' => 5]);

        $serializer = m::mock(UserSerializer::class);
        $serializer->shouldReceive('getActor')->andReturn($actor);

        $adder = new AddUserAttributes();
        $attributes = $adder($serializer, $user, []);

        $this->assertArrayHasKey('checkinCard', $attributes);
        $this->assertEquals(5, $attributes['checkinCard']);
    }

    /**
     * @test
     */
    public function adds_checkin_card_for_moderator_viewing_other_user()
    {
        $actor = m::mock(User::class);
        $actor->shouldReceive('isGuest')->andReturn(false);
        $actor->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $actor->shouldReceive('can')->with('checkin.issuanceOfSupplementaryCards')->andReturn(true);

        $user = new User();
        $user->setRawAttributes(['id' => 2, 'checkin_card' => 10]);

        $serializer = m::mock(UserSerializer::class);
        $serializer->shouldReceive('getActor')->andReturn($actor);

        $adder = new AddUserAttributes();
        $attributes = $adder($serializer, $user, []);

        $this->assertArrayHasKey('checkinCard', $attributes);
        $this->assertEquals(10, $attributes['checkinCard']);
    }

    /**
     * @test
     */
    public function omits_checkin_card_for_regular_user_viewing_other_user()
    {
        $actor = m::mock(User::class);
        $actor->shouldReceive('isGuest')->andReturn(false);
        $actor->shouldReceive('getAttribute')->with('id')->andReturn(2);
        $actor->shouldReceive('can')->with('checkin.issuanceOfSupplementaryCards')->andReturn(false);

        $user = new User();
        $user->setRawAttributes(['id' => 1, 'checkin_card' => 10]);

        $serializer = m::mock(UserSerializer::class);
        $serializer->shouldReceive('getActor')->andReturn($actor);

        $adder = new AddUserAttributes();
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

        $adder = new AddUserAttributes();
        $attributes = $adder($serializer, $user, []);

        $this->assertArrayNotHasKey('checkinCard', $attributes);
    }
}
