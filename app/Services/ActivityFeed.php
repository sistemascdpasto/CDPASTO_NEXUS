<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\ErrorEvent;
use App\Models\LoginEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Línea de tiempo unificada de lo que pasa en todas las apps: acciones, accesos, errores y alertas.
 */
class ActivityFeed
{
    private const ACTION_LABELS = [
        'created' => 'creó',
        'updated' => 'editó',
        'deleted' => 'eliminó',
        'restored' => 'restauró',
        'uploaded' => 'subió',
        'downloaded' => 'descargó',
        'exported' => 'exportó',
    ];

    /**
     * @param  list<int>  $applicationIds
     * @param  list<string>  $kinds  audit | login | error | alert
     * @return Collection<int, array<string, mixed>>
     */
    public function latest(array $applicationIds, int $limit = 40, array $kinds = ['audit', 'login', 'error', 'alert'], ?Carbon $since = null): Collection
    {
        $since ??= now()->subDays(2);
        $items = collect();

        if (in_array('audit', $kinds, true)) {
            $items = $items->concat(AuditLog::with('application:id,name')
                ->whereIn('application_id', $applicationIds)->where('occurred_at', '>=', $since)
                ->latest('occurred_at')->latest('id')->limit($limit)->get()
                ->map(fn (AuditLog $log) => [
                    'id' => "audit-{$log->id}",
                    'kind' => 'audit',
                    'action' => $log->action,
                    'app' => $log->application?->name,
                    'app_id' => $log->application_id,
                    'title' => ($log->user_name ?? 'Sistema').' '.(self::ACTION_LABELS[$log->action] ?? $log->action).' '.$this->auditObject($log),
                    'subtitle' => $log->module.($log->record_id ? " #{$log->record_id}" : ''),
                    'href' => $log->external_user_id ? "/applications/{$log->application_id}/users/{$log->external_user_id}" : '/audit?application_id='.$log->application_id,
                    'at' => $log->occurred_at,
                ]));
        }

        if (in_array('login', $kinds, true)) {
            $items = $items->concat(LoginEvent::with('application:id,name')
                ->whereIn('application_id', $applicationIds)->where('occurred_at', '>=', $since)
                ->whereIn('event', ['login', 'failed', 'lockout', 'blocked'])
                ->latest('occurred_at')->limit($limit)->get()
                ->map(fn (LoginEvent $event) => [
                    'id' => "login-{$event->id}",
                    'kind' => 'login',
                    'action' => $event->event,
                    'app' => $event->application?->name,
                    'app_id' => $event->application_id,
                    'title' => match ($event->event) {
                        'login' => ($event->user_name ?? $event->identifier).' inició sesión',
                        'failed' => 'Intento fallido para '.($event->identifier ?? 'usuario desconocido'),
                        'lockout' => 'Bloqueo por intentos: '.($event->identifier ?? '—'),
                        default => ($event->user_name ?? $event->identifier ?? 'Usuario').' fue bloqueado',
                    },
                    'subtitle' => trim(($event->device ?? '').' · '.($event->ip ?? ''), ' ·'),
                    'href' => $event->external_user_id ? "/applications/{$event->application_id}/users/{$event->external_user_id}" : '/logins?application_id='.$event->application_id,
                    'at' => $event->occurred_at,
                ]));
        }

        if (in_array('error', $kinds, true)) {
            $items = $items->concat(ErrorEvent::with('application:id,name', 'group:id,exception_class,message')
                ->whereIn('application_id', $applicationIds)->where('occurred_at', '>=', $since)
                ->latest('occurred_at')->limit($limit)->get()
                ->map(fn (ErrorEvent $event) => [
                    'id' => "error-{$event->id}",
                    'kind' => 'error',
                    'action' => 'error',
                    'app' => $event->application?->name,
                    'app_id' => $event->application_id,
                    'title' => class_basename($event->group?->exception_class ?? 'Error').': '.mb_strimwidth((string) $event->group?->message, 0, 90, '…'),
                    'subtitle' => $event->user_name ? "Le ocurrió a {$event->user_name}" : ($event->url ?? ''),
                    'href' => "/errors/{$event->error_group_id}",
                    'at' => $event->occurred_at,
                ]));
        }

        if (in_array('alert', $kinds, true)) {
            $items = $items->concat(Alert::with('application:id,name')
                ->whereIn('application_id', $applicationIds)->where('created_at', '>=', $since)
                ->latest()->limit($limit)->get()
                ->map(fn (Alert $alert) => [
                    'id' => "alert-{$alert->id}",
                    'kind' => 'alert',
                    'action' => $alert->severity,
                    'app' => $alert->application?->name,
                    'app_id' => $alert->application_id,
                    'title' => $alert->title,
                    'subtitle' => mb_strimwidth($alert->message, 0, 100, '…'),
                    'href' => '/alerts',
                    'at' => $alert->created_at,
                ]));
        }

        return $items->sortByDesc('at')->take($limit)->values();
    }

    private function auditObject(AuditLog $log): string
    {
        $values = $log->new_values ?? [];

        if ($log->action === 'uploaded' && ! empty($values['archivos'])) {
            $names = array_column($values['archivos'], 'nombre');

            return count($names) === 1 ? "«{$names[0]}»" : count($names).' archivos';
        }

        if ($log->action === 'downloaded' && ! empty($values['archivo'])) {
            return "«{$values['archivo']}»";
        }

        $module = trim(strrchr(' / '.$log->module, '/'), '/ ');

        return mb_strtolower($module);
    }
}
