<?php

namespace Tests\Feature\Preferences;

use App\Models\User;
use App\Models\UserPreference;
use App\Services\UserPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UserPreferenceService (docs/sdd/fase-3-mapa-favoritos-preferencias/spec.md,
 * Part C): one query per request for repeated reads, and set() persists and
 * updates the cache.
 */
class UserPreferenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_reads_cost_one_query_in_the_request(): void
    {
        $user = User::factory()->create();
        UserPreference::factory()->forUser($user)->key('date_format', 'Y-m-d')->create();

        $service = app(UserPreferenceService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $first = $service->get((int) $user->id, 'date_format');
        $second = $service->get((int) $user->id, 'date_format');
        $queries = collect(DB::getQueryLog())->filter(fn (array $entry): bool => str_contains((string) $entry['query'], 'user_preferences'));
        DB::disableQueryLog();

        $this->assertSame('Y-m-d', $first);
        $this->assertSame($first, $second);
        $this->assertCount(1, $queries);
    }

    public function test_set_persists_and_updates_the_cache(): void
    {
        $user = User::factory()->create();
        $service = app(UserPreferenceService::class);

        $service->set((int) $user->id, 'items_per_page', '10');

        $this->assertSame('10', $service->get((int) $user->id, 'items_per_page'));
        $this->assertDatabaseHas('user_preferences', ['user_id' => $user->id, 'key' => 'items_per_page', 'value' => '10']);
    }

    public function test_defaults_fill_missing_keys(): void
    {
        $user = User::factory()->create();
        $service = app(UserPreferenceService::class);

        $this->assertSame('14', $service->get((int) $user->id, 'map_default_zoom'));
        $this->assertSame(15, $service->typed((int) $user->id, 'items_per_page'));
        $this->assertSame(UserPreferenceService::DASHBOARD_WIDGETS, $service->typed((int) $user->id, 'dashboard_widgets'));
    }
}
