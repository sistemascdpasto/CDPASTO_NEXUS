<?php

namespace Tests\Feature\Auth;

use App\Models\PanelAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $this->get('/login')->assertOk();
    }

    public function test_public_registration_is_disabled()
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_valid_credentials_lead_to_the_two_factor_challenge_without_logging_in()
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
    }

    public function test_a_valid_totp_code_completes_the_login()
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $code = (new Google2FA)->getCurrentOtp($user->two_factor_secret);

        $this->post('/two-factor-challenge', ['code' => $code])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas(PanelAuditLog::class, ['user_id' => $user->id, 'action' => 'auth.login']);
    }

    public function test_an_invalid_totp_code_is_rejected()
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_a_recovery_code_works_only_once()
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => 'abcde-fghij'])->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => 'abcde-fghij'])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_users_without_two_factor_are_forced_to_configure_it()
    {
        $user = User::factory()->withoutTwoFactor()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);

        $this->get('/dashboard')->assertRedirect(route('two-factor.setup'));
        $this->get('/two-factor/setup')->assertOk();
    }

    public function test_two_factor_setup_can_be_confirmed()
    {
        $user = User::factory()->withoutTwoFactor()->create();
        $this->actingAs($user)->get('/two-factor/setup');

        $secret = session('two_factor.pending');
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->post('/two-factor/confirm', ['code' => $code])->assertRedirect(route('two-factor.setup'));

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->get('/dashboard')->assertOk();
    }

    public function test_inactive_users_can_not_log_in()
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $this->assertGuest();
        $this->assertDatabaseHas(PanelAuditLog::class, ['action' => 'auth.failed']);
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }
}
