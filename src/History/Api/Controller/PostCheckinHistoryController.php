<?php

namespace Mattoid\CheckinHistory\Api\Controller;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Locale\Translator;
use Illuminate\Support\Arr;
use Mattoid\CheckinHistory\Api\Serializer\CheckinHistorySerializer;
use Mattoid\CheckinHistory\CheckinHistoryManager;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class PostCheckinHistoryController extends AbstractCreateController
{
    public $serializer = CheckinHistorySerializer::class;

    public $include = ['user'];

    public function __construct(
        protected CheckinHistoryManager $manager,
        protected Translator $translator
    ) {
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $date = Arr::get($request->getParsedBody(), 'date');

        if (empty($date) || ! is_string($date)) {
            throw new ValidationException([
                'message' => $this->translator->trans('mattoid-daily-check-in-history.api.error.date-required'),
            ]);
        }

        return $this->manager->supplementCheckin($actor, $date);
    }
}
