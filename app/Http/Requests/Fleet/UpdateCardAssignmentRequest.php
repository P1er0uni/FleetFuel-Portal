<?php

namespace App\Http\Requests\Fleet;

use App\Http\Requests\Concerns\ResolvesFleet;
use App\Models\Driver;
use App\Models\FuelCard;
use App\Models\Product;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Change a card's vehicle, driver, product restriction or fleet (only
 * before its first purchase; FuelCardService enforces that under the card
 * lock). The card's company never changes. A currently assigned record
 * stays valid even if it has been deactivated since.
 */
class UpdateCardAssignmentRequest extends FormRequest
{
    use ResolvesFleet;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('update', $this->card());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $card = $this->card();

        return [
            'company_id' => ['prohibited'],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')
                ->where('company_id', $card->company_id)
                ->where(fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $card->vehicle_id))],
            'driver_id' => ['nullable', 'integer', Rule::exists('drivers', 'id')
                ->where('company_id', $card->company_id)
                ->where(fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $card->driver_id))],
            'allowed_product_id' => ['nullable', 'integer', Rule::exists('products', 'id')
                ->where(fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $card->allowed_product_id))],
            'fleet_id' => $this->fleetRules($card->company_id),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->fleetMessages() + [
            'company_id.prohibited' => 'Una tarjeta no puede cambiar a otra empresa.',
            'vehicle_id.exists' => 'Elige un vehículo activo de esta empresa.',
            'driver_id.exists' => 'Elige un conductor activo de esta empresa.',
            'allowed_product_id.exists' => 'Elige un producto activo.',
        ];
    }

    public function card(): FuelCard
    {
        $card = $this->route('card');
        assert($card instanceof FuelCard);

        return $card;
    }

    public function vehicle(): ?Vehicle
    {
        $id = $this->validated('vehicle_id');

        return $id === null ? null : Vehicle::query()->where('company_id', $this->card()->company_id)->findOrFail((int) $id);
    }

    public function driver(): ?Driver
    {
        $id = $this->validated('driver_id');

        return $id === null ? null : Driver::query()->where('company_id', $this->card()->company_id)->findOrFail((int) $id);
    }

    public function product(): ?Product
    {
        $id = $this->validated('allowed_product_id');

        return $id === null ? null : Product::query()->findOrFail((int) $id);
    }
}
