<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Enums\ThresholdOperator;
use App\Models\Company;
use App\Models\NotificationRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * CRUD of notification rules (docs/sdd/fase-2-importacion-notificaciones/spec.md,
 * B3). The company is chosen by the server on creation and never changes; the
 * threshold only survives on a quota_warning rule. Every change is audited.
 */
class NotificationRuleService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{fleet_id: int|null, event_type: string, channel: string, threshold_value: string|null, threshold_operator: string|null, active: bool}  $data
     */
    public function create(Company $company, array $data, User $actor): NotificationRule
    {
        return DB::transaction(function () use ($company, $data, $actor): NotificationRule {
            $rule = new NotificationRule;
            $rule->forceFill([
                'company_id' => $company->id,
                'fleet_id' => $data['fleet_id'],
                'event_type' => $data['event_type'],
                'channel' => $data['channel'],
                'threshold_value' => $data['threshold_value'],
                'threshold_operator' => $data['threshold_operator'],
                'active' => $data['active'],
            ])->save();

            $this->audit->record('notification_rule.created', $rule, $actor, $company->id, null, $this->values($rule));

            return $rule;
        });
    }

    /**
     * @param  array{event_type: string, channel: string, threshold_value: string|null, threshold_operator: string|null}  $data
     */
    public function update(NotificationRule $rule, array $data, User $actor): NotificationRule
    {
        return DB::transaction(function () use ($rule, $data, $actor): NotificationRule {
            $rule = NotificationRule::query()->lockForUpdate()->findOrFail($rule->id);
            $old = $this->values($rule);

            $rule->forceFill([
                'event_type' => $data['event_type'],
                'channel' => $data['channel'],
                'threshold_value' => $data['threshold_value'],
                'threshold_operator' => $data['threshold_operator'],
            ]);

            if (! $rule->isDirty()) {
                return $rule;
            }

            $rule->save();
            $this->audit->record('notification_rule.updated', $rule, $actor, $rule->company_id, $old, $this->values($rule));

            return $rule;
        });
    }

    public function toggle(NotificationRule $rule, User $actor): NotificationRule
    {
        return DB::transaction(function () use ($rule, $actor): NotificationRule {
            $rule = NotificationRule::query()->lockForUpdate()->findOrFail($rule->id);
            $old = $rule->active;
            $rule->forceFill(['active' => ! $rule->active])->save();

            $this->audit->record('notification_rule.updated', $rule, $actor, $rule->company_id,
                ['active' => $old], ['active' => $rule->active]);

            return $rule;
        });
    }

    public function delete(NotificationRule $rule, User $actor): void
    {
        DB::transaction(function () use ($rule, $actor): void {
            $rule = NotificationRule::query()->lockForUpdate()->findOrFail($rule->id);
            $this->audit->record('notification_rule.deleted', $rule, $actor, $rule->company_id, $this->values($rule), null);
            $rule->delete();
        });
    }

    /** @return array<string, mixed> */
    private function values(NotificationRule $rule): array
    {
        return [
            'fleet_id' => $rule->fleet_id,
            'event_type' => $rule->event_type->value,
            'channel' => $rule->channel->value,
            'threshold_value' => $rule->threshold_value,
            'threshold_operator' => $rule->threshold_operator?->value,
            'active' => $rule->active,
        ];
    }

    /**
     * Normalise validated input for the service, dropping the threshold unless
     * the event is quota_warning (D12).
     *
     * @param  array<string, mixed>  $validated
     * @return array{fleet_id: int|null, event_type: string, channel: string, threshold_value: string|null, threshold_operator: string|null, active: bool}
     */
    public static function attributes(array $validated, bool $withActive = true): array
    {
        $event = NotificationEventType::from((string) $validated['event_type']);
        $quota = $event === NotificationEventType::QuotaWarning;

        return [
            'fleet_id' => isset($validated['fleet_id']) ? (int) $validated['fleet_id'] : null,
            'event_type' => $event->value,
            'channel' => NotificationChannel::from((string) $validated['channel'])->value,
            'threshold_value' => $quota ? ($validated['threshold_value'] ?? null) : null,
            'threshold_operator' => $quota
                ? (isset($validated['threshold_operator'])
                    ? ThresholdOperator::from((string) $validated['threshold_operator'])->value
                    : null)
                : null,
            'active' => $withActive ? (bool) ($validated['active'] ?? true) : true,
        ];
    }
}
