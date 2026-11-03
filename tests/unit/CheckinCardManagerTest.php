<?php

namespace Primexcalibur\Checkin\Tests\unit;

use Flarum\Extension\ExtensionManager;
use Flarum\Testing\unit\TestCase;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Mockery as m;
use Primexcalibur\Checkin\Support\CheckinCardManager;

class CheckinCardManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    /** @test */
    public function is_store_enabled_returns_true_when_extension_enabled(): void
    {
        $extensions = m::mock(ExtensionManager::class);
        $extensions->shouldReceive('isEnabled')->with('mattoid-store')->andReturn(true);

        $connection = m::mock(ConnectionInterface::class);

        $manager = new CheckinCardManager($extensions, $connection);

        $this->assertTrue($manager->isStoreEnabled());
    }

    /** @test */
    public function is_store_enabled_returns_true_when_table_exists(): void
    {
        $extensions = m::mock(ExtensionManager::class);
        $extensions->shouldReceive('isEnabled')->with('mattoid-store')->andReturn(false);

        $schema = m::mock(SchemaBuilder::class);
        $schema->shouldReceive('hasTable')->with('store_cart')->andReturn(true);

        $connection = m::mock(ConnectionInterface::class);
        $connection->shouldReceive('getSchemaBuilder')->andReturn($schema);

        $manager = new CheckinCardManager($extensions, $connection);

        $this->assertTrue($manager->isStoreEnabled());
    }

    /** @test */
    public function is_store_enabled_returns_false_when_neither_extension_nor_table_exists(): void
    {
        $extensions = m::mock(ExtensionManager::class);
        $extensions->shouldReceive('isEnabled')->with('mattoid-store')->andReturn(false);

        $schema = m::mock(SchemaBuilder::class);
        $schema->shouldReceive('hasTable')->with('store_cart')->andReturn(false);

        $connection = m::mock(ConnectionInterface::class);
        $connection->shouldReceive('getSchemaBuilder')->andReturn($schema);

        $manager = new CheckinCardManager($extensions, $connection);

        $this->assertFalse($manager->isStoreEnabled());
    }

    /** @test */
    public function get_available_cards_returns_zero_when_store_disabled(): void
    {
        $extensions = m::mock(ExtensionManager::class);
        $extensions->shouldReceive('isEnabled')->with('mattoid-store')->andReturn(false);

        $schema = m::mock(SchemaBuilder::class);
        $schema->shouldReceive('hasTable')->with('store_cart')->andReturn(false);

        $connection = m::mock(ConnectionInterface::class);
        $connection->shouldReceive('getSchemaBuilder')->andReturn($schema);

        $manager = new CheckinCardManager($extensions, $connection);

        $user = new User();
        $user->setRawAttributes(['id' => 2]);

        $this->assertSame(0, $manager->getAvailableCards($user));
    }

    /** @test */
    public function consume_card_returns_false_when_store_disabled(): void
    {
        $extensions = m::mock(ExtensionManager::class);
        $extensions->shouldReceive('isEnabled')->with('mattoid-store')->andReturn(false);

        $schema = m::mock(SchemaBuilder::class);
        $schema->shouldReceive('hasTable')->with('store_cart')->andReturn(false);

        $connection = m::mock(ConnectionInterface::class);
        $connection->shouldReceive('getSchemaBuilder')->andReturn($schema);

        $manager = new CheckinCardManager($extensions, $connection);

        $user = new User();
        $user->setRawAttributes(['id' => 2]);

        $this->assertFalse($manager->consumeCard($user));
    }
}
