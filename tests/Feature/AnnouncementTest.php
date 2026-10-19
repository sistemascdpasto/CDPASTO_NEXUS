<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AnnouncementStat;
use App\Models\Application;
use App\Models\PanelAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://nexus.example']);
    }

    private function appWithKey(array $attributes = []): array
    {
        $app = Application::factory()->make($attributes);
        $key = $app->regenerateApiKey();
        $app->save();

        return [$app, $key];
    }

    private function announcement(array $apps, array $attributes = []): Announcement
    {
        $announcement = new Announcement(['title' => 'Regreso seguro a casa', ...$attributes]);
        $announcement->setImage(UploadedFile::fake()->image('banner.png', 1600, 900)->get(), 'image/png');
        $announcement->save();
        $announcement->applications()->sync(collect($apps)->pluck('id'));

        return $announcement;
    }

    public function test_only_superadmins_manage_announcements()
    {
        $jefe = User::factory()->jefe()->create();

        $this->actingAs($jefe)->get('/announcements')->assertForbidden();
        $this->actingAs($jefe)->post('/announcements', [])->assertForbidden();
    }

    public function test_superadmin_publishes_an_announcement_for_login_apps_and_the_website()
    {
        $admin = User::factory()->superadmin()->create();
        $adenar = Application::factory()->create(['name' => 'Adenar']);
        $web = Application::factory()->static()->create(['slug' => 'bavaria-cdpasto']);
        $reempaque = Application::factory()->static()->create(['slug' => 'reempaque']);

        // Una web estática que no está habilitada para anuncios no se puede elegir.
        $this->actingAs($admin)->post('/announcements', [
            'title' => 'Regreso seguro a casa',
            'image' => UploadedFile::fake()->image('banner.jpg', 1600, 900),
            'application_ids' => [$adenar->id, $reempaque->id],
        ])->assertSessionHasErrors('application_ids.1');

        $this->actingAs($admin)->post('/announcements', [
            'title' => 'Regreso seguro a casa',
            'image' => UploadedFile::fake()->image('banner.jpg', 1600, 900),
            'link_url' => 'https://cdpasto.example/regreso-seguro',
            'link_label' => 'Conoce la campaña',
            'application_ids' => [$adenar->id, $web->id],
        ])->assertRedirect('/announcements');

        $announcement = Announcement::sole();
        $this->assertSame(1600, $announcement->image_width);
        $this->assertEqualsCanonicalizing([$adenar->id, $web->id], $announcement->applications()->pluck('applications.id')->all());
        $this->assertDatabaseHas(PanelAuditLog::class, ['action' => 'announcement.created']);

        $this->actingAs($admin)->get('/announcements')->assertOk();
        $this->actingAs($admin)->get("/announcements/{$announcement->id}/edit")->assertOk();
    }

    public function test_link_must_be_http()
    {
        $admin = User::factory()->superadmin()->create();
        $app = Application::factory()->create();

        $this->actingAs($admin)->post('/announcements', [
            'title' => 'X',
            'image' => UploadedFile::fake()->image('a.png'),
            'link_url' => 'javascript:alert(1)',
            'application_ids' => [$app->id],
        ])->assertSessionHasErrors('link_url');
    }

    public function test_the_agent_receives_only_live_announcements_for_its_app_with_public_urls()
    {
        [$adenar, $key] = $this->appWithKey(['slug' => 'adenar']);
        [$tickets] = $this->appWithKey(['slug' => 'tickets']);

        $vigente = $this->announcement([$adenar], ['title' => 'Vigente']);
        $this->announcement([$adenar], ['title' => 'Programado', 'starts_at' => now()->addDay()]);
        $this->announcement([$adenar], ['title' => 'Vencido', 'ends_at' => now()->subDay()]);
        $this->announcement([$adenar], ['title' => 'Pausado', 'is_active' => false]);
        $this->announcement([$tickets], ['title' => 'De otra app']);

        $response = $this->withToken($key)->postJson('/api/v1/ingest', [])->assertOk();

        $response->assertJsonPath('announcements.app', 'adenar')
            ->assertJsonCount(1, 'announcements.items')
            ->assertJsonPath('announcements.items.0.title', 'Vigente');

        // URLs con el dominio público (el agente llama por la red privada; las abre el navegador).
        $this->assertStringStartsWith('https://nexus.example/api/anuncios/'.$vigente->uuid.'/imagen', $response->json('announcements.items.0.image_url'));
        $this->assertStringStartsWith('https://nexus.example/anuncios/embed.js', $response->json('announcements.embed_url'));
    }

    public function test_public_endpoints_serve_the_image_the_website_feed_and_count_events()
    {
        $web = Application::factory()->static()->create(['slug' => 'bavaria-cdpasto']);
        $otra = Application::factory()->create(['slug' => 'adenar']);
        $announcement = $this->announcement([$web]);

        $image = $this->get("/api/anuncios/{$announcement->uuid}/imagen");
        $image->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame(base64_decode($announcement->image_data), $image->getContent());

        $this->getJson('/api/anuncios/publicos?app=bavaria-cdpasto')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertJsonPath('data.0.id', $announcement->uuid);
        $this->getJson('/api/anuncios/publicos?app=no-existe')->assertOk()->assertJsonCount(0, 'data');

        $this->post("/api/anuncios/{$announcement->uuid}/evento?app=bavaria-cdpasto&type=view")->assertNoContent();
        $this->post("/api/anuncios/{$announcement->uuid}/evento?app=bavaria-cdpasto&type=view")->assertNoContent();
        $this->post("/api/anuncios/{$announcement->uuid}/evento?app=bavaria-cdpasto&type=click")->assertNoContent();
        // Una app a la que no va dirigido el anuncio no suma.
        $this->post("/api/anuncios/{$announcement->uuid}/evento?app=adenar&type=view")->assertNoContent();

        $stat = AnnouncementStat::sole();
        $this->assertSame([2, 1, $web->id], [$stat->views, $stat->clicks, $stat->application_id]);
        $this->assertNotSame($otra->id, $stat->application_id);
    }
}
