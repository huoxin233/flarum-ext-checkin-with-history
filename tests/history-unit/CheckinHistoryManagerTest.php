<?php

namespace Mattoid\CheckinHistory\Tests\Unit;

use DateTime;
use DateTimeZone;
use Flarum\Extension\ExtensionManager;
use Flarum\Locale\Translator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\unit\TestCase;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Mattoid\CheckinHistory\CheckinHistoryManager;
use Mockery as m;

class CheckinHistoryManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function calculates_today_date_based_on_configured_timezone()
    {
        $settings = m::mock(SettingsRepositoryInterface::class);
        $settings->shouldReceive('get')
            ->with('ziven-forum-checkin.checkinTimeZone', 0)
            ->andReturn(8);

        $manager = new CheckinHistoryManager(
            $settings,
            m::mock(Translator::class),
            m::mock(Dispatcher::class),
            m::mock(ConnectionInterface::class),
            m::mock(\Huoxin\MoneyWithHistory\Service\BalanceManager::class),
            new \Ziven\DailyCheckin\Support\CheckInStateResolver($settings)
        );

        $expectedDate = (new DateTime('now', new DateTimeZone('Etc/GMT-8')))->format('Y-m-d');
        $this->assertEquals($expectedDate, $manager->getTodayDate());
    }

    /**
     * @test
     */
    public function calculates_today_date_with_utc_default_when_no_setting()
    {
        $settings = m::mock(SettingsRepositoryInterface::class);
        $settings->shouldReceive('get')
            ->with('ziven-forum-checkin.checkinTimeZone', 0)
            ->andReturn(0);

        $manager = new CheckinHistoryManager(
            $settings,
            m::mock(Translator::class),
            m::mock(Dispatcher::class),
            m::mock(ConnectionInterface::class),
            m::mock(\Huoxin\MoneyWithHistory\Service\BalanceManager::class),
            new \Ziven\DailyCheckin\Support\CheckInStateResolver($settings)
        );

        $expectedDate = gmdate('Y-m-d');
        $this->assertEquals($expectedDate, $manager->getTodayDate());
    }
}
