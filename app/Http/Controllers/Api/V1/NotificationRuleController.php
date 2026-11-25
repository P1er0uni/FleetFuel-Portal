<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithPages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListNotificationRulesRequest;
use App\Http\Requests\Api\V1\StoreNotificationRuleRequest;
use App\Http\Requests\Api\V1\UpdateNotificationRuleRequest;
use App\Http\Resources\NotificationRuleResource;
use App\Models\NotificationRule;
use App\Models\User;
use App\Services\NotificationRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Notification rules for the API (docs/sdd/fase-2-importacion-notificaciones/spec.md,
 * B4): list, create, detail, update and delete. Admins manage every company;
 * a manager only their own (the {notificationRule} binding is tenant-scoped,
 * so another company's rule is a 404).
 */
class NotificationRuleController extends Controller
{
    use RespondsWithPages;

    public function index(ListNotificationRulesRequest $request): JsonResponse
    {
        $page = NotificationRule::query()
            ->visibleTo($this->user($request))
            ->with(['company', 'fleet'])
            ->when($request->companyFilter(), fn ($query, $company) => $query->where('company_id', $company))
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return $this->pageResponse($page, NotificationRuleResource::class, $request);
    }

    public function store(StoreNotificationRuleRequest $request, NotificationRuleService $rules): JsonResponse
    {
        $rule = $rules->create($request->company(), $request->ruleAttributes(), $this->user($request));

        return NotificationRuleResource::make($rule)
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('api.v1.notifications.rules.show', $rule, absolute: false));
    }

    public function show(Request $request, NotificationRule $notificationRule): JsonResponse
    {
        Gate::authorize('view', $notificationRule);

        return NotificationRuleResource::make($notificationRule)->response();
    }

    public function update(UpdateNotificationRuleRequest $request, NotificationRule $notificationRule, NotificationRuleService $rules): JsonResponse
    {
        return NotificationRuleResource::make(
            $rules->update($notificationRule, $request->ruleAttributes(), $this->user($request)),
        )->response();
    }

    public function destroy(Request $request, NotificationRule $notificationRule, NotificationRuleService $rules): Response
    {
        Gate::authorize('delete', $notificationRule);
        $rules->delete($notificationRule, $this->user($request));

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
