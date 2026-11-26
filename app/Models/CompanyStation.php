<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One enabled station for a company (Fase 6, área 3). Only consulted when the
 * company has restrict_stations on.
 */
#[Fillable(['company_id', 'station_id'])]
class CompanyStation extends Model
{
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Station, $this> */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
