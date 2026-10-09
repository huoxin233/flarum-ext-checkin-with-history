<?php

namespace Mattoid\CheckinHistory\Tests\Integration\Api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Illuminate\Support\Arr;

class ListCheckinHistoryTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('huoxin-money-with-history');
        $this->extension('primexcalibur-checkin-with-history');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                [
                    'id' => 3,
                    'username' => 'otheruser',
                    'email' => 'other@machine.local',
                    'is_email_confirmed' => 1,
                ],
            ],
            'user_checkin_history' => [
                [
                    'id' => 1,
                    'user_id' => 2,
                    'type' => 0,
                    'last_checkin_date' => '2026-08-10',
                    'total_checkin_count' => 1,
                    'total_continuous_checkin_count' => 1,
                    'last_checkin_time' => '2026-08-10 08:00:00',
                ],
                [
                    'id' => 2,
                    'user_id' => 2,
                    'type' => 0,
                    'last_checkin_date' => '2026-08-11',
                    'total_checkin_count' => 2,
                    'total_continuous_checkin_count' => 2,
                    'last_checkin_time' => '2026-08-11 08:00:00',
                ],
                [
                    'id' => 3,
                    'user_id' => 3,
                    'type' => 0,
                    'last_checkin_date' => '2026-08-12',
                    'total_checkin_count' => 1,
                    'total_continuous_checkin_count' => 1,
                    'last_checkin_time' => '2026-08-12 08:00:00',
                ],
            ],
        ]);
    }

    /**
     * @test
     */
    public function normal_user_can_view_own_history()
    {
        $response = $this->send(
            $this->request('GET', '/api/checkin/history', [
                'authenticatedAs' => 2,
            ])->withQueryParams([
                'userId' => 2,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody()->getContents(), true)['data'];
        $this->assertCount(2, $data);
        $this->assertEquals(['1', '2'], Arr::pluck($data, 'id'));
    }

    /**
     * @test
     */
    public function can_query_history_by_username()
    {
        $response = $this->send(
            $this->request('GET', '/api/checkin/history', [
                'authenticatedAs' => 2,
            ])->withQueryParams([
                'username' => 'normal',
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody()->getContents(), true)['data'];
        $this->assertCount(2, $data);
    }

    /**
     * @test
     */
    public function returns_empty_data_for_nonexistent_user()
    {
        $response = $this->send(
            $this->request('GET', '/api/checkin/history', [
                'authenticatedAs' => 2,
            ])->withQueryParams([
                'username' => 'nonexistent_user_xyz',
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody()->getContents(), true)['data'];
        $this->assertEmpty($data);
    }

    /**
     * @test
     */
    public function normal_user_cannot_view_others_history_without_permission()
    {
        $response = $this->send(
            $this->request('GET', '/api/checkin/history', [
                'authenticatedAs' => 2,
            ])->withQueryParams([
                'userId' => 3,
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function user_with_permission_can_view_others_history()
    {
        $this->prepareDatabase([
            'group_permission' => [
                ['permission' => 'checkin.queryOthersHistory', 'group_id' => 3],
            ],
        ]);

        $response = $this->send(
            $this->request('GET', '/api/checkin/history', [
                'authenticatedAs' => 2,
            ])->withQueryParams([
                'userId' => 3,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody()->getContents(), true)['data'];
        $this->assertCount(1, $data);
        $this->assertEquals('3', $data[0]['id']);
    }

    /**
     * @test
     */
    public function guest_cannot_view_history_without_permission()
    {
        $response = $this->send(
            $this->request('GET', '/api/checkin/history')->withQueryParams([
                'userId' => 2,
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function history_can_be_filtered_by_start_and_end_dates()
    {
        $response = $this->send(
            $this->request('GET', '/api/checkin/history', [
                'authenticatedAs' => 2,
            ])->withQueryParams([
                'userId' => 2,
                'start' => '2026-08-11',
                'end' => '2026-08-11',
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody()->getContents(), true)['data'];
        $this->assertCount(1, $data);
        $this->assertEquals('2', $data[0]['id']);
    }

    /**
     * @test
     */
    public function includes_user_relationship_in_jsonapi_compound_document()
    {
        $response = $this->send(
            $this->request('GET', '/api/checkin/history', [
                'authenticatedAs' => 2,
            ])->withQueryParams([
                'userId' => 2,
                'include' => 'user',
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('included', $body);
        $includedUsers = array_filter($body['included'], fn ($item) => $item['type'] === 'users');
        $this->assertNotEmpty($includedUsers);
        $this->assertEquals('normal', $includedUsers[0]['attributes']['username']);
    }
}
