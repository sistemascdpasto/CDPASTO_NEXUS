<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Botón de emergencia: desconexión de los servicios de Railway ante un ataque.
        Schema::create('emergency_lockdowns', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('active'); // active | released
            $table->text('reason');
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            // Por servicio: id, nombre, despliegue detenido (para volver a levantarlo), hora, error.
            $table->json('services');
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_lockdowns');
    }
};
