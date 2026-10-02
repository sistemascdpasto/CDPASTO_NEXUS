<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Incidentes: una caída confirmada abre uno; la recuperación lo cierra (MTTR, historial, causa raíz).
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('cause', 500)->nullable();
            $table->unsignedInteger('failed_checks')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('notes_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['application_id', 'started_at']);
        });

        // Las métricas de recursos ahora también incluyen el servicio de base de datos de cada app (costos).
        // El índice nuevo se crea antes de borrar el viejo: la FK de application_id necesita uno de los dos.
        Schema::table('resource_metrics', function (Blueprint $table) {
            $table->string('service_kind', 10)->default('app')->after('application_id');
            $table->unique(['application_id', 'service_kind', 'measured_at']);
        });

        Schema::table('resource_metrics', function (Blueprint $table) {
            $table->dropUnique(['application_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::table('resource_metrics', function (Blueprint $table) {
            $table->unique(['application_id', 'measured_at']);
        });

        Schema::table('resource_metrics', function (Blueprint $table) {
            $table->dropUnique(['application_id', 'service_kind', 'measured_at']);
            $table->dropColumn('service_kind');
        });

        Schema::dropIfExists('incidents');
    }
};
