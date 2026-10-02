<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_every_page_renders_for_a_superadmin()
    {
        $this->actingAs(User::factory()->superadmin()->create());
        $app = Application::factory()->create();

        foreach (['/dashboard', '/applications', "/applications/{$app->id}", "/applications/{$app->id}/edit", "/applications/{$app->id}/users", "/applications/{$app->id}/users/1", '/applications/create', '/errors', '/sessions', '/logins', '/audit', '/alerts', '/users', '/users/create', '/panel-audit', '/settings/two-factor'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
