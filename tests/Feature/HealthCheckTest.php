<?php

namespace Tests\Feature;

use App\Enums\AlertType;
use App\Enums\AppStatus;
use App\Models\Alert;
use App\Models\Application;
use App\Services\HealthChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_healthy_agent_marks_the_app_online_and_signs_the_request()
    {
        $app = Application::factory()->create(['url' => 'https://adenar.test']);

        Http::fake(['adenar.test/*' => Http::response(['status' => 'ok', 'components' => ['database' => ['ok' => true, 'ms' => 3]]])]);

        app(HealthChecker::class)->check($app);

        $this->assertSame(AppStatus::Online, $app->fresh()->status);
        Http::assertSent(function (Request $request) use ($app) {
            $expected = hash_hmac('sha256', $request->header('X-Nexus-Timestamp')[0]."\n", $app->api_key);

            return $request->url() === 'https://adenar.test/nexus/health'
                && $request->header('X-Nexus-Signature')[0] === $expected;
        });
    }

    public function test_failing_internal_component_marks_the_app_degraded()
    {
        $app = Application::factory()->create(['url' => 'https://adenar.test']);

        Http::fake(['*' => Http::response(['components' => ['database' => ['ok' => false, 'error' => 'SQLSTATE[HY000] [2002]']]])]);

        app(HealthChecker::class)->check($app);

        $this->assertSame(AppStatus::Degraded, $app->fresh()->status);
        $this->assertSame('database', array_key_first($app->fresh()->last_components));
    }

    public function test_app_is_down_only_after_consecutive_failures_then_recovers_with_alerts()
    {
        $app = Application::factory()->static()->create(['url' => 'https://reempaque.test']);
        $checker = app(HealthChecker::class);

        Http::fakeSequence('reempaque.test/*')->push('caído', 502)->push('caído', 502)->push('ok', 200);

        $checker->check($app);
        $this->assertSame(AppStatus::Degraded, $app->fresh()->status, 'Un fallo aislado no declara caída');
        $this->assertSame(0, Alert::count());

        $checker->check($app->fresh());
        $this->assertSame(AppStatus::Down, $app->fresh()->status);
        $this->assertDatabaseHas(Alert::class, ['application_id' => $app->id, 'type' => AlertType::Down->value]);

        $checker->check($app->fresh());
        $this->assertSame(AppStatus::Online, $app->fresh()->status);
        $this->assertDatabaseHas(Alert::class, ['application_id' => $app->id, 'type' => AlertType::Recovered->value]);
    }

    public function test_missing_agent_is_reported_as_degraded_not_down()
    {
        $app = Application::factory()->create(['url' => 'https://tickets.test']);

        Http::fake(['*' => Http::response('Not Found', 404)]);

        app(HealthChecker::class)->check($app);

        $check = $app->healthChecks()->sole();
        $this->assertSame(AppStatus::Degraded, $check->status);
        $this->assertStringContainsString('agente', $check->error);
    }

    public function test_nexus_own_resource_failures_do_not_count_as_outages()
    {
        $app = Application::factory()->static()->create(['url' => 'https://easyol.test']);
        Http::fake(fn () => throw new ConnectionException('cURL error 6: getaddrinfo() thread failed to start'));

        app(HealthChecker::class)->check($app);
        app(HealthChecker::class)->check($app->fresh());

        $this->assertSame(AppStatus::Unknown, $app->healthChecks()->latest('id')->first()->status);
        $this->assertSame(0, $app->fresh()->consecutive_failures);
        $this->assertNotSame(AppStatus::Down, $app->fresh()->status);
        $this->assertSame(0, Alert::count());
    }

    public function test_inside_railway_the_private_url_is_used()
    {
        $app = Application::factory()->create(['url' => 'https://adenar.up.railway.app', 'internal_url' => 'http://adenar.railway.internal:8080']);
        Http::fake(['*' => Http::response(['components' => []])]);

        config(['nexus.railway.inside' => false]);
        $this->assertSame('https://adenar.up.railway.app/nexus/health', $app->healthUrl());

        config(['nexus.railway.inside' => true]);
        app(HealthChecker::class)->check($app);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://adenar.railway.internal:8080/nexus/health');
        $this->assertSame('http://adenar.railway.internal:8080/nexus/commands', $app->agentUrl('commands'));
    }

    public function test_command_only_checks_apps_that_are_due()
    {
        $due = Application::factory()->create(['url' => 'https://a.test', 'last_checked_at' => now()->subMinutes(6), 'check_interval_minutes' => 5]);
        $notDue = Application::factory()->create(['url' => 'https://b.test', 'last_checked_at' => now()->subMinute(), 'check_interval_minutes' => 5]);

        Http::fake(['*' => Http::response(['components' => []])]);

        $this->artisan('nexus:check-health')->assertSuccessful();

        $this->assertSame(1, $due->healthChecks()->count());
        $this->assertSame(0, $notDue->healthChecks()->count());
    }
}
