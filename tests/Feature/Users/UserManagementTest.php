<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * Administration of accounts, admin only (docs/sdd/fase-4-usuarios-admin/):
 * list/filter, create, edit, role change, activate/deactivate, protections,
 * authorization, the DB constraint and the audit trail.
 */
class UserManagementTest extends TestCase
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

    public function test_admin_lists_and_filters_users(): void
    {
        $this->actingAs($this->world['admin'])->get('/users')
            ->assertOk()
            ->assertSee($this->world['manager']->email)
            ->assertSee($this->world['operator']->email);

        $this->actingAs($this->world['admin'])->get('/users?role=company_manager')
            ->assertOk()
            ->assertSee($this->world['manager']->email)
            ->assertDontSee($this->world['operator']->email);
    }

    public function test_admin_creates_a_manager_with_its_company(): void
    {
        $this->actingAs($this->world['admin'])
            ->post('/users', [
                'name' => 'Nueva Gerente',
                'email' => 'Nueva.Gerente@FleetFuel.test',
                'role' => 'company_manager',
                'company_id' => $this->world['company']->id,
                'password' => 'Temporal-123456',
                'password_confirmation' => 'Temporal-123456',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'nueva.gerente@fleetfuel.test')->sole();
        $this->assertSame(UserRole::CompanyManager, $user->role);
        $this->assertSame($this->world['company']->id, $user->company_id);
        $this->assertTrue($user->must_change_password);
        $this->assertSame(1, AuditLog::query()->where('action', 'user.created')->count());
    }

    public function test_creation_respects_role_scope_and_unique_email(): void
    {
        $admin = $this->world['admin'];

        $this->actingAs($admin)->post('/users', [
            'name' => 'Sin empresa', 'email' => 'sin.empresa@fleetfuel.test', 'role' => 'company_manager',
            'password' => 'Temporal-123456', 'password_confirmation' => 'Temporal-123456',
        ])->assertSessionHasErrors('company_id');

        $this->actingAs($admin)->post('/users', [
            'name' => 'Sin estación', 'email' => 'sin.estacion@fleetfuel.test', 'role' => 'station_operator',
            'password' => 'Temporal-123456', 'password_confirmation' => 'Temporal-123456',
        ])->assertSessionHasErrors('station_id');

        $this->actingAs($admin)->post('/users', [
            'name' => 'Admin con empresa', 'email' => 'admin.empresa@fleetfuel.test', 'role' => 'admin',
            'company_id' => $this->world['company']->id,
            'password' => 'Temporal-123456', 'password_confirmation' => 'Temporal-123456',
        ])->assertSessionHasErrors('company_id');

        $this->actingAs($admin)->post('/users', [
            'name' => 'Duplicado', 'email' => $this->world['manager']->email, 'role' => 'company_manager',
            'company_id' => $this->world['company']->id,
            'password' => 'Temporal-123456', 'password_confirmation' => 'Temporal-123456',
        ])->assertSessionHasErrors('email');

        $this->assertSame(0, User::query()->where('email', 'sin.empresa@fleetfuel.test')->count());
    }

    public function test_admin_edits_name_and_email(): void
    {
        $manager = $this->world['manager'];

        $this->actingAs($this->world['admin'])->put('/users/'.$manager->id, [
            'name' => 'Nombre Corregido',
            'email' => 'corregido@fleetfuel.test',
            'role' => 'company_manager',
            'company_id' => $this->world['company']->id,
        ])->assertRedirect(route('users.index'));

        $manager->refresh();
        $this->assertSame('Nombre Corregido', $manager->name);
        $this->assertSame('corregido@fleetfuel.test', $manager->email);
        $this->assertSame(1, AuditLog::query()->where('action', 'user.updated')->count());
    }

    public function test_changing_role_revokes_tokens_and_audits(): void
    {
        $manager = $this->world['manager'];
        $manager->createToken('qa');
        $this->assertSame(1, $manager->tokens()->count());

        $this->actingAs($this->world['admin'])->put('/users/'.$manager->id, [
            'name' => $manager->name,
            'email' => $manager->email,
            'role' => 'station_operator',
            'station_id' => $this->world['station']->id,
        ])->assertRedirect(route('users.index'));

        $manager->refresh();
        $this->assertSame(UserRole::StationOperator, $manager->role);
        $this->assertSame(0, $manager->tokens()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'user.role_changed')->count());
    }

    public function test_activate_and_deactivate(): void
    {
        $operator = $this->world['operator'];
        $operator->createToken('qa');

        $this->actingAs($this->world['admin'])
            ->patch('/users/'.$operator->id.'/status', ['is_active' => 0])
            ->assertRedirect();

        $operator->refresh();
        $this->assertFalse($operator->is_active);
        $this->assertSame(0, $operator->tokens()->count());

        $this->actingAs($this->world['admin'])
            ->patch('/users/'.$operator->id.'/status', ['is_active' => 1])
            ->assertRedirect();
        $this->assertTrue($operator->refresh()->is_active);
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $admin = $this->world['admin'];

        $this->actingAs($admin)
            ->patch('/users/'.$admin->id.'/status', ['is_active' => 0])
            ->assertSessionHasErrors('user');

        $this->assertTrue($admin->refresh()->is_active);
    }

    public function test_non_admins_cannot_manage_users(): void
    {
        $this->actingAs($this->world['manager'])->get('/users')->assertForbidden();
        $this->actingAs($this->world['manager'])->post('/users', [])->assertForbidden();
        $this->actingAs($this->world['manager'])->get('/users/'.$this->world['operator']->id.'/edit')->assertForbidden();
        $this->actingAs($this->world['operator'])->get('/users')->assertForbidden();
        $this->startNewRequestCycle();
        $this->get('/users')->assertRedirect(route('login'));
    }

    public function test_there_is_no_delete_route(): void
    {
        $this->actingAs($this->world['admin'])
            ->delete('/users/'.$this->world['manager']->id)
            ->assertStatus(405);
    }

    public function test_the_database_rejects_an_invalid_role_scope(): void
    {
        $this->expectException(QueryException::class);

        User::query()->forceCreate([
            'name' => 'Admin con empresa',
            'email' => 'bad.scope@fleetfuel.test',
            'password' => 'x',
            'role' => UserRole::Admin,
            'company_id' => $this->world['company']->id,
            'station_id' => null,
            'is_active' => true,
        ]);
    }

    public function test_the_audit_trail_never_stores_the_password(): void
    {
        $this->actingAs($this->world['admin'])->post('/users', [
            'name' => 'Con Clave',
            'email' => 'clave@fleetfuel.test',
            'role' => 'company_manager',
            'company_id' => $this->world['company']->id,
            'password' => 'SecretaPass12345',
            'password_confirmation' => 'SecretaPass12345',
        ])->assertRedirect();

        $audit = AuditLog::query()->where('action', 'user.created')->sole();
        $serialized = json_encode([$audit->old_values, $audit->new_values]);

        $this->assertStringNotContainsString('SecretaPass12345', (string) $serialized);
    }
}
