<?php

namespace Tests\Feature;

use App\Enums\AppStatus;
use App\Mail\WeeklyReport;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\LoginEvent;
use App\Models\ResourceMetric;
use App\Models\User;
use App\Services\HealthChecker;
use App\Services\HealthScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ControlCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_is_public_and_only_exposes_status()
    {
        Application::factory()->create(['name' => 'Adenar', 'url' => 'https://secreto-interno.test', 'status' => AppStatus::Online]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->where('systems.0.name', 'Adenar')
                ->missing('systems.0.url')
                ->where('isAuthenticated', false));
    }

    public function test_a_confirmed_outage_opens_an_incident_that_closes_on_recovery()
    {
        Notification::fake();
        $app = Application::factory()->static()->create(['url' => 'https://reempaque.test']);
        $checker = app(HealthChecker::class);

        Http::fakeSequence('reempaque.test/*')->push('x', 502)->push('x', 502)->push('ok', 200);

        $checker->check($app);
        $this->assertSame(0, Incident::count(), 'Un solo fallo no abre incidente');

        $checker->check($app->fresh());
        $incident = Incident::sole();
        $this->assertNull($incident->resolved_at);
        $this->assertSame('HTTP 502', $incident->cause);

        $this->travel(3)->minutes();
        $checker->check($app->fresh());

        $incident->refresh();
        $this->assertNotNull($incident->resolved_at);
        $this->assertGreaterThanOrEqual(180, $incident->duration_seconds);
        $this->assertSame(2, $incident->failed_checks);
    }

    public function test_jefe_documents_the_root_cause_of_an_incident()
    {
        $app = Application::factory()->create();
        $jefe = User::factory()->jefe()->create();
        $jefe->applications()->attach($app);
        $incident = Incident::create(['application_id' => $app->id, 'started_at' => now()->subHour(), 'resolved_at' => now(), 'duration_seconds' => 3600]);

        $this->actingAs($jefe)->patch("/incidents/{$incident->id}", ['notes' => 'Se llenó el disco del volumen'])->assertSessionHas('success');

        $this->assertSame('Se llenó el disco del volumen', $incident->fresh()->notes);
        $this->actingAs($jefe)->get('/incidents')->assertInertia(fn (Assert $page) => $page->where('summary.count_30d', 1)->has('incidents.data', 1));
    }

    public function test_health_score_penalizes_an_app_that_is_down()
    {
        $healthy = Application::factory()->static()->create(['last_response_ms' => 120]);
        $down = Application::factory()->static()->create(['last_response_ms' => 120, 'status' => AppStatus::Down]);
        Incident::create(['application_id' => $down->id, 'started_at' => now()->subMinutes(5)]);

        $scores = app(HealthScore::class)->forApplications(Application::all());

        $this->assertGreaterThan(50, $scores[$healthy->id]['score']);
        $this->assertLessThanOrEqual(30, $scores[$down->id]['score']);
    }

    public function test_global_search_finds_people_and_apps_within_scope()
    {
        [$mine, $other] = Application::factory()->count(2)->create();
        $user = User::factory()->create();
        $user->applications()->attach($mine);
        LoginEvent::create(['application_id' => $mine->id, 'external_user_id' => '7', 'user_name' => 'Carolina Pérez', 'event' => 'login', 'occurred_at' => now()]);
        LoginEvent::create(['application_id' => $other->id, 'external_user_id' => '8', 'user_name' => 'Carolina Ruiz', 'event' => 'login', 'occurred_at' => now()]);

        $this->actingAs($user)->getJson('/search?q=Carolina')
            ->assertOk()
            ->assertJsonCount(1, 'people')
            ->assertJsonPath('people.0.name', 'Carolina Pérez')
            ->assertJsonPath('people.0.href', "/applications/{$mine->id}/users/7");
    }

    public function test_costs_are_estimated_from_railway_metrics_for_superadmins_only()
    {
        $app = Application::factory()->create(['railway_service_id' => 'svc', 'database_service_id' => 'db']);
        ResourceMetric::insert([
            ['application_id' => $app->id, 'service_kind' => 'app', 'measured_at' => now()->startOfMonth()->addHour(), 'cpu' => 0.5, 'memory_gb' => 1, 'network_rx_gb' => 0, 'network_tx_gb' => 0],
            ['application_id' => $app->id, 'service_kind' => 'database', 'measured_at' => now()->startOfMonth()->addHour(), 'cpu' => 0.1, 'memory_gb' => 0.5, 'network_rx_gb' => 0, 'network_tx_gb' => 0],
        ]);

        // 0.5 vCPU × 20 + 1 GB × 10 = 20 USD (app) · 0.1 × 20 + 0.5 × 10 = 7 USD (BD)
        $this->actingAs(User::factory()->superadmin()->create())->get('/costs')
            ->assertInertia(fn (Assert $page) => $page->where('total_projected', 27)->has('services', 2));

        $this->actingAs(User::factory()->jefe()->create())->get('/costs')->assertForbidden();
    }

    public function test_noc_and_activity_pages_render_with_feed()
    {
        $app = Application::factory()->create();
        AuditLog::create(['application_id' => $app->id, 'external_user_id' => '3', 'user_name' => 'Ana', 'action' => 'uploaded', 'module' => 'Flota / Documentos', 'new_values' => ['archivos' => [['nombre' => 'soat.pdf']]], 'occurred_at' => now()]);

        $this->actingAs(User::factory()->superadmin()->create());

        $this->get('/noc')->assertOk()->assertInertia(fn (Assert $page) => $page->has('systems', 1)->where('feed.0.title', 'Ana subió «soat.pdf»'));
        $this->get('/activity')->assertOk()->assertInertia(fn (Assert $page) => $page->where('items.0.kind', 'audit'));
        $this->get('/activity?kinds[0]=error')->assertInertia(fn (Assert $page) => $page->has('items', 0));
    }

    public function test_weekly_report_is_sent_to_responsibles()
    {
        Mail::fake();
        $app = Application::factory()->create();
        $jefe = User::factory()->jefe()->create();
        $jefe->applications()->attach($app);
        User::factory()->jefe()->create(); // sin apps: no recibe
        User::factory()->superadmin()->create(['receive_alerts' => false]); // alertas apagadas: no recibe

        $this->artisan('nexus:weekly-report')->assertSuccessful();

        Mail::assertSent(WeeklyReport::class, 1);
        Mail::assertSent(WeeklyReport::class, fn (WeeklyReport $mail) => $mail->hasTo($jefe->email) && $mail->rows[0]['name'] === $app->name);
    }
}
