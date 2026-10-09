<?php

namespace Mattoid\CheckinHistory\Tests\Integration\Api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Mattoid\CheckinHistory\Event\SupplementaryCheckinEvent;
use Mattoid\CheckinHistory\Model\UserCheckinHistory;

class PostSupplementCheckinTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('huoxin-money-with-history');
        $this->extension('primexcalibur-checkin-with-history');
        $this->setting('mattoid-forum-checkin.span-day-checkin', '1');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
        ]);
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
        $this->assertEquals($pastDate, $data['attributes']['start']);

        $record = UserCheckinHistory::query()->where('user_id', 2)->where('last_checkin_date', $pastDate)->first();
        $this->assertNotNull($record);
        $this->assertEquals(1, $record->type);
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

        $this->assertEquals(403, $response->getStatusCode());
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

        $this->prepareDatabase([
            'user_checkin_history' => [
                [
                    'id' => 1,
                    'user_id' => 2,
                    'type' => 0,
                    'last_checkin_date' => $date,
                    'total_checkin_count' => 1,
                    'total_continuous_checkin_count' => 1,
                    'last_checkin_time' => date('Y-m-d H:i:s'),
                ],
            ],
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

        $this->setting('mattoid-forum-checkin.min-supplementary-date', date('Y-m-d', strtotime('-5 days')));

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

        $this->setting('mattoid-forum-checkin.checkin-range', '3');

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

        $this->setting('mattoid-forum-checkin.span-day-checkin', '0');

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

        $this->setting('mattoid-forum-checkin.span-day-checkin', '0');

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $today = date('Y-m-d');

        // Today is signed
        $this->prepareDatabase([
            'user_checkin_history' => [
                [
                    'id' => 1,
                    'user_id' => 2,
                    'type' => 0,
                    'last_checkin_date' => $today,
                    'total_checkin_count' => 1,
                    'total_continuous_checkin_count' => 1,
                    'last_checkin_time' => date('Y-m-d H:i:s'),
                ],
            ],
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

        $this->setting('mattoid-forum-checkin.max-supplementary-checkin', '2');

        $day1 = date('Y-m-d', strtotime('-3 days'));
        $day2 = date('Y-m-d', strtotime('-2 days'));
        $day3 = date('Y-m-d', strtotime('-1 day'));

        // 2 consecutive supplements already exist
        $this->prepareDatabase([
            'user_checkin_history' => [
                [
                    'id' => 1,
                    'user_id' => 2,
                    'type' => 1,
                    'last_checkin_date' => $day1,
                    'total_checkin_count' => 1,
                    'total_continuous_checkin_count' => 1,
                    'last_checkin_time' => date('Y-m-d H:i:s'),
                ],
                [
                    'id' => 2,
                    'user_id' => 2,
                    'type' => 1,
                    'last_checkin_date' => $day2,
                    'total_checkin_count' => 2,
                    'total_continuous_checkin_count' => 2,
                    'last_checkin_time' => date('Y-m-d H:i:s'),
                ],
            ],
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
            'users' => [
                [
                    'id' => 2,
                    'username' => 'normal',
                    'email' => 'normal@machine.local',
                    'is_email_confirmed' => 1,
                    'checkin_card' => 3,
                ],
            ],
        ]);

        $pastDate = date('Y-m-d', strtotime('-1 day'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $user = User::query()->find(2);
        $this->assertEquals(2, $user->checkin_card);
    }

    /**
     * @test
     */
    public function disallows_supplement_when_only_card_enabled_and_card_zero()
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
                    'checkin_card' => 0,
                ],
            ],
        ]);

        $this->setting('mattoid-forum-checkin.checkin-card', '1');

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
                    'checkin_card' => 1,
                    'money' => 5.0,
                ],
            ],
        ]);

        $this->setting('mattoid-forum-checkin.consumption', '0');
        $this->setting('mattoid-forum-checkin.reward-money', '10');

        $pastDate = date('Y-m-d', strtotime('-1 day'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $user = User::query()->find(2);
        $this->assertEquals(0, $user->checkin_card);
        $this->assertEquals(15.0, (float) $user->money); // 5 + 10 reward
    }

    /**
     * @test
     */
    public function deducts_money_when_no_card_and_awards_reward_money()
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
                    'checkin_card' => 0,
                    'money' => 50.0,
                ],
            ],
        ]);

        $this->setting('mattoid-forum-checkin.consumption', '10');
        $this->setting('mattoid-forum-checkin.reward-money', '2');

        $pastDate = date('Y-m-d', strtotime('-1 day'));

        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $pastDate,
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $user = User::query()->find(2);
        // 50 - 10 (consumption) + 2 (reward) = 42
        $this->assertEquals(42.0, (float) $user->money);
    }

    /**
     * @test
     */
    public function blocks_supplement_when_money_balance_insufficient()
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
                    'checkin_card' => 0,
                    'money' => 20.0, // Not enough for 100
                ],
            ],
        ]);

        $this->setting('mattoid-forum-checkin.consumption', '100');
        $this->setting('mattoid-forum-checkin.reward-money', '0');

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
    public function rechains_subsequent_continuous_streaks_correctly()
    {
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.allowSupplementaryCheckIn', 'group_id' => 3],
            ],
        ]);

        $day1 = date('Y-m-d', strtotime('-3 days'));
        $day2 = date('Y-m-d', strtotime('-2 days')); // Missing day to supplement
        $day3 = date('Y-m-d', strtotime('-1 day'));

        // Day 1 has streak 1, Day 3 has streak 1
        $this->prepareDatabase([
            'user_checkin_history' => [
                [
                    'id' => 1,
                    'user_id' => 2,
                    'type' => 0,
                    'last_checkin_date' => $day1,
                    'total_checkin_count' => 1,
                    'total_continuous_checkin_count' => 1,
                    'last_checkin_time' => date('Y-m-d H:i:s'),
                ],
                [
                    'id' => 2,
                    'user_id' => 2,
                    'type' => 0,
                    'last_checkin_date' => $day3,
                    'total_checkin_count' => 2,
                    'total_continuous_checkin_count' => 1,
                    'last_checkin_time' => date('Y-m-d H:i:s'),
                ],
            ],
        ]);

        // Supplement Day 2
        $response = $this->send(
            $this->request('POST', '/api/supplement/checkin', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'date' => $day2,
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $recordDay2 = UserCheckinHistory::query()->where('user_id', 2)->where('last_checkin_date', $day2)->first();
        $recordDay3 = UserCheckinHistory::query()->where('user_id', 2)->where('last_checkin_date', $day3)->first();

        $this->assertEquals(2, $recordDay2->total_continuous_checkin_count);
        $this->assertEquals(3, $recordDay3->total_continuous_checkin_count); // Automatically re-chained to 3!
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
        $events->listen(SupplementaryCheckinEvent::class, function (SupplementaryCheckinEvent $event) use (&$dispatched) {
            $dispatched = true;
            $this->assertEquals(2, $event->user->id);
            $this->assertEquals(1, $event->checkinCount);
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
}
