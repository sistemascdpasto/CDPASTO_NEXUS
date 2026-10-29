<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\PanelAuditLog;
use App\Models\RemoteCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class LocationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_superadmins_manage_the_policy()
    {
        $app = Application::factory()->create();

        $this->actingAs(User::factory()->create())->get('/location-policy')->assertForbidden();
        $this->put("/location-policy/{$app->id}", ['required' => true])->assertForbidden();
    }

    public function test_lists_only_apps_with_login()
    {
        Application::factory()->create(['name' => 'Adenar']);
        Application::factory()->static()->create(['name' => 'Web principal']);

        $this->actingAs(User::factory()->superadmin()->create())->get('/location-policy')
            ->assertInertia(fn (Assert $page) => $page->has('apps', 1)->where('apps.0.name', 'Adenar')->where('apps.0.location_required', true));
    }

    public function test_disabling_requires_reason_password_and_code()
    {
        Http::fake();
        $app = Application::factory()->create();
        $user = User::factory()->superadmin()->create();

        $this->actingAs($user)->put("/location-policy/{$app->id}", ['required' => false])
            ->assertSessionHasErrors(['reason', 'password', 'code']);

        $this->put("/location-policy/{$app->id}", ['required' => false, 'reason' => 'Equipos sin GPS', 'password' => 'mala', 'code' => '000000'])
            ->assertSessionHasErrors('password');

        $this->assertTrue($app->fresh()->location_required);
        Http::assertNothingSent();
    }

    public function test_disabling_sends_the_command_and_is_audited()
    {
        $app = Application::factory()->create(['url' => 'https://adenar.test']);
        Http::fake(['adenar.test/nexus/commands' => Http::response(['ok' => true, 'message' => 'Ubicación obligatoria desactivada.'])]);
        $user = User::factory()->superadmin()->create();

        $this->actingAs($user)->put("/location-policy/{$app->id}", [
            'required' => false, 'reason' => 'Equipos sin GPS', 'password' => 'password', 'code' => (new Google2FA)->getCurrentOtp($user->two_factor_secret),
        ])->assertSessionHas('success');

        $this->assertFalse($app->fresh()->location_required);
        Http::assertSent(fn (Request $request) => $request['type'] === 'location_off');
        $this->assertSame(1, RemoteCommand::where('type', 'location_off')->where('status', 'done')->count());
        $this->assertDatabaseHas(PanelAuditLog::class, ['action' => 'remote.location_off']);
    }

    public function test_enabling_does_not_ask_for_the_code_and_survives_an_offline_app()
    {
        $app = Application::factory()->create(['url' => 'https://adenar.test', 'location_required' => false]);
        Http::fake(['adenar.test/*' => Http::response('caído', 502)]);

        $this->actingAs(User::factory()->superadmin()->create())->put("/location-policy/{$app->id}", ['required' => true])
            ->assertSessionHas('success');

        // Queda guardado: la app lo recibe en su próxima ingesta.
        $this->assertTrue($app->fresh()->location_required);
    }

    public function test_the_ingest_response_carries_the_policy()
    {
        $app = Application::factory()->make(['location_required' => false]);
        $key = $app->regenerateApiKey();
        $app->save();

        $this->withToken($key)->postJson('/api/v1/ingest', ['sent_at' => now()->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('policy.location_required', false);
    }
}
