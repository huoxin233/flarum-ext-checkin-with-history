<?php

namespace Primexcalibur\Checkin\Tests\integration;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Primexcalibur\Checkin\Event\CheckedIn;
use Primexcalibur\Checkin\Model\CheckinRecord;
use Primexcalibur\Checkin\Support\CheckinCardManager;
use Primexcalibur\Checkin\Tests\fixtures\FakeCheckinCardManager;

class PostSupplementCheckinTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private FakeCheckinCardManager $cardManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-suspend');
        $this->extension('huoxin-money-with-history');
        $this->extension('primexcalibur-checkin-with-history');

        $this->setting('primexcalibur-checkin-with-history.spanDayCheckin', '1');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);

        $this->cardManager = new FakeCheckinCardManager(10);
        $this->app()->getContainer()->instance(CheckinCardManager::class, $this->cardManager);
    }

    protected function prepareStoreBackpack(int $userId = 2, int $count = 10): void
    {
        $this->cardManager->setAvailableCards($count);
    }

    protected function setting($key, $value): void
    {
        parent::setting($key, (string) $value);

        if ($this->app) {
            $this->app()->getContainer()->make(SettingsRepositoryInterface::class)->set($key, (string) $value);
        }
    }

    /**
     * @test
     */
    public function allowed_user_can_supplement_checkin()
    {
        $pastDate = date('Y-m-d', strtotime('-2 days'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $body = (string) $response->getBody();
        $this->assertEquals(201, $response->getStatusCode(), $body);
        $data = json_decode($body, true)['data'];

        $this->assertEquals(1, $data['attributes']['type']);
        $this->assertEquals($pastDate, $data['attributes']['date']);

        $record = CheckinRecord::query()->where('user_id', 2)->where('checkin_date', $pastDate)->first();
        $this->assertNotNull($record);
        $this->assertEquals(1, $record->type);
        $this->assertEquals(1, $record->streak);
    }

    /**
     * @test
     */
    public function guest_cannot_supplement_checkin()
    {
        $pastDate = date('Y-m-d', strtotime('-2 days'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin')
                ->withAttribute('bypassCsrfToken', true)
                ->withParsedBody([
                    'date' => $pastDate,
                ])
        );

        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function user_without_permission_cannot_supplement_checkin()
    {
        $this->database()->table('group_permission')
            ->where('permission', 'checkin.allowSupplementaryCheckIn')
            ->delete();

        $pastDate = date('Y-m-d', strtotime('-2 days'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function cannot_supplement_invalid_date_format()
    {
        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => 'invalid-date',
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function cannot_supplement_future_date()
    {
        $futureDate = date('Y-m-d', strtotime('+1 day'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $futureDate,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function cannot_supplement_already_checked_in_date()
    {
        $pastDate = date('Y-m-d', strtotime('-2 days'));

        CheckinRecord::query()->create([
            'user_id' => 2,
            'type' => 0,
            'checkin_date' => $pastDate,
            'streak' => 1,
            'checked_in_at' => $pastDate.' 10:00:00',
        ]);

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function respects_min_supplementary_date_setting()
    {
        $this->setting('primexcalibur-checkin-with-history.minSupplementaryDate', date('Y-m-d', strtotime('-1 day')));

        $blockedDate = date('Y-m-d', strtotime('-2 days'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $blockedDate,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function respects_checkin_range_setting()
    {
        $this->setting('primexcalibur-checkin-with-history.checkinRange', '2');

        $blockedDate = date('Y-m-d', strtotime('-5 days'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $blockedDate,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function blocks_supplement_when_span_day_disabled_and_gap_exists()
    {
        $this->setting('primexcalibur-checkin-with-history.spanDayCheckin', '0');

        $pastDate = date('Y-m-d', strtotime('-3 days'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function allows_supplement_when_span_day_disabled_and_all_subsequent_days_are_signed()
    {
        $this->setting('primexcalibur-checkin-with-history.spanDayCheckin', '0');

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        CheckinRecord::query()->create([
            'user_id' => 2,
            'type' => 0,
            'checkin_date' => $today,
            'streak' => 1,
            'checked_in_at' => $today.' 10:00:00',
        ]);

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $yesterday,
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
    }

    /**
     * @test
     */
    public function blocks_supplement_when_max_consecutive_supplement_exceeded()
    {
        $this->setting('primexcalibur-checkin-with-history.maxSupplementaryCheckin', '2');

        $day1 = date('Y-m-d', strtotime('-3 days'));
        $day2 = date('Y-m-d', strtotime('-2 days'));
        $day3 = date('Y-m-d', strtotime('-1 day'));

        CheckinRecord::query()->create([
            'user_id' => 2,
            'type' => 1,
            'checkin_date' => $day1,
            'streak' => 1,
            'checked_in_at' => $day1.' 10:00:00',
        ]);

        CheckinRecord::query()->create([
            'user_id' => 2,
            'type' => 1,
            'checkin_date' => $day2,
            'streak' => 2,
            'checked_in_at' => $day2.' 10:00:00',
        ]);

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $day3,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function deducts_checkin_card_when_available()
    {
        $this->prepareStoreBackpack(2, 3);

        $pastDate = date('Y-m-d', strtotime('-1 day'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(2, $this->cardManager->getAvailableCards(User::find(2)));
        $this->assertSame(1, $this->cardManager->consumed);
    }

    /**
     * @test
     */
    public function disallows_supplement_when_no_cards_available()
    {
        $this->prepareStoreBackpack(2, 0);

        $pastDate = date('Y-m-d', strtotime('-1 day'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function gives_reward_money_when_supplementing_with_checkin_card()
    {
        $this->prepareStoreBackpack(2, 1);
        User::query()->where('id', 2)->update(['money' => 5.0]);

        $this->setting('primexcalibur-checkin-with-history.rewardMoney', '10');

        $pastDate = date('Y-m-d', strtotime('-1 day'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $user = User::query()->findOrFail(2);
        $this->assertEquals(15.0, (float) $user->money);
    }

    /**
     * @test
     */
    public function rechains_subsequent_continuous_streaks_correctly()
    {
        $day1 = date('Y-m-d', strtotime('-3 days'));
        $day2 = date('Y-m-d', strtotime('-2 days'));
        $day3 = date('Y-m-d', strtotime('-1 day'));

        CheckinRecord::query()->create([
            'user_id' => 2,
            'type' => 0,
            'checkin_date' => $day1,
            'streak' => 1,
            'checked_in_at' => $day1.' 10:00:00',
        ]);

        CheckinRecord::query()->create([
            'user_id' => 2,
            'type' => 0,
            'checkin_date' => $day3,
            'streak' => 1,
            'checked_in_at' => $day3.' 10:00:00',
        ]);

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $day2,
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        $recordDay2 = CheckinRecord::query()->where('user_id', 2)->where('checkin_date', $day2)->first();
        $recordDay3 = CheckinRecord::query()->where('user_id', 2)->where('checkin_date', $day3)->first();

        $this->assertEquals(2, $recordDay2->streak);
        $this->assertEquals(3, $recordDay3->streak);
    }

    /**
     * @test
     */
    public function dispatches_supplementary_checkin_event()
    {
        $dispatched = false;

        $this->app()->getContainer()->make(Dispatcher::class)->listen(CheckedIn::class, function (CheckedIn $event) use (&$dispatched) {
            $dispatched = true;
            $this->assertEquals(1, $event->record->type);
            $this->assertEquals(2, $event->user->id);
        });

        $pastDate = date('Y-m-d', strtotime('-2 days'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertTrue($dispatched);
    }

    /**
     * @test
     */
    public function suspended_user_is_denied_supplement_checkin()
    {
        User::query()->where('id', 2)->update([
            'suspended_until' => Carbon::now()->addDays(2),
        ]);

        $pastDate = date('Y-m-d', strtotime('-1 day'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function supplement_decrements_store_backpack_card()
    {
        $this->prepareStoreBackpack(2, 10);

        $pastDate = date('Y-m-d', strtotime('-1 day'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertEquals(9, $this->cardManager->getAvailableCards(User::find(2)));
    }

    /**
     * @test
     */
    public function supplement_fails_when_no_checkin_cards_available()
    {
        $this->prepareStoreBackpack(2, 0);

        $pastDate = date('Y-m-d', strtotime('-1 day'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }
}
