<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AppSession;
use App\Models\PanelAuditLog;
use App\Models\RemoteCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MassLogoutTest extends TestCase
{
    use RefreshDatabase;

    private function code(User $user): string
    {
        return (new Google2FA)->getCurrentOtp($user->two_factor_secret);
    }

    public function test_only_superadmins_see_and_use_mass_logout()
    {
        Application::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/sessions')->assertInertia(fn (Assert $page) => $page->where('massLogoutApps', []));
        $this->post('/sessions/logout-all', ['applications' => [1]])->assertForbidden();
    }

    public function test_static_sites_are_not_offered()
    {
        Application::factory()->create(['name' => 'Adenar']);
        Application::factory()->static()->create(['name' => 'Web principal']);

        $this->actingAs(User::factory()->superadmin()->create())->get('/sessions')
            ->assertInertia(fn (Assert $page) => $page->has('massLogoutApps', 1)->where('massLogoutApps.0.name', 'Adenar'));
    }

    public function test_mass_logout_requires_password_and_code()
    {
        Http::fake();
        $app = Application::factory()->create();
        $user = User::factory()->superadmin()->create();

        $this->actingAs($user)->post('/sessions/logout-all', [
            'applications' => [$app->id], 'reason' => 'Prueba de cierre', 'password' => 'mala', 'code' => $this->code($user),
        ])->assertSessionHasErrors('password');

        $this->post('/sessions/logout-all', [
            'applications' => [$app->id], 'reason' => 'Prueba de cierre', 'password' => 'password', 'code' => '000000',
        ])->assertSessionHasErrors('code');

        Http::assertNothingSent();
    }

    public function test_mass_logout_sends_the_command_to_every_selected_app()
    {
        $adenar = Application::factory()->create(['name' => 'Adenar', 'url' => 'https://adenar.test']);
        $easy = Application::factory()->create(['name' => 'EasyOL', 'url' => 'https://easy.test']);
        $tickets = Application::factory()->create(['name' => 'Tickets', 'url' => 'https://tickets.test']);
        AppSession::create(['application_id' => $adenar->id, 'external_user_id' => '1', 'last_activity_at' => now()]);
        AppSession::create(['application_id' => $tickets->id, 'external_user_id' => '2', 'last_activity_at' => now()]);

        Http::fake([
            'adenar.test/nexus/commands' => Http::response(['ok' => true, 'message' => 'Se cerraron 12 sesiones.']),
            'easy.test/nexus/commands' => Http::response(['ok' => true, 'message' => 'Se cerraron 3 sesiones.']),
        ]);

        $user = User::factory()->superadmin()->create();

        $this->actingAs($user)->post('/sessions/logout-all', [
            'applications' => [$adenar->id, $easy->id], 'reason' => 'Posible robo de credenciales', 'password' => 'password', 'code' => $this->code($user),
        ])->assertSessionHas('success');

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request['type'] === 'logout_all' && $request['reason'] === 'Posible robo de credenciales');
        $this->assertSame(2, RemoteCommand::where('type', 'logout_all')->where('status', 'done')->count());
        $this->assertDatabaseMissing(AppSession::class, ['application_id' => $adenar->id]);
        $this->assertDatabaseHas(AppSession::class, ['application_id' => $tickets->id]);
        $this->assertDatabaseHas(PanelAuditLog::class, ['action' => 'remote.logout_all_bulk']);
    }

    public function test_reports_apps_that_did_not_respond()
    {
        $app = Application::factory()->create(['name' => 'Adenar', 'url' => 'https://adenar.test']);
        Http::fake(['adenar.test/*' => Http::response('caído', 502)]);
        $user = User::factory()->superadmin()->create();

        $this->actingAs($user)->post('/sessions/logout-all', [
            'applications' => [$app->id], 'reason' => 'Posible robo de credenciales', 'password' => 'password', 'code' => $this->code($user),
        ])->assertSessionHas('error');
    }
}
