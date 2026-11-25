<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Fleet;

/** GET /api/v1/fleets */
class ListFleetsRequest extends ListCompanyRecordsRequest
{
    protected function model(): string
    {
        return Fleet::class;
    }
}
