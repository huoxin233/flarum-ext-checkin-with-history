<?php

namespace Mattoid\CheckinHistory\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Mattoid\CheckinHistory\Api\Serializer\CheckinHistorySerializer;
use Mattoid\CheckinHistory\Model\UserCheckinHistory;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListCheckinHistoryController extends AbstractListController
{
    public $serializer = CheckinHistorySerializer::class;

    public $include = ['user'];

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $params = $request->getQueryParams();

        $userId = Arr::get($params, 'userId');
        $username = Arr::get($params, 'username');
        $start = Arr::get($params, 'start');
        $end = Arr::get($params, 'end');

        // Resolve target user
        $targetUser = null;
        if ($userId) {
            $targetUser = User::query()->find($userId);
        } elseif ($username) {
            $targetUser = User::query()->where('username', $username)->first();
        } elseif (! $actor->isGuest()) {
            $targetUser = $actor;
        }

        if (! $targetUser) {
            return [];
        }

        // Permission check: viewing own history vs viewing others' history
        $isSelf = ! $actor->isGuest() && $actor->id === $targetUser->id;
        if (! $isSelf && ! $actor->can('checkin.queryOthersHistory')) {
            throw new PermissionDeniedException();
        }

        $query = UserCheckinHistory::query()
            ->where('user_id', $targetUser->id)
            ->with('user');

        // Normalize start date (accepts Y-m-d or ISO 8601 strings)
        if (! empty($start)) {
            $startDate = substr((string) $start, 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                $query->where('last_checkin_date', '>=', $startDate);
            }
        }

        // Normalize end date (accepts Y-m-d or ISO 8601 strings)
        if (! empty($end)) {
            $endDate = substr((string) $end, 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                $query->where('last_checkin_date', '<=', $endDate);
            }
        }

        return $query->orderBy('last_checkin_date', 'asc')->get();
    }
}
