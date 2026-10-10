<?php

namespace Primexcalibur\Checkin\Tests\integration;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;

class PostGiveCheckinCardTest extends TestCase
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
                    'username' => 'testuser2',
                    'email' => 'user2@machine.local',
                    'is_email_confirmed' => 1,
                    'checkin_card' => 0,
                ],
                [
                    'id' => 4,
                    'username' => 'testuser3',
                    'email' => 'user3@machine.local',
                    'is_email_confirmed' => 1,
                    'checkin_card' => 0,
                ],
            ],
            'group_permission' => [
                ['permission' => 'checkin.issuanceOfSupplementaryCards', 'group_id' => 1],
            ],
        ]);
    }

    /**
     * @test
     */
    public function admin_can_give_cards_to_specific_usernames()
    {
        $response = $this->send(
            $this->request('POST', '/api/give/checkin/card', [
                'authenticatedAs' => 1,
            ])->withParsedBody([
                'username' => 'normal, testuser2',
                'amount' => 5,
                'range' => false,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals(2, $data['userMatchCount']);

        $user2 = User::query()->find(2);
        $user3 = User::query()->find(3);

        $this->assertEquals(5, $user2->checkin_card);
        $this->assertEquals(5, $user3->checkin_card);
    }

    /**
     * @test
     */
    public function handles_mixed_delimiters_correctly()
    {
        $response = $this->send(
            $this->request('POST', '/api/give/checkin/card', [
                'authenticatedAs' => 1,
            ])->withParsedBody([
                'username' => "normal，testuser2 | testuser3 \n",
                'amount' => 2,
                'range' => false,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals(3, $data['userMatchCount']);
    }

    /**
     * @test
     */
    public function admin_can_give_cards_to_all_users()
    {
        $response = $this->send(
            $this->request('POST', '/api/give/checkin/card', [
                'authenticatedAs' => 1,
            ])->withParsedBody([
                'amount' => 3,
                'range' => true,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertGreaterThanOrEqual(3, $data['userMatchCount']);

        $user2 = User::query()->find(2);
        $this->assertEquals(3, $user2->checkin_card);
    }

    /**
     * @test
     */
    public function normal_user_cannot_give_cards()
    {
        $response = $this->send(
            $this->request('POST', '/api/give/checkin/card', [
                'authenticatedAs' => 2,
            ])->withParsedBody([
                'username' => 'normal',
                'amount' => 5,
                'range' => false,
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function guest_cannot_give_cards()
    {
        $response = $this->send(
            $this->request('POST', '/api/give/checkin/card')
                ->withAttribute('bypassCsrfToken', true)
                ->withParsedBody([
                    'amount' => 5,
                    'range' => true,
                ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function disallows_giving_cards_with_zero_or_negative_amount()
    {
        $response = $this->send(
            $this->request('POST', '/api/give/checkin/card', [
                'authenticatedAs' => 1,
            ])->withParsedBody([
                'username' => 'normal',
                'amount' => 0,
                'range' => false,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function disallows_giving_cards_with_empty_username_when_not_all_users()
    {
        $response = $this->send(
            $this->request('POST', '/api/give/checkin/card', [
                'authenticatedAs' => 1,
            ])->withParsedBody([
                'username' => '   ',
                'amount' => 5,
                'range' => false,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function disallows_giving_cards_with_non_existent_usernames()
    {
        $response = $this->send(
            $this->request('POST', '/api/give/checkin/card', [
                'authenticatedAs' => 1,
            ])->withParsedBody([
                'username' => 'non_existent_user_123',
                'amount' => 5,
                'range' => false,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }
}
