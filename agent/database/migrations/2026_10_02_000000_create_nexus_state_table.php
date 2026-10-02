<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Estado compartido entre servicios (web y scheduler), p. ej. el latido del scheduler.
        // Va en la BD y no en caché porque cada servicio puede tener un prefijo de caché distinto.
        Schema::create('nexus_state', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->string('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_state');
    }
};
