<?php

namespace Primexcalibur\Checkin\Tests\unit;

use Carbon\Carbon;
use Flarum\Testing\unit\TestCase;
use Flarum\User\User;
use Mockery as m;
use Primexcalibur\Checkin\Access\UserPolicy;

class UserPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function allow_check_in_allows_for_self_with_permission()
    {
        $policy = new UserPolicy();

        $user = new User();
        $user->setRawAttributes(['id' => 1]);

        $actor = m::mock(User::class);
        $actor->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $actor->shouldReceive('hasPermission')->with('checkin.allowCheckIn')->andReturn(true);

        $result = $policy->allowCheckIn($actor, $user);

        $this->assertEquals(UserPolicy::ALLOW, $result);
    }

    /**
     * @test
     */
    public function allow_check_in_denies_for_other_user()
    {
        $policy = new UserPolicy();

        $user = new User();
        $user->setRawAttributes(['id' => 2]);

        $actor = m::mock(User::class);
        $actor->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $result = $policy->allowCheckIn($actor, $user);

        $this->assertEquals(UserPolicy::DENY, $result);
    }

    /**
     * @test
     */
    public function allow_check_in_denies_when_permission_missing()
    {
        $policy = new UserPolicy();

        $user = new User();
        $user->setRawAttributes(['id' => 1]);

        $actor = m::mock(User::class);
        $actor->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $actor->shouldReceive('hasPermission')->with('checkin.allowCheckIn')->andReturn(false);

        $result = $policy->allowCheckIn($actor, $user);

        $this->assertEquals(UserPolicy::DENY, $result);
    }

    /**
     * @test
     */
    public function allow_check_in_denies_when_user_is_suspended()
    {
        $policy = new UserPolicy();

        $user = new User();
        $user->setRawAttributes([
            'id' => 1,
            'suspended_until' => Carbon::now()->addDay(),
        ]);

        $actor = m::mock(User::class);
        $actor->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $actor->shouldReceive('hasPermission')->with('checkin.allowCheckIn')->andReturn(true);

        $result = $policy->allowCheckIn($actor, $user);

        $this->assertEquals(UserPolicy::DENY, $result);
    }

    /**
     * @test
     */
    public function allow_supplementary_check_in_allows_for_self_with_permission()
    {
        $policy = new UserPolicy();

        $user = new User();
        $user->setRawAttributes(['id' => 1]);

        $actor = m::mock(User::class);
        $actor->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $actor->shouldReceive('hasPermission')->with('checkin.allowSupplementaryCheckIn')->andReturn(true);

        $result = $policy->allowSupplementaryCheckIn($actor, $user);

        $this->assertEquals(UserPolicy::ALLOW, $result);
    }

    /**
     * @test
     */
    public function allow_supplementary_check_in_denies_for_other_user()
    {
        $policy = new UserPolicy();

        $user = new User();
        $user->setRawAttributes(['id' => 2]);

        $actor = m::mock(User::class);
        $actor->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $result = $policy->allowSupplementaryCheckIn($actor, $user);

        $this->assertEquals(UserPolicy::DENY, $result);
    }

    /**
     * @test
     */
    public function allow_supplementary_check_in_denies_when_suspended()
    {
        $policy = new UserPolicy();

        $user = new User();
        $user->setRawAttributes([
            'id' => 1,
            'suspended_until' => Carbon::now()->addDay(),
        ]);

        $actor = m::mock(User::class);
        $actor->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $actor->shouldReceive('hasPermission')->with('checkin.allowSupplementaryCheckIn')->andReturn(true);

        $result = $policy->allowSupplementaryCheckIn($actor, $user);

        $this->assertEquals(UserPolicy::DENY, $result);
    }
}
