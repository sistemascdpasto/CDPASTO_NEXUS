<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Módulo «Ubicación obligatoria»: si la app exige permitir la ubicación para usarla.
        Schema::table('applications', function (Blueprint $table) {
            $table->boolean('location_required')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('location_required');
        });
    }
};
