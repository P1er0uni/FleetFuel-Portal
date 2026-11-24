<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Which rows a report may read: a half-open UTC range [from, to), the
 * company the rows must belong to, and optionally one fleet of that
 * company (docs/sdd/fase-1-flotas/spec.md, D8: the filter applies on
 * fuel_cards.fleet_id, because the cards are the entity that spends).
 * A manager is always pinned to their own company, whatever the request
 * says, so a fleet of another company matches nothing; an admin sees
 * every company unless they filter by one. The request validation also
 * refuses a fleet outside the chosen company. Station operators get no
 * reports.
 */
final readonly class ReportScope
{
    private function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?int $companyId,
        public ?int $fleetId,
    ) {}

    /**
     * A scope chosen on the command line (reports:explain), not by a
     * signed-in user. Read-only diagnostics only.
     */
    public static function forConsole(CarbonImmutable $from, CarbonImmutable $to, ?int $companyId, ?int $fleetId = null): self
    {
        return new self($from, $to, $companyId, $fleetId);
    }

    public static function for(User $user, CarbonImmutable $from, CarbonImmutable $to, ?int $adminCompanyFilter = null, ?int $fleetFilter = null): self
    {
        return match ($user->role) {
            UserRole::Admin => new self($from, $to, $adminCompanyFilter, $fleetFilter),
            UserRole::CompanyManager => new self($from, $to, (int) $user->company_id, $fleetFilter),
            UserRole::StationOperator => throw new LogicException('Reports are for admins and company managers.'),
        };
    }
}
