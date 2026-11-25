<?php

namespace Tests\Feature\Notifications;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\NotificationRule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\TestCase;

/**
 * The notification screens (docs/sdd/fase-2-importacion-notificaciones/spec.md,
 * B3): the bell counter, the inbox with filters, marking read, and the rules
 * CRUD with tenant scope and role checks.
 */
class NotificationScreensTest extends TestCase
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

    public function test_the_bell_shows_the_unread_count(): void
    {
        $this->makeNotification('quota_warning');
        $this->makeNotification('quota_warning');
        $this->makeNotification('quota_warning', ['read_at' => now()]);

        $response = $this->actingAs($this->world['manager'])->get('/dashboard')->assertOk();

        $response->assertSee('data-bell-count="2"', false);
        $response->assertSee('Notificaciones');
    }

    public function test_the_inbox_lists_and_filters(): void
    {
        $unread = $this->makeNotification('quota_warning', ['title' => 'Aviso sin leer']);
        $read = $this->makeNotification('card_blocked', ['title' => 'Bloqueo leído', 'read_at' => now()]);

        $this->actingAs($this->world['manager'])->get('/notifications')
            ->assertOk()->assertSee('Aviso sin leer')->assertSee('Bloqueo leído');

        $this->assertSame(['Aviso sin leer'], $this->listedTitles('/notifications?status=unread'));
        $this->assertSame(['Bloqueo leído'], $this->listedTitles('/notifications?event_type=card_blocked'));
    }

    /**
     * Titles of the paginated table (not the bell dropdown), newest first.
     *
     * @return list<string>
     */
    private function listedTitles(string $uri): array
    {
        $response = $this->actingAs($this->world['manager'])->get($uri)->assertOk();

        return $response->viewData('notifications')->pluck('title')->all();
    }

    public function test_a_notification_is_marked_read_and_all_are_marked_read(): void
    {
        $first = $this->makeNotification('quota_warning');
        $second = $this->makeNotification('quota_warning');

        $this->actingAs($this->world['manager'])
            ->patch(route('notifications.read', $first))
            ->assertRedirect();
        $this->assertNotNull($first->refresh()->read_at);

        $this->actingAs($this->world['manager'])
            ->post(route('notifications.mark-all-read'))
            ->assertRedirect();
        $this->assertNotNull($second->refresh()->read_at);
    }

    public function test_operators_have_no_notification_screens(): void
    {
        $this->actingAs($this->world['operator'])->get('/notifications')->assertForbidden();
        $this->actingAs($this->world['operator'])->get('/notifications/rules')->assertForbidden();
    }

    public function test_a_manager_manages_their_own_rules(): void
    {
        $this->actingAs($this->world['manager'])
            ->post(route('notifications.rules.store'), [
                'event_type' => 'quota_exceeded',
                'channel' => 'in_app',
            ])
            ->assertRedirect(route('notifications.rules.index'));

        $rule = NotificationRule::query()->sole();
        $this->assertSame($this->world['company']->id, $rule->company_id);
        $this->assertTrue($rule->active);
        $this->assertSame(1, AuditLog::query()->where('action', 'notification_rule.created')->count());

        $this->actingAs($this->world['manager'])
            ->put(route('notifications.rules.update', $rule), [
                'event_type' => 'quota_warning',
                'channel' => 'log',
                'threshold_value' => '80',
                'threshold_operator' => 'gte',
            ])
            ->assertRedirect(route('notifications.rules.index'));

        $rule->refresh();
        $this->assertSame('quota_warning', $rule->event_type->value);
        $this->assertSame('80.000', $rule->threshold_value);

        $this->actingAs($this->world['manager'])
            ->patch(route('notifications.rules.toggle', $rule))
            ->assertRedirect();
        $this->assertFalse($rule->refresh()->active);

        $this->actingAs($this->world['manager'])
            ->delete(route('notifications.rules.destroy', $rule))
            ->assertRedirect(route('notifications.rules.index'));
        $this->assertSame(0, NotificationRule::query()->count());
    }

    public function test_a_threshold_must_match_the_event(): void
    {
        $this->actingAs($this->world['manager'])
            ->post(route('notifications.rules.store'), [
                'event_type' => 'card_blocked',
                'channel' => 'in_app',
                'threshold_value' => '80',
            ])
            ->assertSessionHasErrors(['threshold_value']);

        $this->actingAs($this->world['manager'])
            ->post(route('notifications.rules.store'), [
                'event_type' => 'quota_warning',
                'channel' => 'in_app',
                'threshold_value' => '80',
            ])
            ->assertSessionHasErrors(['threshold_value']);

        $this->assertSame(0, NotificationRule::query()->count());
    }

    public function test_a_manager_cannot_touch_another_companys_rule(): void
    {
        $foreign = NotificationRule::factory()->create(['company_id' => $this->world['otherCompany']->id]);

        $this->actingAs($this->world['manager'])->get(route('notifications.rules.edit', $foreign))->assertNotFound();
        $this->actingAs($this->world['manager'])
            ->put(route('notifications.rules.update', $foreign), ['event_type' => 'quota_exceeded', 'channel' => 'log'])
            ->assertNotFound();
        $this->actingAs($this->world['manager'])->delete(route('notifications.rules.destroy', $foreign))->assertNotFound();

        $this->assertSame(1, NotificationRule::query()->count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeNotification(string $eventType, array $attributes = []): Notification
    {
        return Notification::factory()
            ->forUser($this->world['manager'])
            ->create(['event_type' => $eventType] + $attributes);
    }
}
