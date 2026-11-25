<?php

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\NotificationRule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsLedgerFixtures;
use Tests\Concerns\CallsApi;
use Tests\Concerns\ChecksOpenApiContract;
use Tests\TestCase;

/**
 * /api/v1/notifications and /api/v1/notifications/rules
 * (docs/sdd/fase-2-importacion-notificaciones/spec.md, B4): the inbox, the
 * unread count, marking read, and the rules CRUD with scope and abilities.
 * Every response is checked against openapi.json.
 */
class NotificationApiTest extends TestCase
{
    use BuildsLedgerFixtures;
    use CallsApi;
    use ChecksOpenApiContract;
    use RefreshDatabase;

    /** @var array{company: mixed, otherCompany: mixed, station: mixed, otherStation: mixed, operator: mixed, otherOperator: mixed, admin: mixed, manager: mixed, diesel: mixed, petrol: mixed, vehicle: mixed, driver: mixed, card: mixed} */
    private array $world;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::FIXTURE_NOW));
        $this->world = $this->ledgerWorld();
    }

    public function test_the_inbox_lists_filters_and_counts(): void
    {
        $token = $this->apiToken($this->world['manager']);
        $unread = $this->makeNotification('quota_warning', ['title' => 'Uno']);
        $read = $this->makeNotification('card_blocked', ['title' => 'Dos', 'read_at' => now()]);

        $list = $this->req('GET', '/notifications', '/notifications', $token)->assertOk();
        $list->assertJsonPath('meta.total', 2);

        $this->req('GET', '/notifications?status=unread', '/notifications', $token)
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $unread->id);
        $this->req('GET', '/notifications?status=read', '/notifications', $token)
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $read->id);
        $this->req('GET', '/notifications?event_type=card_blocked', '/notifications', $token)
            ->assertOk()->assertJsonPath('meta.total', 1);

        $this->req('GET', '/notifications/unread-count', '/notifications/unread-count', $token)
            ->assertOk()->assertJsonPath('data.unread_count', 1);

        // Unknown filters are refused.
        $this->req('GET', '/notifications?bogus=1', '/notifications', $token)->assertUnprocessable();
    }

    public function test_another_users_notification_is_not_found(): void
    {
        $mine = $this->makeNotification('quota_warning');
        $foreign = Notification::factory()->create(['event_type' => 'quota_warning']);

        $this->req('PATCH', '/notifications/'.$mine->id.'/read', '/notifications/{id}/read', $this->apiToken($this->world['manager']))
            ->assertOk()->assertJsonPath('data.read_at', fn ($value) => $value !== null);

        $this->req('PATCH', '/notifications/'.$foreign->id.'/read', '/notifications/{id}/read', $this->apiToken($this->world['manager']))
            ->assertNotFound();
    }

    public function test_mark_all_read(): void
    {
        $this->makeNotification('quota_warning');
        $this->makeNotification('card_blocked');

        $this->req('POST', '/notifications/mark-all-read', '/notifications/mark-all-read', $this->apiToken($this->world['manager']))
            ->assertOk()->assertJsonPath('data.marked_read', 2);
        $this->assertSame(0, Notification::query()->where('user_id', $this->world['manager']->id)->whereNull('read_at')->count());
    }

    public function test_rules_crud_with_scope_and_abilities(): void
    {
        $manager = $this->apiToken($this->world['manager']);

        $created = $this->req('POST', '/notifications/rules', '/notifications/rules', $manager, [
            'event_type' => 'quota_exceeded',
            'channel' => 'in_app',
        ])->assertCreated();
        $rule = NotificationRule::query()->sole();
        $this->assertSame($this->world['company']->id, $rule->company_id);
        $this->assertSame('/api/v1/notifications/rules/'.$rule->id, $created->headers->get('Location'));

        $this->req('GET', '/notifications/rules', '/notifications/rules', $manager)
            ->assertOk()->assertJsonPath('meta.total', 1);
        $this->req('GET', '/notifications/rules/'.$rule->id, '/notifications/rules/{id}', $manager)->assertOk();

        $this->req('PUT', '/notifications/rules/'.$rule->id, '/notifications/rules/{id}', $manager, [
            'event_type' => 'quota_warning',
            'channel' => 'log',
            'threshold_value' => '80',
            'threshold_operator' => 'gte',
        ])->assertOk()->assertJsonPath('data.channel', 'log')->assertJsonPath('data.threshold_value', '80.000');

        // An operator has neither ability nor role.
        $this->req('GET', '/notifications/rules', '/notifications/rules', $this->apiToken($this->world['operator']))->assertForbidden();

        // A token without notifications:write cannot create.
        $this->req('POST', '/notifications/rules', '/notifications/rules', $this->apiToken($this->world['manager'], ['notifications:read']), [
            'event_type' => 'quota_exceeded', 'channel' => 'in_app',
        ])->assertForbidden();

        // Another company's rule is a 404.
        $foreign = NotificationRule::factory()->create(['company_id' => $this->world['otherCompany']->id]);
        $this->req('GET', '/notifications/rules/'.$foreign->id, '/notifications/rules/{id}', $manager)->assertNotFound();

        $this->req('DELETE', '/notifications/rules/'.$rule->id, '/notifications/rules/{id}', $manager)->assertNoContent();
        $this->assertSame(1, NotificationRule::query()->count());
    }

    public function test_an_admin_must_send_an_integer_company_id(): void
    {
        $admin = $this->apiToken($this->world['admin']);
        $companyId = $this->world['company']->id;

        $this->req('POST', '/notifications/rules', '/notifications/rules', $admin, [
            'event_type' => 'quota_exceeded', 'channel' => 'in_app',
        ])->assertUnprocessable();

        $this->req('POST', '/notifications/rules', '/notifications/rules', $admin, [
            'company_id' => (string) $companyId, 'event_type' => 'quota_exceeded', 'channel' => 'in_app',
        ])->assertUnprocessable();

        $this->req('POST', '/notifications/rules', '/notifications/rules', $admin, [
            'company_id' => $companyId, 'event_type' => 'quota_exceeded', 'channel' => 'in_app',
        ])->assertCreated();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    private function req(string $method, string $path, string $template, ?string $token, array $body = []): TestResponse
    {
        return $this->assertMatchesOpenApi($this->api($method, '/api/v1'.$path, $token, $body), $method, $template);
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
