<?php

namespace App\Http\Requests\Reports;

use App\Http\Requests\Concerns\FiltersBusinessDates;
use App\Models\User;
use App\Support\ReportScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

/**
 * Filters of the report screens: Beirut dates (default the current month),
 * an admin's company, the consumption grouping and a fleet
 * (docs/sdd/fase-1-flotas/spec.md, D8). As on the other list screens, a
 * manager's ?company_id= is ignored and can never widen what they see:
 * ReportScope pins them to their own company, and a fleet outside the
 * chosen company is a validation error, never a wider report.
 */
class ReportRequest extends FormRequest
{
    use FiltersBusinessDates;

    public const GROUPINGS = ['company', 'vehicle', 'product'];

    public function authorize(): bool
    {
        return $this->user()?->can('viewReports') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->dateRangeRules(),
            'company_id' => ['nullable', 'integer', 'min:1'],
            'group_by' => ['nullable', 'string', 'in:'.implode(',', self::GROUPINGS)],
            'fleet_id' => ['nullable', 'integer', 'min:1', $this->fleetExists()],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [$this->dateRangeLimit()];
    }

    public function scope(): ReportScope
    {
        [$from, $to] = $this->utcRange();
        $company = $this->validated('company_id');
        $fleet = $this->validated('fleet_id');

        return ReportScope::for(
            $this->actor(),
            $from,
            $to,
            $company === null ? null : (int) $company,
            $fleet === null ? null : (int) $fleet,
        );
    }

    public function groupBy(): string
    {
        return (string) ($this->validated('group_by') ?? 'company');
    }

    public function actor(): User
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user;
    }

    /**
     * The fleet must exist and belong to the company whose report is being
     * read: the admin's filtered company, or the manager's own company.
     * An admin without a company filter may pick any fleet.
     */
    private function fleetExists(): Exists
    {
        $user = $this->user();

        $companyId = $user instanceof User && $user->isAdmin()
            ? $this->input('company_id')
            : ($user instanceof User ? $user->company_id : null);

        $rule = Rule::exists('fleets', 'id');

        return ctype_digit((string) $companyId) ? $rule->where('company_id', (int) $companyId) : $rule;
    }
}
