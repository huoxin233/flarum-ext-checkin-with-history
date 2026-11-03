<?php

namespace Primexcalibur\Checkin\Tests\integration;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Primexcalibur\Checkin\Event\CheckedIn;
use Primexcalibur\Checkin\Model\CheckinRecord;

class PostSupplementCheckinTest extends TestCase
{
    use RetrievesAuthorizedUsers;

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
        ]);
        $this->prepareStoreBackpack(2, 10);
    }

    protected function tearDown(): void
    {
        CheckinRecord::query()->where('user_id', 2)->delete();
        parent::tearDown();
    }

    protected function setting(string $key, $value): void
    {
        parent::setting($key, (string) $value);

        if ($this->app) {
            $this->app()->getContainer()->make(\Flarum\Settings\SettingsRepositoryInterface::class)->set($key, (string) $value);
        }
    }

    protected function prepareStoreBackpack(int $userId = 2, int $count = 10): void
    {
        $schema = $this->database()->getSchemaBuilder();
        if (! $schema->hasTable('store_cart')) {
            $schema->create('store_cart', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->string('title');
                $table->string('code');
                $table->tinyInteger('status')->default(1);
                $table->string('type')->default('permanent');
                $table->dateTime('outtime')->nullable();
                $table->timestamps();
            });
        }
        $this->database()->table('store_cart')->where('user_id', $userId)->delete();
        for ($i = 0; $i < $count; $i++) {
            $this->database()->table('store_cart')->insert([
                'user_id' => $userId,
                'title' => 'Check-in Card',
                'code' => 'checkin-card',
                'status' => 1,
                'type' => 'permanent',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * @test
     */
    public function allowed_user_can_supplement_checkin()
    {
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);

        $pastDate = date('Y-m-d', strtotime('-2 days'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getBody()->getContents(), true)['data'];

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
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);

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
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);

        $futureDate = date('Y-m-d', strtotime('+2 days'));

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
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);

        $date = date('Y-m-d', strtotime('-3 days'));

        CheckinRecord::query()->where('user_id', 2)->delete();
        CheckinRecord::query()->create([
            'user_id' => 2,
            'type' => 0,
            'checkin_date' => $date,
            'streak' => 1,
            'checked_in_at' => Carbon::now(),
        ]);

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $date,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function respects_min_supplementary_date_setting()
    {
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);

        $this->setting('primexcalibur-checkin-with-history.minSupplementaryDate', date('Y-m-d', strtotime('-5 days')));

        // Date before the min date
        $tooEarlyDate = date('Y-m-d', strtotime('-7 days'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $tooEarlyDate,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function respects_checkin_range_setting()
    {
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);

        $this->setting('primexcalibur-checkin-with-history.checkinRange', '3');

        // Date beyond 3 days
        $tooOldDate = date('Y-m-d', strtotime('-5 days'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $tooOldDate,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function blocks_supplement_when_span_day_disabled_and_gap_exists()
    {
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);

        $this->setting('primexcalibur-checkin-with-history.spanDayCheckin', '0');

        $threeDaysAgo = date('Y-m-d', strtotime('-3 days'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $threeDaysAgo,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function allows_supplement_when_span_day_disabled_and_all_subsequent_days_are_signed()
    {
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);

        $this->setting('primexcalibur-checkin-with-history.spanDayCheckin', '0');

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $today = date('Y-m-d');

        // Today is signed
        CheckinRecord::query()->where('user_id', 2)->delete();
        CheckinRecord::query()->create([
            'user_id' => 2,
            'type' => 0,
            'checkin_date' => $today,
            'streak' => 1,
            'checked_in_at' => Carbon::now(),
        ]);

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $yesterday,
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function blocks_supplement_when_max_consecutive_supplement_exceeded()
    {
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);

        $this->setting('primexcalibur-checkin-with-history.maxSupplementaryCheckin', '2');

        $day1 = date('Y-m-d', strtotime('-3 days'));
        $day2 = date('Y-m-d', strtotime('-2 days'));
        $day3 = date('Y-m-d', strtotime('-1 day'));

        // 2 consecutive supplements already exist
        CheckinRecord::query()->where('user_id', 2)->delete();
        CheckinRecord::query()->create([
            'user_id' => 2,
            'type' => 1,
            'checkin_date' => $day1,
            'streak' => 1,
            'checked_in_at' => Carbon::now(),
        ]);
        CheckinRecord::query()->create([
            'user_id' => 2,
            'type' => 1,
            'checkin_date' => $day2,
            'streak' => 2,
            'checked_in_at' => Carbon::now(),
        ]);

        // Attempting a 3rd consecutive supplement
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
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);
        $this->prepareStoreBackpack(2, 3);

        $pastDate = date('Y-m-d', strtotime('-1 day'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $remainingCards = $this->database()->table('store_cart')
            ->where('user_id', 2)
            ->where('code', 'checkin-card')
            ->where('status', 1)
            ->count();
        $this->assertEquals(2, $remainingCards);
    }

    /**
     * @test
     */
    public function disallows_supplement_when_no_cards_available()
    {
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);
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
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
            'users' => [
                [
                    'id' => 2,
                    'username' => 'normal',
                    'email' => 'normal@machine.local',
                    'is_email_confirmed' => 1,
                    'money' => 5.0,
                ],
            ],
        ]);
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

        $this->assertEquals(201, $response->getStatusCode());

        $remainingCards = $this->database()->table('store_cart')
            ->where('user_id', 2)
            ->where('code', 'checkin-card')
            ->where('status', 1)
            ->count();
        $this->assertEquals(0, $remainingCards);

        $user = User::query()->find(2);
        $this->assertEquals(15.0, (float) $user->money);
    }

    /**
     * @test
     */
    public function rechains_subsequent_continuous_streaks_correctly()
    {
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);

        $day1 = date('Y-m-d', strtotime('-3 days'));
        $day2 = date('Y-m-d', strtotime('-2 days'));
        $day3 = date('Y-m-d', strtotime('-1 day'));

        CheckinRecord::query()->where('user_id', 2)->delete();
        CheckinRecord::query()->create([
            'user_id' => 2,
            'type' => 0,
            'checkin_date' => $day1,
            'streak' => 1,
            'checked_in_at' => Carbon::now(),
        ]);
        CheckinRecord::query()->create([
            'user_id' => 2,
            'type' => 0,
            'checkin_date' => $day3,
            'streak' => 1,
            'checked_in_at' => Carbon::now(),
        ]);

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $day2,
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

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
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);

        $dispatched = false;
        /** @var Dispatcher $events */
        $events = $this->app()->getContainer()->make(Dispatcher::class);
        $events->listen(CheckedIn::class, function (CheckedIn $event) use (&$dispatched) {
            $dispatched = true;
            $this->assertEquals(2, $event->user->id);
            $this->assertEquals(1, $event->supplementCount);
        });

        $pastDate = date('Y-m-d', strtotime('-1 day'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($dispatched);
    }

    /**
     * @test
     */
    public function suspended_user_is_denied_supplement_checkin()
    {
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);

        $this->database()->table('users')
            ->where('id', 2)
            ->update(['suspended_until' => Carbon::now()->addDay()]);

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
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);
        $this->prepareStoreBackpack(2, 10);

        $pastDate = date('Y-m-d', strtotime('-1 day'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());
        $remainingCards = $this->database()->table('store_cart')
            ->where('user_id', 2)
            ->where('code', 'checkin-card')
            ->where('status', 1)
            ->count();
        $this->assertEquals(9, $remainingCards);
    }

    /**
     * @test
     */
    public function supplement_fails_when_no_checkin_cards_available()
    {
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);
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
        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertArrayHasKey('errors', $body);
    }
}
