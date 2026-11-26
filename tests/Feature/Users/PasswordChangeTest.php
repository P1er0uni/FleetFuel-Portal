<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserAccountService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * Forced password change after an admin sets a temporary password
 * (docs/sdd/fase-4-usuarios-admin/, D7).
 */
class PasswordChangeTest extends TestCase
{
    use BuildsLedgerFixtures;
    use RefreshDatabase;

    /** @var array{company: mixed, otherCompany: mixed, station: mixed, otherStation: mixed, operator: mixed, otherOperator: mixed, admin: mixed, manager: mixed, diesel: mixed, petrol: mixed, vehicle: mixed, driver: mixed, card: mixed} */
    private array $world;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::FIXTURE_NOW));
        $this->world = $this->ledgerWorld();
    }

    public function test_a_temporary_password_forces_a_change(): void
    {
        $user = $this->temporaryUser();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('password.change.edit'));
        $this->actingAs($user)->get('/password/change')->assertOk();
    }

    public function test_changing_the_password_clears_the_flag(): void
    {
        $user = $this->temporaryUser();

        $this->actingAs($user)->put('/password/change', [
            'current_password' => 'Temporal-12345',
            'password' => 'NuevaClave-12345',
            'password_confirmation' => 'NuevaClave-12345',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NuevaClave-12345', $user->password));
        $this->assertFalse(Hash::check('Temporal-12345', $user->password));

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_a_wrong_current_password_is_rejected(): void
    {
        $user = $this->temporaryUser();

        $this->actingAs($user)->put('/password/change', [
            'current_password' => 'equivocada',
            'password' => 'NuevaClave-12345',
            'password_confirmation' => 'NuevaClave-12345',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue($user->refresh()->must_change_password);
    }

    public function test_a_normal_user_is_not_forced_to_change(): void
    {
        $this->actingAs($this->world['manager'])->get('/dashboard')->assertOk();
    }

    private function temporaryUser(): User
    {
        return app(UserAccountService::class)->create(
            'Temporal User',
            'temporal@fleetfuel.test',
            'Temporal-12345',
            UserRole::CompanyManager,
            $this->world['company'],
        );
    }
}
