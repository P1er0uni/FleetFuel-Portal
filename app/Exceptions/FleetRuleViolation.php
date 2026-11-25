<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * A fleet rule refused a POS purchase. It is a ValidationException (the card,
 * station and product are all valid; the fleet's policy just says no), so it
 * still renders as a 422 on the API and a form error on the web. It also
 * carries the IDs the notification listener needs for the fleet_rule_violated
 * event, which is dispatched after the purchase transaction rolls back.
 */
class FleetRuleViolation extends ValidationException
{
    public ?int $companyId = null;

    public ?int $fleetId = null;

    public ?int $cardId = null;

    public ?string $ruleName = null;

    public static function forRule(
        string $field,
        string $message,
        int $companyId,
        ?int $fleetId,
        ?int $cardId,
        string $ruleName,
    ): self {
        $violation = self::withMessages([$field => $message]);
        $violation->companyId = $companyId;
        $violation->fleetId = $fleetId;
        $violation->cardId = $cardId;
        $violation->ruleName = $ruleName;

        return $violation;
    }
}
