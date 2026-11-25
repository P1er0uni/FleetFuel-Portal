<?php

namespace App\Http\Controllers\Web;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Enums\ThresholdOperator;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FiltersLists;
use App\Http\Requests\Notifications\StoreNotificationRuleRequest;
use App\Http\Requests\Notifications\UpdateNotificationRuleRequest;
use App\Models\Company;
use App\Models\NotificationRule;
use App\Services\NotificationRuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * CRUD of notification rules over the web (docs/sdd/fase-2-importacion-notificaciones/spec.md,
 * B3). Admins manage every company; a manager only their own (the {notificationRule}
 * binding is tenant-scoped, so another company's rule is a 404).
 */
class NotificationRuleController extends Controller
{
    use FiltersLists;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', NotificationRule::class);
        $user = $this->actor($request);

        return view('notifications.rules.index', [
            'rules' => NotificationRule::query()
                ->visibleTo($user)
                ->with(['company', 'fleet'])
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', NotificationRule::class);
        $user = $this->actor($request);

        return view('notifications.rules.form', [
            'rule' => null,
            'companies' => $this->companyOptions($user, activeOnly: true),
            'ownCompany' => $user->isAdmin() ? null : $user->loadMissing('company')->company,
            'fleets' => $this->fleetOptions($user->isAdmin() ? null : (int) $user->company_id),
            'events' => NotificationEventType::cases(),
            'channels' => NotificationChannel::cases(),
            'operators' => ThresholdOperator::cases(),
        ]);
    }

    public function store(StoreNotificationRuleRequest $request, NotificationRuleService $rules): RedirectResponse
    {
        $rule = $rules->create($request->company(), $request->ruleAttributes(), $this->actor($request));

        return redirect()->route('notifications.rules.index')->with('status', 'Regla de notificación creada.');
    }

    public function edit(Request $request, NotificationRule $notificationRule): View
    {
        Gate::authorize('update', $notificationRule);
        $user = $this->actor($request);

        return view('notifications.rules.form', [
            'rule' => $notificationRule->loadMissing('company'),
            'companies' => null,
            'ownCompany' => $notificationRule->company,
            'fleets' => $this->fleetOptions($notificationRule->company_id),
            'events' => NotificationEventType::cases(),
            'channels' => NotificationChannel::cases(),
            'operators' => ThresholdOperator::cases(),
        ]);
    }

    public function update(UpdateNotificationRuleRequest $request, NotificationRule $notificationRule, NotificationRuleService $rules): RedirectResponse
    {
        $rules->update($notificationRule, $request->ruleAttributes(), $this->actor($request));

        return redirect()->route('notifications.rules.index')->with('status', 'Regla de notificación guardada.');
    }

    public function toggle(Request $request, NotificationRule $notificationRule, NotificationRuleService $rules): RedirectResponse
    {
        Gate::authorize('update', $notificationRule);
        $rule = $rules->toggle($notificationRule, $this->actor($request));

        return back()->with('status', $rule->active ? 'Regla activada.' : 'Regla desactivada.');
    }

    public function destroy(Request $request, NotificationRule $notificationRule, NotificationRuleService $rules): RedirectResponse
    {
        Gate::authorize('delete', $notificationRule);
        $rules->delete($notificationRule, $this->actor($request));

        return redirect()->route('notifications.rules.index')->with('status', 'Regla de notificación eliminada.');
    }
}
