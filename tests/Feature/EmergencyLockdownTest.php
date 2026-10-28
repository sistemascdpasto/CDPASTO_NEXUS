<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Application;
use App\Models\EmergencyLockdown;
use App\Models\PanelAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class EmergencyLockdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['nexus.railway.token' => 't', 'nexus.railway.project_id' => 'p', 'nexus.railway.environment_id' => 'e']);
    }

    private function service(string $id, string $name, ?string $image = null, string $status = 'SUCCESS'): array
    {
        return ['node' => [
            'id' => $id,
            'name' => $name,
            'serviceInstances' => ['edges' => [['node' => ['environmentId' => 'e', 'source' => ['image' => $image, 'repo' => $image ? null : 'org/repo']]]]],
            'deployments' => ['edges' => [['node' => ['id' => "dep-{$id}", 'status' => $status, 'createdAt' => now()->toIso8601String(), 'environmentId' => 'e']]]],
        ]];
    }

    /**
     * Railway simulado: Adenar y Tickets apagables, Nexus protegido y una base de datos.
     */
    private function fakeRailway(): void
    {
        Http::fake(['backboard.railway.com/*' => function (Request $request) {
            $query = $request['query'];

            if (str_contains($query, 'project(id')) {
                return Http::response(['data' => ['project' => ['services' => ['edges' => [
                    $this->service('svc-adenar', 'SistemaGestionAdenar'),
                    $this->service('svc-tickets', 'Tickets', status: 'CRASHED'),
                    $this->service('svc-nexus', 'CDPASTO_NEXUS'),
                    $this->service('svc-db', 'MySQL', image: 'mysql:9'),
                ]]]]]);
            }

            return Http::response(['data' => ['ok' => true]]);
        }]);
    }

    private function code(User $user): string
    {
        return (new Google2FA)->getCurrentOtp($user->two_factor_secret);
    }

    public function test_only_superadmins_can_use_the_button()
    {
        $this->actingAs(User::factory()->create())->get('/emergency')->assertForbidden();
        $this->post('/emergency/disconnect')->assertForbidden();
    }

    public function test_targets_exclude_databases_and_nexus()
    {
        $this->fakeRailway();

        $this->actingAs(User::factory()->superadmin()->create())->get('/emergency')
            ->assertInertia(fn ($page) => $page->component('emergency/index')
                ->has('services', 2)
                ->where('services.0.name', 'SistemaGestionAdenar')
                ->where('services.1.name', 'Tickets'));
    }

    public function test_disconnect_requires_password_and_two_factor_code()
    {
        $this->fakeRailway();
        $user = User::factory()->superadmin()->create();

        $this->actingAs($user)->post('/emergency/disconnect', [
            'reason' => 'Ataque detectado', 'confirmation' => 'DESCONECTAR', 'password' => 'incorrecta', 'code' => $this->code($user),
        ])->assertSessionHasErrors('password');

        $this->post('/emergency/disconnect', [
            'reason' => 'Ataque detectado', 'confirmation' => 'DESCONECTAR', 'password' => 'password', 'code' => '000000',
        ])->assertSessionHasErrors('code');

        $this->post('/emergency/disconnect', [
            'reason' => 'Ataque detectado', 'confirmation' => 'desconectar', 'password' => 'password', 'code' => $this->code($user),
        ])->assertSessionHasErrors('confirmation');

        Http::assertNotSent(fn (Request $request) => str_contains($request['query'], 'deploymentStop'));
        $this->assertDatabaseCount(EmergencyLockdown::class, 0);
    }

    public function test_disconnect_stops_active_deployments_and_reconnect_brings_them_back()
    {
        $this->fakeRailway();
        $user = User::factory()->superadmin()->create();
        $app = Application::factory()->create(['railway_service_id' => 'svc-adenar']);

        $this->actingAs($user)->post('/emergency/disconnect', [
            'reason' => 'Ataque detectado', 'confirmation' => 'DESCONECTAR', 'password' => 'password', 'code' => $this->code($user),
        ])->assertSessionHas('success');

        Http::assertSent(fn (Request $request) => str_contains($request['query'], 'deploymentStop') && $request['variables']['id'] === 'dep-svc-adenar');
        Http::assertNotSent(fn (Request $request) => str_contains($request['query'], 'deploymentStop')
            && in_array($request['variables']['id'], ['dep-svc-nexus', 'dep-svc-db', 'dep-svc-tickets'], true));

        $lockdown = EmergencyLockdown::current();
        $this->assertSame(['svc-adenar'], EmergencyLockdown::stoppedServiceIds());
        $this->assertSame('skipped', collect($lockdown->services)->firstWhere('service_id', 'svc-tickets')['state']);
        $this->assertDatabaseHas(Alert::class, ['type' => 'emergency']);
        $this->assertDatabaseHas(PanelAuditLog::class, ['action' => 'emergency.disconnect']);

        // El chequeo de salud no revisa (ni alerta) una app apagada a propósito.
        Http::fake(['*' => Http::response('caído', 503)]);
        $this->artisan('nexus:check-health', ['--all' => true]);
        $this->assertDatabaseMissing('health_checks', ['application_id' => $app->id]);

        $this->fakeRailway();
        Cache::flush(); // el mismo código TOTP no se puede reusar (antirreplay)
        $this->post('/emergency/reconnect', ['password' => 'password', 'code' => $this->code($user)])->assertSessionHas('success');

        Http::assertSent(fn (Request $request) => str_contains($request['query'], 'deploymentRedeploy') && $request['variables']['id'] === 'dep-svc-adenar');
        $this->assertNull(EmergencyLockdown::current());
        $this->assertSame(EmergencyLockdown::RELEASED, $lockdown->fresh()->status);
        $this->assertDatabaseHas(PanelAuditLog::class, ['action' => 'emergency.reconnect']);
    }

    public function test_a_single_service_can_be_disconnected()
    {
        $this->fakeRailway();
        $user = User::factory()->superadmin()->create();

        $this->actingAs($user)->post('/emergency/disconnect', [
            'reason' => 'Solo Adenar comprometido', 'services' => ['svc-adenar'], 'confirmation' => 'DESCONECTAR',
            'password' => 'password', 'code' => $this->code($user),
        ])->assertSessionHas('success');

        $this->assertCount(1, EmergencyLockdown::current()->services);
    }
}
