<?php

namespace Ziven\checkin\Api\Controller;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Api\Serializer\UserSerializer;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;
use Ziven\checkin\Service\CheckInService;

class CheckInController extends AbstractShowController
{
    public $serializer = UserSerializer::class;

    public function __construct(private CheckInService $checkInService)
    {
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        return $this->checkInService->checkIn(RequestUtil::getActor($request));
    }
}
