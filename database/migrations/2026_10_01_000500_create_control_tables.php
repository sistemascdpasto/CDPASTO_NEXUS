<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // logout | block | unblock
            $table->string('external_user_id', 64);
            $table->string('target_name')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending'); // pending | done | failed
            $table->string('response', 500)->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('severity', 10);
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['application_id', 'type', 'created_at']);
        });

        Schema::create('panel_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('description', 500);
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_audit_logs');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('remote_commands');
    }
};
