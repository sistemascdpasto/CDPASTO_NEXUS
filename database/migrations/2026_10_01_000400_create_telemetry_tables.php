<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Peticiones agregadas por minuto y ruta, enviadas por el agente.
        Schema::create('request_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->timestamp('bucket');
            $table->string('method', 10);
            $table->string('route', 255);
            $table->unsignedInteger('count');
            $table->unsignedBigInteger('total_ms');
            $table->unsignedInteger('max_ms');
            $table->unsignedInteger('errors_4xx')->default(0);
            $table->unsignedInteger('errors_5xx')->default(0);
            $table->index(['application_id', 'bucket']);
        });

        Schema::create('error_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->string('exception_class');
            $table->text('message');
            $table->string('file', 500)->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->unsignedInteger('occurrences')->default(0);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['application_id', 'fingerprint']);
            $table->index(['application_id', 'last_seen_at']);
        });

        Schema::create('error_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('error_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('external_user_id', 64)->nullable();
            $table->string('user_name')->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('trace')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['application_id', 'occurred_at']);
            $table->index(['error_group_id', 'occurred_at']);
        });

        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('external_user_id', 64)->nullable();
            $table->string('identifier')->nullable();
            $table->string('user_name')->nullable();
            $table->string('event', 20); // login | failed | logout | lockout
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('device', 100)->nullable();
            $table->timestamp('occurred_at');
            $table->index(['application_id', 'occurred_at']);
            $table->index(['application_id', 'event', 'occurred_at']);
        });

        // Fotografía de las sesiones activas de cada app (se reemplaza en cada envío).
        Schema::create('app_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('external_user_id', 64);
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_role')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('device', 100)->nullable();
            $table->timestamp('login_at')->nullable();
            $table->timestamp('last_activity_at');
            $table->index(['application_id', 'last_activity_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('external_user_id', 64)->nullable();
            $table->string('user_name')->nullable();
            $table->string('action', 30);
            $table->string('module', 150);
            $table->string('record_id', 64)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('url', 1000)->nullable();
            $table->timestamp('occurred_at');
            $table->index(['application_id', 'occurred_at']);
            $table->index(['application_id', 'action', 'occurred_at']);
            $table->index(['external_user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('app_sessions');
        Schema::dropIfExists('login_events');
        Schema::dropIfExists('error_events');
        Schema::dropIfExists('error_groups');
        Schema::dropIfExists('request_metrics');
    }
};
