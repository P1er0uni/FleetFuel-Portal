<?php

namespace App\Http\Requests\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Enums\ThresholdOperator;
use App\Http\Requests\Fleet\ResolvesCompany;
use App\Models\NotificationRule;
use App\Rules\DecimalString;
use App\Services\NotificationRuleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create a notification rule (web, Spanish). The company is server-chosen; the
 * fleet must belong to it; a threshold is only accepted for quota_warning and
 * needs both its value and its operator.
 */
class StoreNotificationRuleRequest extends FormRequest
{
    use ResolvesCompany;

    public function authorize(): bool
    {
        return $this->actor()->can('create', NotificationRule::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => $this->companyRules(),
            'fleet_id' => [
                'nullable', 'integer',
                Rule::exists('fleets', 'id')->where('company_id', $this->targetCompanyId()),
            ],
            'event_type' => ['required', Rule::enum(NotificationEventType::class)],
            'channel' => ['required', Rule::enum(NotificationChannel::class)],
            'threshold_value' => ['nullable', new DecimalString(9, 3)],
            'threshold_operator' => ['nullable', Rule::enum(ThresholdOperator::class)],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->companyMessages() + [
            'event_type.required' => 'Elige un evento.',
            'channel.required' => 'Elige un canal.',
            'fleet_id.exists' => 'Elige una flota de esta empresa.',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $event = (string) $this->input('event_type');
                $value = trim((string) $this->input('threshold_value'));
                $operator = trim((string) $this->input('threshold_operator'));

                if ($event !== NotificationEventType::QuotaWarning->value) {
                    if ($value !== '') {
                        $validator->errors()->add('threshold_value', 'El umbral solo aplica al aviso de cuota.');
                    }
                    if ($operator !== '') {
                        $validator->errors()->add('threshold_operator', 'El umbral solo aplica al aviso de cuota.');
                    }

                    return;
                }

                if (($value !== '') !== ($operator !== '')) {
                    $validator->errors()->add('threshold_value', 'Indica el umbral y su operador juntos, o deja ambos vacíos.');
                }
            },
        ];
    }

    /**
     * @return array{fleet_id: int|null, event_type: string, channel: string, threshold_value: string|null, threshold_operator: string|null, active: bool}
     */
    public function ruleAttributes(): array
    {
        return NotificationRuleService::attributes($this->validated(), withActive: true);
    }
}
