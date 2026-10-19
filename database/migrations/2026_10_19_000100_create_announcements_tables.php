<?php

use App\Models\Application;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Anuncios (banners) que se muestran al iniciar sesión en las apps y en la web principal.
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 150);
            // La imagen vive en la base: Nexus no tiene disco persistente en Railway.
            $table->longText('image_data');
            $table->string('image_mime', 40);
            $table->unsignedInteger('image_size');
            $table->unsignedSmallInteger('image_width')->nullable();
            $table->unsignedSmallInteger('image_height')->nullable();
            $table->string('link_url', 500)->nullable();
            $table->string('link_label', 60)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('announcement_application', function (Blueprint $table) {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->primary(['announcement_id', 'application_id']);
        });

        // Vistas y clics por día y por app.
        Schema::create('announcement_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unique(['announcement_id', 'application_id', 'date'], 'announcement_stats_unique');
        });

        // La página web principal (Bavaria_CDPASTO) pasa a ser monitoreada y recibe anuncios.
        // (En las pruebas no: cada prueba crea las apps que necesita.)
        if (! app()->runningUnitTests() && ! Application::where('slug', 'bavaria-cdpasto')->exists()) {
            $app = new Application([
                'name' => 'Web principal CD Pasto',
                'slug' => 'bavaria-cdpasto',
                'description' => 'Página web principal (Bavaria_CDPASTO): mapa del CD, sistemas e inducción SST.',
                'type' => Application::TYPE_STATIC,
                'url' => 'https://bavariacdpasto.up.railway.app',
                'railway_service_id' => '256de537-6b69-4809-a46c-d547fc2b5df7',
                'railway_service_name' => 'Bavaria_CDPASTO',
                'repository' => 'sistemascdpasto/Bavaria_CDPASTO',
                'check_interval_minutes' => 5,
            ]);
            $app->regenerateApiKey();
            $app->save();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_stats');
        Schema::dropIfExists('announcement_application');
        Schema::dropIfExists('announcements');
    }
};
