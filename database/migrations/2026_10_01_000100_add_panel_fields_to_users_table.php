<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('lector')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
            $table->string('phone', 30)->nullable()->after('is_active');
            $table->boolean('receive_alerts')->default(true)->after('phone');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'is_active', 'phone', 'receive_alerts', 'two_factor_secret',
                'two_factor_recovery_codes', 'two_factor_confirmed_at', 'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
