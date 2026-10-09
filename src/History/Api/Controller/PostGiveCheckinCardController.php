<?php

namespace Mattoid\CheckinHistory\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Mattoid\CheckinHistory\CheckinHistoryManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PostGiveCheckinCardController implements RequestHandlerInterface
{
    public function __construct(
        protected CheckinHistoryManager $manager
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $body = (array) $request->getParsedBody();

        $amount = (int) Arr::get($body, 'amount', 0);
        $range = (bool) Arr::get($body, 'range', false);
        $username = Arr::get($body, 'username');

        $userMatchCount = $this->manager->giveCheckinCards($actor, is_string($username) ? $username : null, $amount, $range);

        return new JsonResponse([
            'userMatchCount' => $userMatchCount,
        ]);
    }
}
