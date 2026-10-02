<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Enums\AppStatus;
use App\Models\Application;
use App\Models\HealthCheck;
use App\Support\AgentSigner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Consulta el endpoint de salud de cada app (HU-04, HU-06) y dispara alertas de caída/recuperación (HU-20).
 */
class HealthChecker
{
    public function __construct(private AlertManager $alerts) {}

    /**
     * @param  Collection<int, Application>  $applications
     */
    public function checkMany(Collection $applications): void
    {
        if ($applications->isEmpty()) {
            return;
        }

        $timeout = config('nexus.health.timeout_seconds');
        $started = [];

        $responses = Http::pool(function (Pool $pool) use ($applications, $timeout, &$started) {
            foreach ($applications as $app) {
                $started[$app->id] = microtime(true);

                $pool->as((string) $app->id)
                    ->withHeaders($app->isLaravel() ? AgentSigner::headers($app) : ['User-Agent' => 'Nexus-Monitor/1.0'])
                    ->timeout($timeout)
                    ->connectTimeout($timeout)
                    ->get($app->healthUrl());
            }
        });

        foreach ($applications as $app) {
            $result = $responses[(string) $app->id] ?? null;
            $elapsed = (int) round((microtime(true) - $started[$app->id]) * 1000);

            $this->record($app, $result, $elapsed);
        }
    }

    public function check(Application $application): HealthCheck
    {
        $this->checkMany(collect([$application]));

        return $application->healthChecks()->latest('checked_at')->latest('id')->first();
    }

    private function record(Application $app, mixed $result, int $elapsedMs): void
    {
        [$checkStatus, $httpStatus, $responseMs, $components, $error] = $this->evaluate($app, $result, $elapsedMs);

        $app->healthChecks()->create([
            'status' => $checkStatus,
            'http_status' => $httpStatus,
            'response_ms' => $responseMs,
            'components' => $components,
            'error' => $error ? mb_substr($error, 0, 500) : null,
            'checked_at' => now(),
        ]);

        $previous = $app->status;
        $failures = $checkStatus === AppStatus::Down ? $app->consecutive_failures + 1 : 0;

        // Una caída aislada no cambia el estado hasta confirmar N fallos seguidos.
        $newStatus = $checkStatus === AppStatus::Down && $failures < config('nexus.health.down_after_failures')
            ? ($previous === AppStatus::Down ? AppStatus::Down : AppStatus::Degraded)
            : $checkStatus;

        $app->forceFill([
            'status' => $newStatus,
            'consecutive_failures' => $failures,
            'last_status_code' => $httpStatus,
            'last_response_ms' => $responseMs,
            'last_components' => $components,
            'last_checked_at' => now(),
            'status_changed_at' => $newStatus !== $previous ? now() : $app->status_changed_at,
        ])->save();

        $this->notifyTransition($app, $previous, $newStatus, $error);
    }

    /**
     * @return array{0: AppStatus, 1: ?int, 2: ?int, 3: ?array, 4: ?string}
     */
    private function evaluate(Application $app, mixed $result, int $elapsedMs): array
    {
        if ($result instanceof ConnectionException || $result instanceof Throwable) {
            return [AppStatus::Down, null, null, null, $result->getMessage()];
        }

        if (! $result instanceof Response) {
            return [AppStatus::Down, null, null, null, 'Sin respuesta'];
        }

        $status = $result->status();
        $responseMs = (int) round(($result->handlerStats()['total_time'] ?? $elapsedMs / 1000) * 1000);

        // El agente responde 401 si la firma no coincide: la web está viva pero mal configurada.
        if (in_array($status, [401, 403], true) && $app->isLaravel()) {
            return [AppStatus::Degraded, $status, $responseMs, null, 'El agente rechazó la firma: revisa NEXUS_KEY en la app.'];
        }

        if ($status === 404 && $app->isLaravel()) {
            return [AppStatus::Degraded, $status, $responseMs, null, 'Endpoint /nexus/health no encontrado: ¿está instalado el agente?'];
        }

        $components = $app->isLaravel() ? $result->json('components') : null;

        // Si el agente devolvió componentes, PHP está vivo: los fallos internos se tratan como degradación.
        if (! is_array($components) && $status >= 400) {
            return [AppStatus::Down, $status, $responseMs, null, "HTTP {$status}"];
        }

        $failing = collect(is_array($components) ? $components : [])
            ->filter(fn ($component) => is_array($component) && ($component['ok'] ?? true) === false)
            ->keys();

        if ($failing->isNotEmpty()) {
            return [AppStatus::Degraded, $status, $responseMs, $components, 'Componentes con fallo: '.$failing->implode(', ')];
        }

        if ($responseMs > $app->slow_threshold_ms) {
            return [AppStatus::Degraded, $status, $responseMs, $components, "Respuesta lenta ({$responseMs} ms)"];
        }

        return [AppStatus::Online, $status, $responseMs, $components, null];
    }

    private function notifyTransition(Application $app, ?AppStatus $previous, AppStatus $current, ?string $error): void
    {
        if ($current === $previous) {
            return;
        }

        if ($current === AppStatus::Down) {
            $this->alerts->raise($app, AlertType::Down, "{$app->name} no responde", $error ?? 'La aplicación dejó de responder.', [
                'url' => $app->healthUrl(),
            ], cooldown: false);

            return;
        }

        if ($previous === AppStatus::Down) {
            $downSince = $app->healthChecks()
                ->where('status', '!=', AppStatus::Down->value)
                ->where('checked_at', '<', now()->subSeconds(5))
                ->latest('checked_at')
                ->value('checked_at');

            $this->alerts->raise($app, AlertType::Recovered, "{$app->name} volvió a estar en línea", 'La aplicación responde nuevamente.', [
                'down_since' => $downSince,
            ], cooldown: false);
        }
    }
}
