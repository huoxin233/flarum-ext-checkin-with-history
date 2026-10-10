<?php

namespace Primexcalibur\Checkin\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Primexcalibur\Checkin\Service\CheckinCardService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PostGiveCheckinCardController implements RequestHandlerInterface
{
    public function __construct(
        protected CheckinCardService $cards
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $body = (array) $request->getParsedBody();

        $amount = (int) Arr::get($body, 'amount', 0);
        $range = (bool) Arr::get($body, 'range', false);
        $username = Arr::get($body, 'username');

        $userMatchCount = $this->cards->giveCheckinCards($actor, is_string($username) ? $username : null, $amount, $range);

        return new JsonResponse([
            'userMatchCount' => $userMatchCount,
        ]);
    }
}
