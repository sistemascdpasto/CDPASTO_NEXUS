<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ubicación de los usuarios de cada app: al iniciar sesión y cada 10 minutos.
        Schema::create('user_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('external_user_id', 64)->nullable();
            $table->string('user_name')->nullable();
            // gps: el navegador la entregó con permiso del usuario; ip: aproximada por la IP.
            $table->string('source', 10);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('accuracy_m')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('device', 120)->nullable();
            $table->timestamp('recorded_at');
            $table->index(['application_id', 'external_user_id', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_locations');
    }
};
