<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Models\UserLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserLocationTest extends TestCase
{
    use RefreshDatabase;

    private function appWithKey(array $attributes = []): array
    {
        $app = Application::factory()->make($attributes);
        $key = $app->regenerateApiKey();
        $app->save();

        return [$app, $key];
    }

    public function test_the_agent_reports_user_locations()
    {
        [$app, $key] = $this->appWithKey(['name' => 'Adenar']);

        $this->withToken($key)->postJson('/api/v1/ingest', [
            'locations' => [
                ['user_id' => '7', 'user_name' => 'Ana', 'source' => 'gps', 'latitude' => 0.8301, 'longitude' => -77.6444, 'accuracy' => 18.4, 'city' => 'Ipiales', 'region' => 'Nariño', 'country' => 'Colombia', 'ip' => '181.50.1.2', 'user_agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/120', 'occurred_at' => now()->toIso8601String()],
                ['user_id' => '8', 'user_name' => 'Luis', 'source' => 'ip', 'city' => 'Pasto', 'region' => 'Nariño', 'country' => 'Colombia', 'latitude' => 1.2136, 'longitude' => -77.2811, 'occurred_at' => now()->toIso8601String()],
            ],
        ])->assertOk()->assertJsonPath('stored.locations', 2);

        $ana = UserLocation::where('external_user_id', '7')->sole();
        $this->assertSame([$app->id, 'gps', 18, 'Ipiales'], [$ana->application_id, $ana->source, $ana->accuracy_m, $ana->city]);
        $this->assertSame('Ipiales, Nariño, Colombia', $ana->place());

        $this->withToken($key)->postJson('/api/v1/ingest', [
            'locations' => [['source' => 'satelite', 'occurred_at' => now()->toIso8601String()]],
        ])->assertUnprocessable();
    }

    public function test_the_map_shows_the_latest_location_of_each_user_in_the_period()
    {
        $app = Application::factory()->create(['name' => 'Adenar']);
        $admin = User::factory()->superadmin()->create();

        UserLocation::create(['application_id' => $app->id, 'external_user_id' => '7', 'user_name' => 'Ana', 'source' => 'gps', 'latitude' => 1.21, 'longitude' => -77.28, 'city' => 'Pasto', 'recorded_at' => now()->subMinutes(12)]);
        UserLocation::create(['application_id' => $app->id, 'external_user_id' => '7', 'user_name' => 'Ana', 'source' => 'gps', 'latitude' => 0.83, 'longitude' => -77.64, 'city' => 'Ipiales', 'recorded_at' => now()->subMinutes(2)]);
        UserLocation::create(['application_id' => $app->id, 'external_user_id' => '9', 'user_name' => 'Viejo', 'source' => 'ip', 'city' => 'Cali', 'recorded_at' => now()->subHours(5)]);

        $this->actingAs($admin)->get('/map')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('locations/map')
            ->has('points', 1)
            ->where('points.0.city', 'Ipiales')
            ->where('stats.gps', 1));

        $this->actingAs($admin)->get('/map?period=24h')->assertInertia(fn (Assert $page) => $page
            ->has('points', 2)
            ->where('stats.cities', 2));
    }

    public function test_bosses_only_see_users_of_their_assigned_apps()
    {
        $mine = Application::factory()->create();
        $other = Application::factory()->create();
        $jefe = User::factory()->jefe()->create();
        $jefe->applications()->attach($mine);

        UserLocation::create(['application_id' => $mine->id, 'external_user_id' => '1', 'source' => 'gps', 'latitude' => 1.2, 'longitude' => -77.2, 'recorded_at' => now()]);
        UserLocation::create(['application_id' => $other->id, 'external_user_id' => '2', 'source' => 'gps', 'latitude' => 1.2, 'longitude' => -77.2, 'recorded_at' => now()]);

        $this->actingAs($jefe)->get('/map')->assertInertia(fn (Assert $page) => $page
            ->has('points', 1)
            ->where('points.0.application.id', $mine->id));
    }

    public function test_old_locations_are_pruned()
    {
        $app = Application::factory()->create();
        UserLocation::create(['application_id' => $app->id, 'source' => 'ip', 'recorded_at' => now()->subDays(120)]);
        UserLocation::create(['application_id' => $app->id, 'source' => 'ip', 'recorded_at' => now()->subDays(10)]);

        $this->artisan('nexus:prune')->assertSuccessful();

        $this->assertSame(1, UserLocation::count());
    }
}
