<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserFavorite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserFavorite>
 */
class UserFavoriteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'favoritable_type' => 'fleet',
            'favoritable_id' => 1,
            'label' => null,
            'position' => 0,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function of(string $type, int $id, string $position = '0'): static
    {
        return $this->state([
            'favoritable_type' => $type,
            'favoritable_id' => $id,
            'position' => (int) $position,
        ]);
    }
}
