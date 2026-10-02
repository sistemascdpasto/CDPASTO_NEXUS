<?php

namespace Tests\Feature;

use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\Application;
use App\Models\AppSession;
use App\Models\AuditLog;
use App\Models\ErrorGroup;
use App\Models\LoginEvent;
use App\Models\RequestMetric;
use App\Models\User;
use App\Notifications\AlertRaised;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class IngestTest extends TestCase
{
    use RefreshDatabase;

    private function appWithKey(array $attributes = []): array
    {
        $app = Application::factory()->make($attributes);
        $key = $app->regenerateApiKey();
        $app->save();

        return [$app, $key];
    }

    private function exception(array $overrides = []): array
    {
        return [
            'class' => 'ErrorException',
            'message' => 'Undefined variable $cliente',
            'file' => 'app/Http/Controllers/ClienteController.php',
            'line' => 42,
            'user_id' => '7',
            'user_name' => 'Ana',
            'occurred_at' => now()->toIso8601String(),
            ...$overrides,
        ];
    }

    public function test_requests_without_a_valid_key_are_rejected()
    {
        $this->postJson('/api/v1/ingest', [])->assertUnauthorized();
        $this->withToken('nx_invalida')->postJson('/api/v1/ingest', [])->assertUnauthorized();
    }

    public function test_inactive_applications_can_not_send_data()
    {
        [, $key] = $this->appWithKey(['is_active' => false]);

        $this->withToken($key)->postJson('/api/v1/ingest', [])->assertUnauthorized();
    }

    public function test_telemetry_batch_is_stored()
    {
        [$app, $key] = $this->appWithKey();

        $this->withToken($key)->postJson('/api/v1/ingest', [
            'agent_version' => '1.0.0',
            'requests' => [[
                'bucket' => now()->startOfMinute()->toIso8601String(), 'method' => 'GET', 'route' => '/clientes/{id}',
                'count' => 10, 'total_ms' => 1500, 'max_ms' => 400, 'errors_4xx' => 1, 'errors_5xx' => 0,
            ]],
            'logins' => [['event' => 'login', 'user_id' => '7', 'identifier' => 'ana@cdpasto.com', 'user_name' => 'Ana', 'ip' => '10.0.0.1', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0', 'occurred_at' => now()->toIso8601String()]],
            'audits' => [[
                'action' => 'updated', 'module' => 'Cliente', 'record_id' => 15,
                'old' => ['telefono' => '300'], 'new' => ['telefono' => '311'],
                'user_id' => '7', 'user_name' => 'Ana', 'occurred_at' => now()->toIso8601String(),
            ]],
            'sessions' => [['user_id' => '7', 'user_name' => 'Ana', 'user_email' => 'ana@cdpasto.com', 'user_role' => 'admin', 'last_activity' => now()->toIso8601String()]],
        ])->assertOk()->assertJsonPath('stored.audits', 1);

        $this->assertDatabaseHas(RequestMetric::class, ['application_id' => $app->id, 'route' => '/clientes/{id}', 'count' => 10]);
        $this->assertDatabaseHas(LoginEvent::class, ['application_id' => $app->id, 'event' => 'login', 'device' => 'Chrome · Windows']);

        $audit = AuditLog::firstWhere('application_id', $app->id);
        $this->assertSame(['telefono' => '300'], $audit->old_values);
        $this->assertSame(['telefono' => '311'], $audit->new_values);

        $session = AppSession::firstWhere('application_id', $app->id);
        $this->assertSame('Ana', $session->user_name);
        $this->assertNotNull($session->login_at, 'La hora de inicio se toma del último login registrado');

        $this->assertSame('1.0.0', $app->fresh()->agent_version);
    }

    public function test_session_snapshot_replaces_the_previous_one()
    {
        [$app, $key] = $this->appWithKey();
        AppSession::create(['application_id' => $app->id, 'external_user_id' => '1', 'last_activity_at' => now()]);

        $this->withToken($key)->postJson('/api/v1/ingest', ['sessions' => []])->assertOk();

        $this->assertDatabaseCount(AppSession::class, 0);
    }

    public function test_repeated_exceptions_are_grouped_and_resolved_ones_reopen()
    {
        [$app, $key] = $this->appWithKey();

        $this->withToken($key)->postJson('/api/v1/ingest', ['exceptions' => [$this->exception(), $this->exception()]])->assertOk();

        $group = ErrorGroup::sole();
        $this->assertSame(2, $group->occurrences);
        $this->assertSame(2, $group->events()->count());

        $group->update(['resolved_at' => now()->subMinute()]);

        $this->withToken($key)->postJson('/api/v1/ingest', ['exceptions' => [$this->exception()]])->assertOk();

        $this->assertNull($group->fresh()->resolved_at);
        $this->assertSame(1, ErrorGroup::count());
    }

    public function test_error_spike_raises_an_alert_and_notifies_responsibles()
    {
        Notification::fake();

        [$app, $key] = $this->appWithKey(['error_threshold' => 3]);
        $jefe = User::factory()->jefe()->create();
        $jefe->applications()->attach($app);
        $otro = User::factory()->jefe()->create();
        $admin = User::factory()->superadmin()->create();

        $this->withToken($key)->postJson('/api/v1/ingest', ['exceptions' => array_fill(0, 3, $this->exception())])->assertOk();

        $this->assertDatabaseHas(Alert::class, ['application_id' => $app->id, 'type' => AlertType::ErrorSpike->value]);
        Notification::assertSentTo([$jefe, $admin], AlertRaised::class);
        Notification::assertNotSentTo($otro, AlertRaised::class);

        // Enfriamiento: no se repite la alerta.
        $this->withToken($key)->postJson('/api/v1/ingest', ['exceptions' => [$this->exception()]])->assertOk();
        $this->assertSame(1, Alert::where('type', AlertType::ErrorSpike)->count());
    }

    public function test_failed_logins_and_mass_deletes_raise_alerts()
    {
        Notification::fake();
        [$app, $key] = $this->appWithKey(['failed_login_threshold' => 3, 'mass_delete_threshold' => 2]);

        $failed = array_fill(0, 3, ['event' => 'failed', 'identifier' => 'admin', 'ip' => '1.2.3.4', 'occurred_at' => now()->toIso8601String()]);
        $deletes = array_fill(0, 2, ['action' => 'deleted', 'module' => 'Cliente', 'user_id' => '9', 'user_name' => 'Pedro', 'occurred_at' => now()->toIso8601String()]);

        $this->withToken($key)->postJson('/api/v1/ingest', ['logins' => $failed, 'audits' => $deletes])->assertOk();

        $this->assertDatabaseHas(Alert::class, ['application_id' => $app->id, 'type' => AlertType::FailedLogins->value]);
        $this->assertDatabaseHas(Alert::class, ['application_id' => $app->id, 'type' => AlertType::MassDelete->value]);
    }

    public function test_malformed_batches_are_rejected()
    {
        [, $key] = $this->appWithKey();

        $this->withToken($key)->postJson('/api/v1/ingest', ['logins' => [['event' => 'hackeo']]])->assertUnprocessable();
    }
}
