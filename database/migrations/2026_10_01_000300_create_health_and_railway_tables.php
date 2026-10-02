<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_ms')->nullable();
            $table->json('components')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('checked_at');
            $table->index(['application_id', 'checked_at']);
        });

        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('railway_id')->unique();
            $table->string('status', 30);
            $table->string('commit_hash', 64)->nullable();
            $table->string('commit_message', 500)->nullable();
            $table->string('commit_author')->nullable();
            $table->string('branch')->nullable();
            $table->timestamp('deployed_at');
            $table->timestamps();
            $table->index(['application_id', 'deployed_at']);
        });

        Schema::create('resource_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->timestamp('measured_at');
            $table->decimal('cpu', 10, 4)->nullable();
            $table->decimal('memory_gb', 10, 4)->nullable();
            $table->decimal('network_rx_gb', 12, 6)->nullable();
            $table->decimal('network_tx_gb', 12, 6)->nullable();
            $table->unique(['application_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_metrics');
        Schema::dropIfExists('deployments');
        Schema::dropIfExists('health_checks');
    }
};
