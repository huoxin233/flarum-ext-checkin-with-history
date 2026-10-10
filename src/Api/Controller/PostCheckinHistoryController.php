<?php

namespace Primexcalibur\Checkin\Api\Controller;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Locale\Translator;
use Illuminate\Support\Arr;
use Primexcalibur\Checkin\Api\Serializer\CheckinHistorySerializer;
use Primexcalibur\Checkin\Service\CheckInService;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class PostCheckinHistoryController extends AbstractCreateController
{
    public $serializer = CheckinHistorySerializer::class;

    public $include = ['user'];

    public function __construct(
        protected CheckInService $checkins,
        protected Translator $translator
    ) {
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $date = Arr::get($request->getParsedBody(), 'date');

        if (empty($date) || ! is_string($date)) {
            throw new ValidationException([
                'message' => $this->translator->trans('primexcalibur-checkin-with-history.api.error.date-required'),
            ]);
        }

        return $this->checkins->supplement($actor, $date);
    }
}
