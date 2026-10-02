<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateSuperadmin extends Command
{
    protected $signature = 'nexus:superadmin {--email=} {--name=} {--password= : Solo para uso no interactivo (despliegues)}';

    protected $description = 'Crea (o promueve) un usuario superadministrador del panel';

    public function handle(): int
    {
        $email = $this->option('email') ?: text('Correo', required: true);
        $name = $this->option('name') ?: text('Nombre', required: true);

        $user = User::firstWhere('email', $email);

        if (! $user) {
            $pass = $this->option('password') ?: password('Contraseña (mín. 10, mayúsculas, minúsculas y números)', required: true);

            $validator = Validator::make(['password' => $pass], ['password' => [Password::min(10)->mixedCase()->numbers()]]);

            if ($validator->fails()) {
                $this->error($validator->errors()->first());

                return self::FAILURE;
            }

            $user = new User(['email' => $email, 'name' => $name, 'password' => $pass]);
            $user->email_verified_at = now();
        }

        $user->fill(['role' => UserRole::Superadmin, 'is_active' => true])->save();

        $this->info("{$user->email} es superadministrador. Configurará el doble factor en su primer ingreso.");

        return self::SUCCESS;
    }
}
