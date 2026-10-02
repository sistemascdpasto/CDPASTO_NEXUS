<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AppSession;
use App\Models\AuditLog;
use App\Models\PanelAuditLog;
use App\Models\RemoteCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_only_see_their_assigned_applications()
    {
        [$mine, $other] = Application::factory()->count(2)->create();
        $lector = User::factory()->create();
        $lector->applications()->attach($mine);

        $this->actingAs($lector)->get('/applications')
            ->assertInertia(fn (Assert $page) => $page->has('applications', 1)->where('applications.0.id', $mine->id));

        $this->get("/applications/{$mine->id}")->assertOk();
        $this->get("/applications/{$other->id}")->assertForbidden();
    }

    public function test_audit_logs_are_scoped_to_assigned_applications()
    {
        [$mine, $other] = Application::factory()->count(2)->create();
        foreach ([$mine, $other] as $app) {
            AuditLog::create(['application_id' => $app->id, 'action' => 'deleted', 'module' => 'Cliente', 'occurred_at' => now()]);
        }

        $lector = User::factory()->create();
        $lector->applications()->attach($mine);

        $this->actingAs($lector)->get('/audit')
            ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.application_id', $mine->id));
    }

    public function test_only_superadmins_manage_applications_and_panel_users()
    {
        $jefe = User::factory()->jefe()->create();
        $app = Application::factory()->create();
        $jefe->applications()->attach($app);

        $this->actingAs($jefe);
        $this->get('/applications/create')->assertForbidden();
        $this->put("/applications/{$app->id}", ['name' => 'X'])->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->get('/panel-audit')->assertForbidden();
    }

    public function test_superadmin_registers_an_application_and_receives_its_key_once()
    {
        $admin = User::factory()->superadmin()->create();
        $jefe = User::factory()->jefe()->create();

        $response = $this->actingAs($admin)->post('/applications', [
            'name' => 'Sistema de Tickets',
            'type' => 'laravel',
            'url' => 'https://tickets.up.railway.app',
            'environment' => 'production',
            'check_interval_minutes' => 5,
            'slow_threshold_ms' => 3000,
            'error_threshold' => 20,
            'failed_login_threshold' => 10,
            'mass_delete_threshold' => 30,
            'is_active' => true,
            'user_ids' => [$jefe->id],
        ]);

        $app = Application::sole();
        $response->assertRedirect("/applications/{$app->id}")->assertSessionHas('apiKey');

        $this->assertSame($app->id, Application::findByApiKey(session('apiKey'))?->id);
        $this->assertTrue($jefe->canAccessApplication($app));
        $this->assertDatabaseHas(PanelAuditLog::class, ['action' => 'application.created', 'user_id' => $admin->id]);
    }

    public function test_jefe_can_force_logout_on_assigned_app_and_it_is_audited()
    {
        $app = Application::factory()->create(['url' => 'https://adenar.test']);
        $jefe = User::factory()->jefe()->create();
        $jefe->applications()->attach($app);
        AppSession::create(['application_id' => $app->id, 'external_user_id' => '15', 'user_name' => 'Carlos', 'last_activity_at' => now()]);

        Http::fake(['adenar.test/nexus/commands' => Http::response(['ok' => true, 'message' => 'Se cerraron 1 sesiones.'])]);

        $this->actingAs($jefe)
            ->post("/applications/{$app->id}/commands", ['type' => 'logout', 'external_user_id' => '15', 'target_name' => 'Carlos', 'reason' => 'Acceso indebido'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas(RemoteCommand::class, ['type' => 'logout', 'status' => 'done', 'user_id' => $jefe->id]);
        $this->assertDatabaseCount(AppSession::class, 0);
        $this->assertDatabaseHas(PanelAuditLog::class, ['action' => 'remote.logout', 'user_id' => $jefe->id]);
        Http::assertSent(fn ($request) => $request['type'] === 'logout' && $request['user_id'] === '15' && $request->hasHeader('X-Nexus-Signature'));
    }

    public function test_readers_can_not_run_remote_commands()
    {
        $app = Application::factory()->create();
        $lector = User::factory()->create();
        $lector->applications()->attach($app);

        $this->actingAs($lector)->post("/applications/{$app->id}/commands", ['type' => 'block', 'external_user_id' => '1'])->assertForbidden();

        $this->assertDatabaseCount(RemoteCommand::class, 0);
    }

    public function test_failed_remote_command_is_reported()
    {
        $app = Application::factory()->create(['url' => 'https://adenar.test']);
        $admin = User::factory()->superadmin()->create();

        Http::fake(['*' => Http::response(['ok' => false, 'message' => 'Firma inválida'], 401)]);

        $this->actingAs($admin)->post("/applications/{$app->id}/commands", ['type' => 'block', 'external_user_id' => '3'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas(RemoteCommand::class, ['status' => 'failed']);
    }

    public function test_superadmin_creates_a_panel_user_with_assigned_apps()
    {
        $admin = User::factory()->superadmin()->create();
        $app = Application::factory()->create();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Jefe de Flota',
            'email' => 'flota@cdpasto.com',
            'role' => 'jefe',
            'password' => 'ClaveSegura123',
            'password_confirmation' => 'ClaveSegura123',
            'is_active' => true,
            'receive_alerts' => true,
            'application_ids' => [$app->id],
        ])->assertRedirect('/users');

        $user = User::firstWhere('email', 'flota@cdpasto.com');
        $this->assertFalse($user->hasTwoFactorEnabled(), 'Debe configurar el 2FA en su primer ingreso');
        $this->assertTrue($user->canAccessApplication($app));
    }

    public function test_superadmin_can_not_demote_or_deactivate_themselves()
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'lector',
            'is_active' => true,
        ])->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->isSuperadmin());
    }

    public function test_audit_can_be_exported_to_excel_and_pdf()
    {
        $admin = User::factory()->superadmin()->create();
        $app = Application::factory()->create();
        AuditLog::create(['application_id' => $app->id, 'action' => 'updated', 'module' => 'Cliente', 'record_id' => '4', 'old_values' => ['a' => 1], 'new_values' => ['a' => 2], 'occurred_at' => now()]);

        $this->actingAs($admin)->get('/audit/export/xlsx')->assertOk()->assertDownload();
        $this->get('/audit/export/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->assertSame(2, PanelAuditLog::where('action', 'audit.exported')->count());
    }
}
