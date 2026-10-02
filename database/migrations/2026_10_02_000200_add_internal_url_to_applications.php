<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // URL por la red privada de Railway (http://<servicio>.railway.internal:8080). Desde dentro de
            // Railway la URL pública falla de forma intermitente (hairpin), por eso Nexus usa esta.
            $table->string('internal_url')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('internal_url');
        });
    }
};
