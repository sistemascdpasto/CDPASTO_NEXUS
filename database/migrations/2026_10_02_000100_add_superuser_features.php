<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Servicio MySQL de Railway de la app: las credenciales se leen en vivo desde la API de Railway.
            $table->string('database_service_id')->nullable()->after('railway_service_name');
            $table->string('database_service_name')->nullable()->after('database_service_id');
            // Ficha técnica reportada por el agente (/nexus/info).
            $table->json('last_info')->nullable()->after('last_components');
            $table->timestamp('last_info_at')->nullable()->after('last_info');
        });

        // Actividad por usuario y hora: cuántas peticiones y qué rutas usó.
        Schema::create('user_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('external_user_id', 64);
            $table->string('user_name')->nullable();
            $table->timestamp('bucket');
            $table->unsignedInteger('requests');
            $table->json('routes')->nullable();
            $table->index(['application_id', 'bucket']);
            $table->index(['application_id', 'external_user_id', 'bucket']);
        });

        // Comandos de nivel app (mantenimiento, caché…) no tienen usuario destino.
        Schema::table('remote_commands', function (Blueprint $table) {
            $table->string('external_user_id', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity');

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['database_service_id', 'database_service_name', 'last_info', 'last_info_at']);
        });
    }
};
