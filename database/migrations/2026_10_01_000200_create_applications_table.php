<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type', 20)->default('laravel'); // laravel | static
            $table->string('url');
            $table->string('health_path')->nullable();
            $table->string('environment', 30)->default('production');
            $table->string('railway_service_id')->nullable()->index();
            $table->string('railway_service_name')->nullable();
            $table->string('repository')->nullable();
            $table->text('api_key'); // cifrada: se usa para firmar llamadas al agente
            $table->string('api_key_hash', 64)->unique(); // sha256: búsqueda al recibir datos
            $table->string('api_key_prefix', 12);
            $table->unsignedSmallInteger('check_interval_minutes')->default(1);
            $table->unsignedInteger('slow_threshold_ms')->default(3000);
            $table->unsignedInteger('error_threshold')->default(20);
            $table->unsignedInteger('failed_login_threshold')->default(10);
            $table->unsignedInteger('mass_delete_threshold')->default(30);
            $table->boolean('is_active')->default(true);
            $table->string('status', 20)->default('unknown');
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->unsignedInteger('last_response_ms')->nullable();
            $table->json('last_components')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('last_ingest_at')->nullable();
            $table->string('agent_version', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('application_user', function (Blueprint $table) {
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['application_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_user');
        Schema::dropIfExists('applications');
    }
};
