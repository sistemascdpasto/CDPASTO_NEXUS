<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\LoginEvent;
use App\Models\PanelAuditLog;
use App\Models\RemoteCommand;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\AgentInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperuserFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function agentInfo(array $overrides = []): array
    {
        return array_replace_recursive([
            'environment' => ['env' => 'production', 'debug' => true, 'maintenance' => false, 'php' => '8.2', 'laravel' => '12', 'agent' => '1.1.0', 'drivers' => [], 'config_cached' => true, 'routes_cached' => true],
            'packages' => [],
            'security' => [
                ['key' => 'debug', 'label' => 'APP_DEBUG desactivado', 'ok' => false, 'detail' => 'Con APP_DEBUG=true…'],
                ['key' => 'https', 'label' => 'APP_URL con HTTPS', 'ok' => true, 'detail' => 'ok'],
            ],
            'storage' => ['total_gb' => 5, 'used_gb' => 4.6, 'used_percent' => 92],
            'failed_jobs' => ['total' => 3, 'recent' => []],
            'users' => ['total' => 10],
        ], $overrides);
    }

    public function test_agent_activity_is_ingested_per_user_and_hour()
    {
        $app = Application::factory()->make();
        $key = $app->regenerateApiKey();
        $app->save();

        $this->withToken($key)->postJson('/api/v1/ingest', ['activity' => [[
            'bucket' => now()->startOfHour()->toIso8601String(), 'user_id' => '7', 'user_name' => 'Ana', 'requests' => 12,
            'routes' => ['GET /flota/vehiculos' => 8, 'POST /flota/vehiculos/{id}/documentos' => 4],
        ]]])->assertOk();

        $activity = UserActivity::sole();
        $this->assertSame(12, $activity->requests);
        $this->assertSame(8, $activity->routes['GET /flota/vehiculos']);
    }

    public function test_agent_info_is_collected_and_findings_reach_the_dashboard()
    {
        $app = Application::factory()->create(['url' => 'https://adenar.test', 'last_ingest_at' => now()]);
        Http::fake(['adenar.test/nexus/info' => Http::response($this->agentInfo())]);

        $this->artisan('nexus:collect-info')->assertSuccessful();

        $this->assertCount(1, AgentInfo::findings($app->fresh()));

        $this->actingAs(User::factory()->superadmin()->create())->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('findings', 1)
                ->where('findings.0.key', 'debug')
                ->where('applications.0.failed_jobs', 3)
                ->where('applications.0.storage_percent', 92));
    }

    public function test_superadmin_can_put_an_app_in_maintenance_and_it_is_audited()
    {
        $app = Application::factory()->create(['url' => 'https://adenar.test']);
        Http::fake([
            'adenar.test/nexus/commands' => Http::response(['ok' => true, 'message' => 'Aplicación en modo mantenimiento.']),
            'adenar.test/nexus/info' => Http::response($this->agentInfo(['environment' => ['maintenance' => true]])),
        ]);

        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->post("/applications/{$app->id}/operations", ['type' => 'maintenance_on', 'reason' => 'Migración de datos'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas(RemoteCommand::class, ['type' => 'maintenance_on', 'status' => 'done', 'external_user_id' => null]);
        $this->assertDatabaseHas(PanelAuditLog::class, ['action' => 'remote.maintenance_on', 'user_id' => $admin->id]);
        $this->assertTrue($app->fresh()->last_info['environment']['maintenance']);
        Http::assertSent(fn ($r) => $r->url() === 'https://adenar.test/nexus/commands' && $r['type'] === 'maintenance_on' && $r['user_id'] === null);
    }

    public function test_only_superadmins_can_run_operations_or_browse_databases()
    {
        $app = Application::factory()->create(['database_service_id' => 'svc-mysql']);
        $jefe = User::factory()->jefe()->create();
        $jefe->applications()->attach($app);

        $this->actingAs($jefe);
        $this->post("/applications/{$app->id}/operations", ['type' => 'cache_clear'])->assertForbidden();
        $this->post("/applications/{$app->id}/railway/restart")->assertForbidden();
        $this->get("/applications/{$app->id}/database")->assertForbidden();
        $this->assertDatabaseCount(RemoteCommand::class, 0);
    }

    public function test_restart_uses_the_active_railway_deployment()
    {
        config(['nexus.railway.token' => 't', 'nexus.railway.project_id' => 'p', 'nexus.railway.environment_id' => 'e']);
        $app = Application::factory()->create(['railway_service_id' => 'svc-1']);
        Deployment::create(['application_id' => $app->id, 'railway_id' => 'dep-viejo', 'status' => 'REMOVED', 'deployed_at' => now()->subDay()]);
        Deployment::create(['application_id' => $app->id, 'railway_id' => 'dep-activo', 'status' => 'SUCCESS', 'deployed_at' => now()]);

        Http::fake(['backboard.railway.com/*' => Http::response(['data' => ['deploymentRestart' => true]])]);

        $this->actingAs(User::factory()->superadmin()->create())->post("/applications/{$app->id}/railway/restart")->assertSessionHas('success');

        Http::assertSent(fn ($r) => str_contains($r['query'], 'deploymentRestart') && $r['variables']['id'] === 'dep-activo');
        $this->assertDatabaseHas(PanelAuditLog::class, ['action' => 'railway.restart']);
    }

    public function test_deployment_logs_are_returned_without_debug_lines()
    {
        config(['nexus.railway.token' => 't', 'nexus.railway.project_id' => 'p', 'nexus.railway.environment_id' => 'e']);
        $app = Application::factory()->create();
        $deployment = Deployment::create(['application_id' => $app->id, 'railway_id' => 'dep-1', 'status' => 'SUCCESS', 'deployed_at' => now()]);

        Http::fake(['backboard.railway.com/*' => Http::response(['data' => ['deploymentLogs' => [
            ['message' => 'rewrote request', 'severity' => 'debug', 'timestamp' => now()->toIso8601String()],
            ['message' => 'SQLSTATE[HY000] Connection refused', 'severity' => 'error', 'timestamp' => now()->toIso8601String()],
        ]]])]);

        $this->actingAs(User::factory()->superadmin()->create())
            ->getJson("/applications/{$app->id}/deployments/{$deployment->id}/logs")
            ->assertOk()
            ->assertJsonCount(1, 'logs')
            ->assertJsonPath('logs.0.severity', 'error');
    }

    public function test_database_page_reports_connection_problems_instead_of_failing()
    {
        config(['nexus.railway.token' => 't', 'nexus.railway.project_id' => 'p', 'nexus.railway.environment_id' => 'e']);
        $app = Application::factory()->create(['database_service_id' => 'svc-mysql']);
        Http::fake(['backboard.railway.com/*' => Http::response(['data' => ['variables' => ['MYSQL_URL' => 'mysql://root:x@mysql.railway.internal:3306/railway']]])]);

        $this->actingAs(User::factory()->superadmin()->create())->get("/applications/{$app->id}/database")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('overview', null)->where('error', fn ($error) => str_contains($error, 'MYSQL_PUBLIC_URL')));
    }

    public function test_user_360_gathers_logins_actions_and_activity()
    {
        $app = Application::factory()->create();
        $lector = User::factory()->create();
        $lector->applications()->attach($app);

        LoginEvent::create(['application_id' => $app->id, 'external_user_id' => '7', 'user_name' => 'Ana', 'event' => 'login', 'occurred_at' => now()]);
        AuditLog::create(['application_id' => $app->id, 'external_user_id' => '7', 'user_name' => 'Ana', 'action' => 'uploaded', 'module' => 'Flota / Documentos', 'new_values' => ['archivos' => [['nombre' => 'soat.pdf']]], 'occurred_at' => now()]);
        AuditLog::create(['application_id' => $app->id, 'external_user_id' => '7', 'action' => 'deleted', 'module' => 'Flota / Vehiculo', 'occurred_at' => now()]);
        AuditLog::create(['application_id' => $app->id, 'external_user_id' => '8', 'action' => 'deleted', 'module' => 'Otro', 'occurred_at' => now()]);
        UserActivity::create(['application_id' => $app->id, 'external_user_id' => '7', 'bucket' => now()->startOfHour(), 'requests' => 5, 'routes' => ['GET /flota' => 5]]);

        $this->actingAs($lector)->get("/applications/{$app->id}/users/7")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('name', 'Ana')
                ->where('stats.logins_30d', 1)
                ->where('stats.actions_30d.uploaded', 1)
                ->has('audits.data', 2)
                ->where('topRoutes.GET /flota', 5));

        $this->get("/applications/{$app->id}/users/7?action=uploaded")
            ->assertInertia(fn (Assert $page) => $page->has('audits.data', 1));

        $this->get("/applications/{$app->id}/users")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('directory', null)->has('known', 1));
    }

    public function test_users_of_unassigned_apps_are_not_visible()
    {
        $app = Application::factory()->create();

        $this->actingAs(User::factory()->create())->get("/applications/{$app->id}/users/1")->assertForbidden();
    }
}
