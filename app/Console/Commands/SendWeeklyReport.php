<?php

namespace App\Console\Commands;

use App\Mail\WeeklyReport;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendWeeklyReport extends Command
{
    protected $signature = 'nexus:weekly-report {--email= : Enviar solo a este usuario (prueba)}';

    protected $description = 'Envía el resumen semanal a los responsables de cada sistema';

    public function handle(): int
    {
        $users = User::active()
            ->where('receive_alerts', true)
            ->when($this->option('email'), fn ($q, $email) => $q->where('email', $email))
            ->get();

        foreach ($users as $user) {
            // Quien no tiene apps asignadas (y no es superadmin) no recibe un correo vacío.
            if (! $user->isSuperadmin() && $user->applications()->doesntExist()) {
                continue;
            }

            try {
                Mail::to($user)->send(new WeeklyReport($user));
                $this->line("Enviado a {$user->email}");
            } catch (Throwable $e) {
                report($e);
                $this->error("{$user->email}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
