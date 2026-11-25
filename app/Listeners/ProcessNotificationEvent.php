<?php

namespace App\Listeners;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Enums\UserRole;
use App\Events\DomainNotificationEvent;
use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Models\NotificationRule;
use App\Models\User;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Turns a domain event into notifications through the rules that match its
 * company, optional fleet and event type (docs/sdd/fase-2-importacion-notificaciones/spec.md,
 * B2).
 *
 * - A rule limited to a fleet only fires for that fleet's events.
 * - A threshold is compared only for quota_warning (D12).
 * - Within one hour, the same (rule, event, entity) notifies once (D11); the
 *   check runs before creating the in-app rows that record the delivery.
 * - in_app fills the company managers' inbox, email sends a Mailable, and log
 *   writes to audit_logs and the Laravel log (D10).
 */
class ProcessNotificationEvent
{
    private const DEDUP_MINUTES = 60;

    public function __construct(private readonly AuditService $audit) {}

    public function handle(DomainNotificationEvent $event): void
    {
        $rules = NotificationRule::query()
            ->where('company_id', $event->companyId())
            ->where('event_type', $event->type())
            ->where('active', true)
            ->where(function ($query) use ($event): void {
                $query->whereNull('fleet_id');
                if ($event->fleetId() !== null) {
                    $query->orWhere('fleet_id', $event->fleetId());
                }
            })
            ->get();

        foreach ($rules as $rule) {
            if (! $this->passesThreshold($rule, $event)) {
                continue;
            }

            if ($this->recentlyNotified($rule, $event)) {
                continue;
            }

            match ($rule->channel) {
                NotificationChannel::InApp => $this->deliverInApp($rule, $event),
                NotificationChannel::Email => $this->deliverEmail($rule, $event),
                NotificationChannel::Log => $this->deliverLog($rule, $event),
            };
        }
    }

    private function passesThreshold(NotificationRule $rule, DomainNotificationEvent $event): bool
    {
        if ($event->type() !== NotificationEventType::QuotaWarning) {
            return true;
        }

        if ($rule->threshold_value === null || $rule->threshold_operator === null) {
            return true;
        }

        $percent = (string) ($event->context()['percent'] ?? '0');

        return $rule->threshold_operator->satisfiedBy($percent, (string) $rule->threshold_value);
    }

    private function recentlyNotified(NotificationRule $rule, DomainNotificationEvent $event): bool
    {
        return Notification::query()
            ->where('notification_rule_id', $rule->id)
            ->where('event_type', $event->type()->value)
            ->where('entity_hash', $event->entity()->entityHash())
            ->where('created_at', '>=', CarbonImmutable::now()->subMinutes(self::DEDUP_MINUTES))
            ->exists();
    }

    private function deliverInApp(NotificationRule $rule, DomainNotificationEvent $event): void
    {
        foreach ($this->managers($rule) as $manager) {
            Notification::query()->forceCreate([
                'company_id' => $rule->company_id,
                'notification_rule_id' => $rule->id,
                'user_id' => $manager->id,
                'event_type' => $event->type()->value,
                'title' => $event->title(),
                'message' => $event->message(),
                'payload' => $event->context() + [
                    'entity' => $event->entity()->type,
                    'entity_id' => $event->entity()->id,
                ],
                'entity_hash' => $event->entity()->entityHash(),
                'read_at' => null,
            ]);
        }
    }

    private function deliverEmail(NotificationRule $rule, DomainNotificationEvent $event): void
    {
        foreach ($this->managers($rule) as $manager) {
            Mail::to($manager->email)->send(new NotificationMail($event->title(), $event->message()));
        }
    }

    private function deliverLog(NotificationRule $rule, DomainNotificationEvent $event): void
    {
        Log::warning('Notification rule fired.', [
            'notification_rule_id' => $rule->id,
            'event_type' => $event->type()->value,
            'title' => $event->title(),
        ]);

        $this->audit->record('notification.sent', $rule, null, $rule->company_id, null, [
            'event_type' => $event->type()->value,
            'channel' => NotificationChannel::Log->value,
            'entity_hash' => $event->entity()->entityHash(),
        ]);
    }

    /** @return Collection<int, User> */
    private function managers(NotificationRule $rule)
    {
        return User::query()
            ->where('company_id', $rule->company_id)
            ->where('role', UserRole::CompanyManager->value)
            ->where('is_active', true)
            ->get();
    }
}
