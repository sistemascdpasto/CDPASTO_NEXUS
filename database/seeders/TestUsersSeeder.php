<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuarios de prueba para desarrollo local. DatabaseSeeder solo lo ejecuta en APP_ENV=local.
 */
class TestUsersSeeder extends Seeder
{
    public const PASSWORD = 'Nexus12345';

    public function run(): void
    {
        $users = [
            ['name' => 'Admin Prueba', 'email' => 'admin@nexus.test', 'role' => UserRole::Superadmin, 'apps' => []],
            ['name' => 'Jefe Prueba', 'email' => 'jefe@nexus.test', 'role' => UserRole::Jefe, 'apps' => ['adenar', 'easyol']],
            ['name' => 'Lector Prueba', 'email' => 'lector@nexus.test', 'role' => UserRole::Lector, 'apps' => ['tickets', 'reempaque']],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(['email' => $data['email']], [
                'name' => $data['name'],
                'password' => self::PASSWORD,
                'role' => $data['role'],
                'is_active' => true,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->applications()->sync(Application::whereIn('slug', $data['apps'])->pluck('id'));

            $this->command?->info("{$data['email']} / ".self::PASSWORD." ({$data['role']->label()})");
        }
    }
}
