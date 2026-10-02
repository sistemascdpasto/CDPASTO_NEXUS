<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eventos pendientes de enviar a Nexus (peticiones, errores, logins, auditoría).
        Schema::create('nexus_buffer', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Usuarios bloqueados desde el panel Nexus.
        Schema::create('nexus_blocks', function (Blueprint $table) {
            $table->string('user_id', 64)->primary();
            $table->timestamp('blocked_until')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_blocks');
        Schema::dropIfExists('nexus_buffer');
    }
};
