<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Los anuncios se guardan aquí como JSON y no caben en VARCHAR(255).
        Schema::table('nexus_state', function (Blueprint $table) {
            $table->longText('value')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('nexus_state', function (Blueprint $table) {
            $table->string('value')->nullable()->change();
        });
    }
};
