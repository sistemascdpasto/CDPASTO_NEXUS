<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\Application;
use App\Models\User;
use App\Notifications\AlertRaised;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Registra alertas y notifica por correo/WhatsApp a los responsables (HU-20, HU-21, HU-22).
 */
class AlertManager
{
    /**
     * @param  array<string, mixed>  $data
     * @param  bool  $cooldown  Si es true, no se repite la misma alerta para la app durante el periodo de enfriamiento.
     */
    public function raise(?Application $application, AlertType $type, string $title, string $message, array $data = [], bool $cooldown = true): ?Alert
    {
        if ($cooldown && $this->recentlyRaised($application, $type)) {
            return null;
        }

        $alert = Alert::create([
            'application_id' => $application?->id,
            'type' => $type,
            'severity' => $type->severity(),
            'title' => $title,
            'message' => $message,
            'data' => $data ?: null,
        ]);

        $recipients = $this->recipientsFor($application);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AlertRaised($alert));
        }

        return $alert;
    }

    private function recentlyRaised(?Application $application, AlertType $type): bool
    {
        return Alert::query()
            ->where('application_id', $application?->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subMinutes(config('nexus.alerts.cooldown_minutes')))
            ->exists();
    }

    /**
     * Superadmins + usuarios asignados a la app, activos y con alertas habilitadas.
     *
     * @return Collection<int, User>
     */
    private function recipientsFor(?Application $application)
    {
        return User::query()
            ->active()
            ->where('receive_alerts', true)
            ->where(function ($query) use ($application) {
                $query->where('role', UserRole::Superadmin);

                if ($application) {
                    $query->orWhereHas('applications', fn ($q) => $q->whereKey($application->id));
                }
            })
            ->get();
    }
}
